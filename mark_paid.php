<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$billID = $_POST['billID'] ?? null;
$status = $_POST['status'] ?? 'paid';

if (!$billID) {
    echo json_encode(['error' => 'No bill ID provided']);
    exit();
}

try {
    // Update tblBills
    $pdo->prepare("UPDATE tblBills SET status = :status WHERE billID = :billID")
        ->execute(['status' => $status, 'billID' => $billID]);

    // Update all linked orders in tblOrders with the same status
    $pdo->prepare("
        UPDATE tblOrders 
        SET status = :status 
        WHERE orderID IN (
            SELECT orderID FROM tblBillOrders WHERE billID = :billID
        )
    ")->execute(['status' => $status, 'billID' => $billID]);

    echo json_encode(['success' => true, 'status' => $status]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>