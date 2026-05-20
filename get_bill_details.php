<?php
require_once 'db.php';
header('Content-Type: application/json');

$billID = $_GET['billID'] ?? null;
if (!$billID) {
    echo json_encode(['error' => 'No bill ID provided']);
    exit();
}

try {
    // Get bill info
    $stmt = $pdo->prepare("
        SELECT tblBills.*, tblusers.fullName
        FROM tblBills
        JOIN tblusers ON tblBills.userID = tblusers.userID
        WHERE tblBills.billID = :billID
    ");
    $stmt->execute(['billID' => $billID]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get orders under this bill
    $stmt = $pdo->prepare("
        SELECT tblOrders.orderID, tblOrders.orderDate, tblOrders.totalAmount
        FROM tblBillOrders
        JOIN tblOrders ON tblBillOrders.orderID = tblOrders.orderID
        WHERE tblBillOrders.billID = :billID
    ");
    $stmt->execute(['billID' => $billID]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get items for each order
    $items = [];
    foreach ($orders as $order) {
        $stmt = $pdo->prepare("
            SELECT tblOrderItems.*, tblItems.itemName, tblItems.itemUnit
            FROM tblOrderItems
            JOIN tblItems ON tblOrderItems.itemID = tblItems.itemID
            WHERE tblOrderItems.orderID = :orderID
        ");
        $stmt->execute(['orderID' => $order['orderID']]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($orderItems as $item) {
            $items[] = $item;
        }
    }

    echo json_encode(['bill' => $bill, 'items' => $items]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
