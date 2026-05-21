<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$billID = $_POST['billID'] ?? null;

if (!$billID) {
    echo json_encode(['error' => 'No bill ID provided']);
    exit();
}

try {
    // Get order IDs linked to this bill
    $stmt = $pdo->prepare("SELECT orderID FROM tblBillOrders WHERE billID = :billID");
    $stmt->execute(['billID' => $billID]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete tblBillOrders
    $pdo->prepare("DELETE FROM tblBillOrders WHERE billID = :billID")
        ->execute(['billID' => $billID]);

    // Delete associated orders and order items
    foreach ($orders as $order) {
        $pdo->prepare("DELETE FROM tblOrderItems WHERE orderID = :orderID")
            ->execute(['orderID' => $order['orderID']]);
        $pdo->prepare("DELETE FROM tblOrders WHERE orderID = :orderID")
            ->execute(['orderID' => $order['orderID']]);
    }

    // Delete the bill
    $pdo->prepare("DELETE FROM tblBills WHERE billID = :billID")
        ->execute(['billID' => $billID]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
