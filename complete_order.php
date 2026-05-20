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

    // Check if bill already exists for this order
    $checkBill = $pdo->prepare("SELECT billID FROM tblBillOrders WHERE orderID = :orderID");
    $checkBill->execute(['orderID' => $orderID]);
    $existingBill = $checkBill->fetch(PDO::FETCH_ASSOC);

    if ($existingBill) {
        // Already billed — just update the order status and return
        $pdo->prepare("UPDATE tblOrders SET status = 'billed' WHERE orderID = :orderID")
            ->execute(['orderID' => $orderID]);
        echo json_encode([
            'success' => true,
            'message' => 'Order already billed',
            'billID' => $existingBill['billID']
        ]);
        exit();
    }

    // Generate bill number
    $billNumber = generateBillNumber($pdo, date('Y-m-d'));

    if (!$billNumber) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate bill number']);
        exit();
    }

    // Create new bill
    $stmt = $pdo->prepare("
    INSERT INTO tblBills (userID, billNumber, billDate, dueDate, totalAmount, status, penaltyAmount)
    VALUES (:userID, :billNumber, :billDate, :dueDate, :totalAmount, 'unpaid', 0)
    ");
    $stmt->execute([
        'userID'      => $order['userID'],
        'billNumber'  => $billNumber,
        'billDate'    => date('Y-m-d'),
        'dueDate'     => date('Y-m-d', strtotime('+15 days')), // changed to 15 days per your requirement
        'totalAmount' => $order['totalAmount'],
    ]);
    $billID = $pdo->lastInsertId();

    // Link bill to order
    $pdo->prepare("INSERT INTO tblBillOrders (billID, orderID) VALUES (:billID, :orderID)")
        ->execute(['billID' => $billID, 'orderID' => $orderID]);

    // Update order status to billed
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
            'billDate'     => date('M d, Y'),
            'dueDate'      => date('M d, Y', strtotime('+15 days')),
            'customerName' => $order['fullName'],
            'totalAmount'  => $order['totalAmount'],
            'items'        => $items
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
