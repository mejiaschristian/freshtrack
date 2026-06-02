<?php
require_once 'db.php';

function getAllItems($pdo)
{
    try {
        $stmt = $pdo->query("SELECT i.*, c.categoryName FROM tblitems i JOIN tblcategories c ON i.categoryID = c.categoryID ORDER BY i.itemName ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getItemsByCategory($pdo, $categoryID)
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM tblitems WHERE categoryID = :categoryID ORDER BY itemName ASC");
        $stmt->execute([':categoryID' => $categoryID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getLowStockItems($pdo)
{
    try {
        $stmt = $pdo->query("SELECT i.*, c.categoryName FROM tblitems i JOIN tblcategories c ON i.categoryID = c.categoryID WHERE i.itemQuantity < 10 OR i.itemQuantity <= i.reorderLevel ORDER BY i.itemQuantity ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get items nearing expiration or already expired (Up to the upcoming 7 days) - Broken down per batch
 */
function getExpiringItems($pdo, $days = 7)
{
    try {
        $stmt = $pdo->prepare("
            SELECT i.itemName, b.quantity, b.expiryDate, b.batchCode
            FROM tblItemBatches b
            JOIN tblitems i ON b.itemID = i.itemID
            WHERE b.expiryDate <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
              AND b.quantity > 0
            ORDER BY b.expiryDate ASC
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getAllOrders($pdo)
{
    try {
        return $pdo->query("SELECT * FROM tblorders ORDER BY orderDate DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getOrderDetails($pdo, $orderID)
{
    try {
        $stmt = $pdo->prepare("SELECT od.*, i.itemName, i.itemPrice FROM tblorderdetails od JOIN tblitems i ON od.itemID = i.itemID WHERE od.orderID = :orderID");
        $stmt->execute([':orderID' => $orderID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getTotalInventoryValue($pdo)
{
    try {
        // Only sum batches where quantity is greater than 0 AND expiration date is in the future
        $sql = "SELECT SUM(b.quantity * i.itemPrice) as total 
                FROM tblItemBatches b 
                JOIN tblitems i ON b.itemID = i.itemID
                WHERE b.quantity > 0 
                  AND b.expiryDate > CURDATE()";
        $result = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function getDashboardStats($pdo)
{
    try {
        $stats = [];
        $stats['totalOrders'] = $pdo->query("SELECT COUNT(*) FROM tblorders")->fetchColumn();
        $stats['pendingOrders'] = $pdo->query("SELECT COUNT(*) FROM tblorders WHERE status = 'pending'")->fetchColumn();
        $stats['totalItems'] = $pdo->query("SELECT COUNT(*) FROM tblitems")->fetchColumn();
        $stats['lowStockCount'] = $pdo->query("SELECT COUNT(*) FROM tblitems WHERE itemQuantity <= reorderLevel")->fetchColumn();
        $stats['totalUsers'] = $pdo->query("SELECT COUNT(*) FROM tblusers")->fetchColumn();
        $stats['inventoryValue'] = getTotalInventoryValue($pdo);

        // Realized Received Cash Revenue (Only Paid and Partially Paid orders)
        $stats['realizedRevenue'] = $pdo->query("SELECT COALESCE(SUM(totalAmount), 0) FROM tblorders WHERE status IN ('paid', 'partial')")->fetchColumn();

        // Expected Pending Revenue (Stock is gone, but payment is still pending/unpaid/billed)
        $stats['expectedRevenue'] = $pdo->query("SELECT COALESCE(SUM(totalAmount), 0) FROM tblorders WHERE status IN ('pending', 'unpaid', 'billed')")->fetchColumn();

        return $stats;
    } catch (PDOException $e) {
        return [];
    }
}

function getRecentOrders($pdo, $limit = 5)
{
    try {
        // Explicitly pulling o.status AS orderStatus prevents u.status from overwriting it
        $stmt = $pdo->prepare("
            SELECT o.*, o.status AS orderStatus, u.fullName AS customerName 
            FROM tblorders o 
            INNER JOIN tblusers u ON o.userID = u.userID 
            ORDER BY o.orderDate DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getOrdersTrend($pdo)
{
    try {
        $sql = "SELECT 
                    DATE(orderDate) as day, 
                    COUNT(*) as orderCount, 
                    SUM(CASE WHEN status IN ('paid', 'partial') THEN totalAmount ELSE 0 END) as revenue,
                    SUM(CASE WHEN status IN ('pending', 'unpaid', 'billed') THEN totalAmount ELSE 0 END) as expected_revenue
                FROM tblorders 
                WHERE orderDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
                GROUP BY DATE(orderDate) 
                ORDER BY day ASC";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getTopSellingItems($pdo, $limit = 5)
{
    try {
        $stmt = $pdo->prepare("SELECT i.itemName, SUM(oi.quantity) as totalQty, SUM(oi.quantity * oi.price) as totalRevenue FROM tblorderitems oi JOIN tblitems i ON i.itemID = oi.itemID JOIN tblorders o ON oi.orderID = o.orderID WHERE o.status IN ('paid', 'partial') GROUP BY oi.itemID ORDER BY totalQty DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function generateBillNumber(PDO $pdo, string $date = null): string
{
    $datePrefix = date('Y-m', strtotime($date ?: 'now'));
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(billNumber, -3) AS UNSIGNED)), 0) AS maxSeq FROM tblBills WHERE billNumber LIKE :prefix");
    $stmt->execute(['prefix' => 'BILL-' . $datePrefix . '%']);
    $nextSeq = (int)$stmt->fetchColumn() + 1;
    return 'BILL-' . $datePrefix . '-' . str_pad($nextSeq, 3, '0', STR_PAD_LEFT);
}

function createBillForOrder(PDO $pdo, int $orderID, int $dueDays = 15): array
{
    $checkBill = $pdo->prepare("SELECT b.billID, b.billNumber, b.dueDate, b.billDate, b.totalAmount FROM tblBillOrders bo JOIN tblBills b ON bo.billID = b.billID WHERE bo.orderID = :orderID");
    $checkBill->execute(['orderID' => $orderID]);
    $existing = $checkBill->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        return $existing;
    }

    $stmt = $pdo->prepare("SELECT * FROM tblOrders WHERE orderID = :orderID");
    $stmt->execute(['orderID' => $orderID]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    $billNumber = generateBillNumber($pdo);
    $billDate = date('Y-m-d');
    $dueDate = date('Y-m-d', strtotime("+{$dueDays} days"));

    $insertBill = $pdo->prepare("INSERT INTO tblBills (userID, billNumber, billDate, dueDate, totalAmount, status, penaltyAmount) VALUES (:userID, :billNumber, :billDate, :dueDate, :totalAmount, 'unpaid', 0)");
    $insertBill->execute([
        'userID' => $order['userID'],
        'billNumber' => $billNumber,
        'billDate' => $billDate,
        'dueDate' => $dueDate,
        'totalAmount' => $order['totalAmount']
    ]);
    $billID = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO tblBillOrders (billID, orderID) VALUES (:billID, :orderID)")
        ->execute(['billID' => $billID, 'orderID' => $orderID]);

    return [
        'billID' => $billID,
        'billNumber' => $billNumber,
        'billDate' => $billDate,
        'dueDate' => $dueDate,
        'totalAmount' => $order['totalAmount']
    ];
}

function getOrderStatusBreakdown($pdo)
{
    try {
        return $pdo->query("SELECT status, COUNT(*) as count, SUM(totalAmount) as total FROM tblorders GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getRevenueComparison($pdo)
{
    try {
        $sql = "SELECT
            -- Realized Cash Revenue (Paid/Partial)
            SUM(CASE WHEN orderDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status IN ('paid', 'partial') THEN totalAmount ELSE 0 END) as thisWeekRealized,
            SUM(CASE WHEN orderDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status IN ('paid', 'partial') THEN totalAmount ELSE 0 END) as lastWeekRealized,
            
            -- Expected Revenue (Pending/Unpaid/Billed)
            SUM(CASE WHEN orderDate >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status IN ('pending', 'unpaid', 'billed') THEN totalAmount ELSE 0 END) as thisWeekExpected,
            SUM(CASE WHEN orderDate BETWEEN DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status IN ('pending', 'unpaid', 'billed') THEN totalAmount ELSE 0 END) as lastWeekExpected
        FROM tblorders";
        return $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Unique items with nested batches (including past expired ones) to avoid interface duplicate rows
 */
function getExpiringItemsWithValue($pdo, $days = 7)
{
    try {
        $stmt = $pdo->prepare("
            SELECT i.itemID, i.itemName, i.itemPrice, 
                   SUM(b.quantity) as total_qty,
                   SUM(b.quantity * i.itemPrice) as total_waste_value
            FROM tblItemBatches b
            JOIN tblitems i ON b.itemID = i.itemID
            WHERE b.expiryDate <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
              AND b.quantity > 0
            GROUP BY i.itemID, i.itemName, i.itemPrice
            ORDER BY total_waste_value DESC
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch sub-batches for each unique item row
        foreach ($items as &$item) {
            $bStmt = $pdo->prepare("
                SELECT batchCode, quantity, expiryDate 
                FROM tblItemBatches 
                WHERE itemID = :id 
                  AND quantity > 0 
                  AND expiryDate <= DATE_ADD(CURDATE(), INTERVAL :days DAY) 
                ORDER BY expiryDate ASC
            ");
            $bStmt->execute(['id' => $item['itemID'], 'days' => $days]);
            $item['batches'] = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $items;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get all users
 */
function getAllUsers($pdo)
{
    try {
        $stmt = $pdo->query("SELECT * FROM tblusers ORDER BY fullName ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
