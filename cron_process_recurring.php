<?php
// cron_process_recurring.php
require_once 'db.php';

function processAutomaticRecurringBatches($pdo) {
    try {
        // Prevent recursive infinity loops if multiple entries occur concurrently
        if (defined('AUTOMATION_RUNNING')) return;
        define('AUTOMATION_RUNNING', true);

        $todayStr = date('Y-m-d');
        
        // 1. Fetch active due templates
        $stmt = $pdo->prepare("SELECT * FROM tblRecurringOrders WHERE status = 'active' AND nextDeliveryDate <= :today");
        $stmt->execute(['today' => $todayStr]);
        $dueTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($dueTemplates)) {
            return; // Exit silently if nothing is due today
        }

        $pdo->beginTransaction();

        // 2. Prepare reusable database statements
        $insertOrderStmt = $pdo->prepare("
            INSERT INTO tblOrders (userID, totalAmount, orderDate, deliveryDate, status, orderType, deliveryTimeSlot, estimatedDelivery, recurringOrderID)
            VALUES (:userID, :totalAmount, :orderDate, :deliveryDate, 'pending', :orderType, :deliveryTimeSlot, :estimatedDelivery, :recurringOrderID)
        ");

        $fetchItemsStmt = $pdo->prepare("
            SELECT ri.*, i.itemPrice 
            FROM tblRecurringOrderItems ri
            JOIN tblItems i ON ri.itemID = i.itemID
            WHERE ri.recurringID = :recurringID
        ");

        $insertItemStmt = $pdo->prepare("
            INSERT INTO tblOrderItems (orderID, itemID, quantity, price)
            VALUES (:orderID, :itemID, :quantity, :price)
        ");

        $updateStockStmt = $pdo->prepare("
            UPDATE tblItems SET itemQuantity = itemQuantity - :qty WHERE itemID = :itemID
        ");

        $updateTemplateStmt = $pdo->prepare("
            UPDATE tblRecurringOrders SET nextDeliveryDate = :nextDate WHERE recurringID = :recurringID
        ");

        foreach ($dueTemplates as $template) {
            $recID = $template['recurringID'];

            // Fetch template items
            $fetchItemsStmt->execute(['recurringID' => $recID]);
            $templateItems = $fetchItemsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($templateItems)) continue;

            // Calculate current total cost matching prices from inventory
            $totalAmount = 0;
            foreach ($templateItems as $item) {
                $totalAmount += ($item['itemPrice'] * $item['quantity']);
            }

            $orderDate = date('Y-m-d');
            $deliveryDate = $template['nextDeliveryDate']; 
            $timeLabel = ucfirst($template['deliveryTimeSlot']);
            $estimatedText = date('M d, Y', strtotime($deliveryDate)) . " [{$timeLabel}]";

            // 3. Spawns new active transaction record into live queue
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

            // 4. Duplicate tracking products items lists and adjust quantities
            foreach ($templateItems as $item) {
                $insertItemStmt->execute([
                    'orderID'  => $newOrderID,
                    'itemID'   => $item['itemID'],
                    'quantity' => $item['quantity'],
                    'price'    => $item['itemPrice']
                ]);

                $updateStockStmt->execute([
                    'qty'    => $item['quantity'],
                    'itemID' => $item['itemID']
                ]);
            }

            // 5. Calculate next release timeline date (Weekly vs Monthly)
            $currentSchedule = new DateTime($template['nextDeliveryDate']);
            if ($template['frequency'] === 'weekly') {
                $currentSchedule->modify('+7 days');
            } else {
                $currentSchedule->modify('+1 month');
            }
            if ($currentSchedule->format('N') == 7) {
                $currentSchedule->modify('+1 day'); // Push past Sundays
            }
            
            $newNextDeliveryDate = $currentSchedule->format('Y-m-d');

            // Save new calculation back to template card
            $updateTemplateStmt->execute([
                'nextDate'    => $newNextDeliveryDate,
                'recurringID' => $recID
            ]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}