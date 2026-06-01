<?php
require_once 'db.php';
require_once 'cron_process_recurring.php';
header('Content-Type: application/json');

$orderID = $_GET['orderID'] ?? null;
if (!$orderID) {
    echo json_encode(['error' => 'No order ID provided']);
    exit();
}

try {
    // Get order info + hotel name
    $stmt = $pdo->prepare("
        SELECT tblOrders.*, tblusers.fullName 
        FROM tblOrders 
        JOIN tblusers ON tblOrders.userID = tblusers.userID 
        WHERE tblOrders.orderID = :orderID
    ");
    $stmt->execute(['orderID' => $orderID]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get order items
    $stmt = $pdo->prepare("
        SELECT tblOrderItems.*, tblItems.itemName, tblItems.itemUnit
        FROM tblOrderItems
        JOIN tblItems ON tblOrderItems.itemID = tblItems.itemID
        WHERE tblOrderItems.orderID = :orderID
    ");
    $stmt->execute(['orderID' => $orderID]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if a bill exists for this order
    $billStmt = $pdo->prepare("SELECT billID FROM tblBillOrders WHERE orderID = :orderID");
    $billStmt->execute(['orderID' => $orderID]);
    $billRow = $billStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'order'  => $order,
        'items'  => $items,
        'billID' => $billRow ? $billRow['billID'] : null
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
