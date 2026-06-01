<?php
// cron_process_recurring.php
require_once 'db.php';

function processAutomaticRecurringBatches($pdo)
{
    try {
        if (defined('AUTOMATION_RUNNING')) return;
        define('AUTOMATION_RUNNING', true);

        $todayStr = date('Y-m-d');

        // 1. Fetch BOTH active due templates AND templates auto-paused by the system due to zero stock
        $stmt = $pdo->prepare("
            SELECT * FROM tblRecurringOrders 
            WHERE (status = 'active' AND nextDeliveryDate <= :today)
               OR (status = 'paused_no_stock' AND nextDeliveryDate <= :today)
        ");
        $stmt->execute(['today' => $todayStr]);
        $dueTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($dueTemplates)) {
            return;
        }

        // Reusable SQL Statements
        $fetchItemsStmt = $pdo->prepare("
            SELECT ri.*, i.itemPrice 
            FROM tblRecurringOrderItems ri
            JOIN tblItems i ON ri.itemID = i.itemID
            WHERE ri.recurringID = :recurringID
        ");

        $checkBatchStockStmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) as totalAvailable 
            FROM tblItemBatches 
            WHERE itemID = :itemID AND quantity > 0 AND expiryDate >= :today
        ");

        $setStatusStmt = $pdo->prepare("
            UPDATE tblRecurringOrders SET status = :status WHERE recurringID = :recurringID
        ");

        $insertOrderStmt = $pdo->prepare("
            INSERT INTO tblOrders (userID, totalAmount, orderDate, deliveryDate, status, orderType, deliveryTimeSlot, estimatedDelivery, recurringOrderID)
            VALUES (:userID, :totalAmount, :orderDate, :deliveryDate, 'pending', :orderType, :deliveryTimeSlot, :estimatedDelivery, :recurringOrderID)
        ");

        $insertItemStmt = $pdo->prepare("
            INSERT INTO tblOrderItems (orderID, itemID, quantity, price)
            VALUES (:orderID, :itemID, :quantity, :price)
        ");

        $fetchBatchesStmt = $pdo->prepare("
            SELECT batchID, quantity 
            FROM tblItemBatches 
            WHERE itemID = :itemID AND quantity > 0 
            ORDER BY harvestDate ASC, batchID ASC
        ");

        $deductBatchStmt = $pdo->prepare("
            UPDATE tblItemBatches SET quantity = quantity - :deduct WHERE batchID = :batchID
        ");

        $syncMasterQtyStmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS totalQty, 
                   MIN(CASE WHEN quantity > 0 THEN expiryDate END) AS fifoExpiry 
            FROM tblItemBatches 
            WHERE itemID = :itemID
        ");

        $updateMasterItemStmt = $pdo->prepare("
            UPDATE tblItems SET itemQuantity = :qty, itemExpiryDate = :exp WHERE itemID = :itemID
        ");

        $updateTemplateStmt = $pdo->prepare("
            UPDATE tblRecurringOrders SET nextDeliveryDate = :nextDate, status = 'active' WHERE recurringID = :recurringID
        ");

        foreach ($dueTemplates as $template) {
            $recID = $template['recurringID'];

            // Fetch template items
            $fetchItemsStmt->execute(['recurringID' => $recID]);
            $templateItems = $fetchItemsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($templateItems)) continue;

            // ─── STAGE 1: INVENTORY VERIFICATION ───
            $insufficientStockFound = false;
            foreach ($templateItems as $item) {
                $checkBatchStockStmt->execute([
                    'itemID' => $item['itemID'],
                    'today'  => $todayStr
                ]);
                $availableStock = (int)$checkBatchStockStmt->fetchColumn();

                if ($availableStock < (int)$item['quantity']) {
                    $insufficientStockFound = true;
                    break;
                }
            }

            // If stock is now available and was paused_no_stock, auto-resume it
            if (!$insufficientStockFound && $template['status'] === 'paused_no_stock') {
                // Calculate next delivery date from today's run
                $nextDelivery = new DateTime($todayStr);
                if ($template['frequency'] === 'weekly') {
                    $nextDelivery->modify('+7 days');
                } else {
                    $nextDelivery->modify('+1 month');
                }
                if ($nextDelivery->format('N') == 7) {
                    $nextDelivery->modify('+1 day');
                }

                $pdo->prepare("
                    UPDATE tblRecurringOrders 
                    SET status = 'active', nextDeliveryDate = :nextDate 
                    WHERE recurringID = :recurringID
                ")->execute(['nextDate' => $nextDelivery->format('Y-m-d'), 'recurringID' => $recID]);

                // Continue processing the order placement since stock is now available
            }

            // If stock is not enough:
            if ($insufficientStockFound) {
                // If it was active, flag it as 'paused_no_stock'
                if ($template['status'] === 'active') {
                    $setStatusStmt->execute(['status' => 'paused_no_stock', 'recurringID' => $recID]);
                }
                // Skip processing order placement
                continue;
            }

            // ─── STAGE 2: EXECUTE AUTO-RESUME AND REORDER ───
            try {
                $pdo->beginTransaction();

                // Calculate totals matching live item Prices
                $totalAmount = 0;
                foreach ($templateItems as $item) {
                    $totalAmount += ($item['itemPrice'] * $item['quantity']);
                }

                $orderDate = date('Y-m-d');
                // If it was auto-paused, delivery date resets to today's schedule run date
                $deliveryDate = ($template['status'] === 'paused_no_stock') ? $orderDate : $template['nextDeliveryDate'];

                $timeLabel = ucfirst($template['deliveryTimeSlot']);
                $estimatedText = date('M d, Y', strtotime($deliveryDate)) . " [{$timeLabel}]";

                // Save Order Record
                $insertOrderStmt->execute([
                    'userID'            => $template['userID'],
                    'totalAmount'       => $totalAmount,
                    'orderDate'         => $orderDate,
                    'deliveryDate'      => $deliveryDate,
                    'orderType'         => $template['orderType'],
                    'deliveryTimeSlot'  => $template['deliveryTimeSlot'],
                    'estimatedDelivery' => $estimatedText,
                    'recurringOrderID'  => $recID
                ]);

                $newOrderID = $pdo->lastInsertId();

                // Deduct Batch Products out via FIFO
                foreach ($templateItems as $item) {
                    $insertItemStmt->execute([
                        'orderID'  => $newOrderID,
                        'itemID'   => $item['itemID'],
                        'quantity' => $item['quantity'],
                        'price'    => $item['itemPrice']
                    ]);

                    $fetchBatchesStmt->execute(['itemID' => $item['itemID']]);
                    $batches = $fetchBatchesStmt->fetchAll(PDO::FETCH_ASSOC);

                    $remainingToDeduct = (int)$item['quantity'];
                    foreach ($batches as $batch) {
                        if ($remainingToDeduct <= 0) break;

                        $deductAmount = min($remainingToDeduct, (int)$batch['quantity']);
                        $deductBatchStmt->execute([
                            'deduct'  => $deductAmount,
                            'batchID' => $batch['batchID']
                        ]);

                        $remainingToDeduct -= $deductAmount;
                    }

                    // Sync items inventory master layout values
                    $syncMasterQtyStmt->execute(['itemID' => $item['itemID']]);
                    $sync = $syncMasterQtyStmt->fetch(PDO::FETCH_ASSOC);

                    $updateMasterItemStmt->execute([
                        'qty'    => $sync['totalQty'],
                        'exp'    => $sync['fifoExpiry'] ?? date('Y-m-d'),
                        'itemID' => $item['itemID']
                    ]);
                }

                // Calculate next cycle timeline
                $currentSchedule = new DateTime($deliveryDate);
                if ($template['frequency'] === 'weekly') {
                    $currentSchedule->modify('+7 days');
                } else {
                    $currentSchedule->modify('+1 month');
                }
                if ($currentSchedule->format('N') == 7) {
                    $currentSchedule->modify('+1 day'); // Skip Sundays
                }

                $newNextDeliveryDate = $currentSchedule->format('Y-m-d');

                // Update next tracking date and flip status back to 'active' automatically
                $updateTemplateStmt->execute([
                    'nextDate'    => $newNextDeliveryDate,
                    'recurringID' => $recID
                ]);

                $pdo->commit();
            } catch (Exception $innerEx) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Failed processing recurring order ID " . $recID . ": " . $innerEx->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("Global Automatic Automation Failure: " . $e->getMessage());
    }
}
