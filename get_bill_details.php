<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$billID = $_GET['billID'] ?? null;

if (!$billID) {
    echo json_encode(['error' => 'No bill ID provided']);
    exit();
}

try {
    // Get bill details
    $stmt = $pdo->prepare("
        SELECT tblBills.*, tblusers.fullName
        FROM tblBills
        INNER JOIN tblusers ON tblBills.userID = tblusers.userID
        WHERE tblBills.billID = :billID
    ");
    $stmt->execute(['billID' => $billID]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        echo json_encode(['error' => 'Bill not found']);
        exit();
    }

    // Get bill items
    $stmt = $pdo->prepare("
        SELECT tblOrderItems.*, tblItems.itemName, tblItems.itemUnit
        FROM tblOrderItems
        INNER JOIN tblItems ON tblOrderItems.itemID = tblItems.itemID
        INNER JOIN tblBillOrders ON tblOrderItems.orderID = tblBillOrders.orderID
        WHERE tblBillOrders.billID = :billID
    ");
    $stmt->execute(['billID' => $billID]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'bill' => $bill,
        'items' => $items
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
