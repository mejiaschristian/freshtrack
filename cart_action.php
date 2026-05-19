<?php
session_start();
require 'db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $userID   = $_SESSION['user_id']; // make sure this is set on login
    $itemID   = $_POST['itemID'];
    $quantity = $_POST['quantity'];

    try {
        // Get or create cart for this user
        $stmt = $pdo->prepare("SELECT cartID FROM tblCart WHERE userID = :userID");
        $stmt->execute(['userID' => $userID]);
        $cart = $stmt->fetch();

        if (!$cart) {
            $pdo->prepare("INSERT INTO tblCart (userID) VALUES (:userID)")
                ->execute(['userID' => $userID]);
            $cartID = $pdo->lastInsertId();
        } else {
            $cartID = $cart['cartID'];
        }

        // Check if item already in cart — update quantity if so
        $stmt = $pdo->prepare("SELECT cartItemID, quantity FROM tblCartItems WHERE cartID = :cartID AND itemID = :itemID");
        $stmt->execute(['cartID' => $cartID, 'itemID' => $itemID]);
        $existing = $stmt->fetch();

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;
            $pdo->prepare("UPDATE tblCartItems SET quantity = :qty WHERE cartItemID = :id")
                ->execute(['qty' => $newQty, 'id' => $existing['cartItemID']]);
        } else {
            $pdo->prepare("INSERT INTO tblCartItems (cartID, itemID, quantity) VALUES (:cartID, :itemID, :quantity)")
                ->execute(['cartID' => $cartID, 'itemID' => $itemID, 'quantity' => $quantity]);
        }

        // Before the redirect, fetch the item name
        $itemStmt = $pdo->prepare("SELECT itemName FROM tblItems WHERE itemID = :itemID");
        $itemStmt->execute(['itemID' => $itemID]);
        $itemName = $itemStmt->fetchColumn();

        header('Location: shop.php?success_item=' . urlencode($itemName) . '&success_qty=' . urlencode($quantity));
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
