<?php
session_start();
require 'db.php';
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $userID   = $_SESSION['user_id'];
    $itemID   = (int)$_POST['itemID'];
    $quantity = (int)$_POST['quantity'];

    if ($quantity < 1) {
        header('Location: shop.php?error=invalid_qty');
        exit();
    }

    try {
        $pdo->beginTransaction();

        // ── 1. Get or create cart for this user ─────────────────────────────
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

        // ── 2. Check what's already sitting in their cart ───────────────────
        $stmt = $pdo->prepare("
            SELECT cartItemID, quantity
            FROM   tblCartItems
            WHERE  cartID = :cartID AND itemID = :itemID
        ");
        $stmt->execute(['cartID' => $cartID, 'itemID' => $itemID]);
        $existing = $stmt->fetch();
        
        $existingCartQty = $existing ? (int)$existing['quantity'] : 0;
        $totalRequested  = $existingCartQty + $quantity;

        // ── 3. Verify absolute available total stock across all active batches ──
        $stockStmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) AS totalStock
            FROM   tblItemBatches
            WHERE  itemID = :itemID AND quantity > 0
        ");
        $stockStmt->execute(['itemID' => $itemID]);
        $totalStock = (int)$stockStmt->fetchColumn();

        // Ensure current cart quantity + new addition doesn't break physical stock limits
        if ($totalStock < $totalRequested) {
            $pdo->rollBack(); // Cancel transaction safely
            header('Location: shop.php?error=insufficient_stock'); // Redirect with our error flag
            exit();
        }

        // ── 4. Add / update cart item row ONLY (No batch/item stock deduction) ─
        if ($existing) {
            $pdo->prepare("UPDATE tblCartItems SET quantity = :qty WHERE cartItemID = :id")
                ->execute(['qty' => $totalRequested, 'id' => $existing['cartItemID']]);
        } else {
            $pdo->prepare("
                INSERT INTO tblCartItems (cartID, itemID, quantity)
                VALUES (:cartID, :itemID, :quantity)
            ")->execute(['cartID' => $cartID, 'itemID' => $itemID, 'quantity' => $quantity]);
        }

        $pdo->commit();

        // ── 5. Redirect with success toast ──────────────────────────────────
        $itemStmt = $pdo->prepare("SELECT itemName FROM tblItems WHERE itemID = :itemID");
        $itemStmt->execute(['itemID' => $itemID]);
        $itemName = $itemStmt->fetchColumn();

        header('Location: shop.php?success_item=' . urlencode($itemName) . '&success_qty=' . urlencode($quantity));
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}