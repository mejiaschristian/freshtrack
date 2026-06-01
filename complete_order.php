<?php
session_start();
require_once 'db.php';
require_once 'auth.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['orderID'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    $orderID = $_POST['orderID'];

    // Get order details — remove the empty string status check
    $stmt = $pdo->prepare("
    SELECT tblOrders.*, tblusers.fullName
    FROM tblOrders
    INNER JOIN tblusers ON tblOrders.userID = tblusers.userID
    WHERE tblOrders.orderID = :orderID AND tblOrders.status = 'pending'
");
    $stmt->execute(['orderID' => $orderID]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(400);
        echo json_encode(['error' => 'Order not found or already billed']);
        exit();
    }

    $bill = createBillForOrder($pdo, $orderID);
    $billID = $bill['billID'];
    $billNumber = $bill['billNumber'];
    $billDate = $bill['billDate'];
    $dueDate = $bill['dueDate'];
    $totalAmount = $bill['totalAmount'];

    // Mark order as billed (admin flow only)
    $pdo->prepare("UPDATE tblOrders SET status = 'billed' WHERE orderID = :orderID")
        ->execute(['orderID' => $orderID]);

    // Get order items
    $stmt = $pdo->prepare("
    SELECT tblOrderItems.*, tblItems.itemName, tblItems.itemUnit
    FROM tblOrderItems
    JOIN tblItems ON tblOrderItems.itemID = tblItems.itemID
    WHERE tblOrderItems.orderID = :orderID
    ");
    $stmt->execute(['orderID' => $orderID]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Order billed successfully',
        'billID'  => $billID,
        'bill'    => [
            'billNumber'   => $billNumber,
            'billDate'     => date('M d, Y', strtotime($billDate)),
            'dueDate'      => date('M d, Y', strtotime($dueDate)),
            'customerName' => $order['fullName'],
            'totalAmount'  => $totalAmount,
            'items'        => $items
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
