<?php
session_start();
require 'db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $userID   = $_SESSION['user_id'];
    $itemID   = $_POST['itemID'];
    $quantity = $_POST['quantity'];

    try {
        // Get current item stock
        $stmt = $pdo->prepare("SELECT itemName, itemQuantity FROM tblItems WHERE itemID = :itemID");
        $stmt->execute(['itemID' => $itemID]);
        $item = $stmt->fetch();

        if (!$item) {
            header('Location: shop.php?error=Item not found');
            exit();
        }

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

        // Calculate total quantity that will be in cart
        $currentQuantityInCart = $existing ? $existing['quantity'] : 0;
        $totalQuantity = $currentQuantityInCart + $quantity;

        // Validate stock
        if ($totalQuantity > $item['itemQuantity']) {
            $available = $item['itemQuantity'] - $currentQuantityInCart;
            header('Location: shop.php?error=' . urlencode("Insufficient stock for {$item['itemName']}. Available: {$available} unit(s), Requested: {$quantity} unit(s)"));
            exit();
        }

        // If validation passed, proceed with adding to cart
        if ($existing) {
            $pdo->prepare("UPDATE tblCartItems SET quantity = :qty WHERE cartItemID = :id")
                ->execute(['qty' => $totalQuantity, 'id' => $existing['cartItemID']]);
        } else {
            $pdo->prepare("INSERT INTO tblCartItems (cartID, itemID, quantity) VALUES (:cartID, :itemID, :quantity)")
                ->execute(['cartID' => $cartID, 'itemID' => $itemID, 'quantity' => $quantity]);
        }

        // Before the redirect, fetch the item name
        header('Location: shop.php?success_item=' . urlencode($item['itemName']) . '&success_qty=' . urlencode($quantity));
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
