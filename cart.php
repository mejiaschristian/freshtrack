<?php
session_start();
include 'db.php';
require 'auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$message = "";
$messageType = "";

// Handle remove item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $cartItemID = $_POST['cartItemID'];
    $pdo->prepare("DELETE FROM tblCartItems WHERE cartItemID = :id")
        ->execute(['id' => $cartItemID]);
    header('Location: cart.php');
    exit();
}

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    try {
        // Get order type from form submission
        $orderType = $_POST['orderType'] ?? 'pickup';

        // Get cart
        $stmt = $pdo->prepare("SELECT cartID FROM tblCart WHERE userID = :userID");
        $stmt->execute(['userID' => $userID]);
        $cart = $stmt->fetch();

        if ($cart) {
            // Get all cart items
            $stmt = $pdo->prepare("
                SELECT tblCartItems.*, tblItems.itemPrice, tblItems.itemName 
                FROM tblCartItems 
                JOIN tblItems ON tblCartItems.itemID = tblItems.itemID 
                WHERE tblCartItems.cartID = :cartID
            ");
            $stmt->execute(['cartID' => $cart['cartID']]);
            $cartItems = $stmt->fetchAll();

            // Calculate total
            $total = array_sum(array_map(fn($i) => $i['itemPrice'] * $i['quantity'], $cartItems));

            // Insert into tblOrders with the new orderType column
            $pdo->prepare("INSERT INTO tblOrders (userID, totalAmount, orderDate, status, orderType) VALUES (:userID, :total, NOW(), 'pending', :orderType)")
                ->execute([
                    'userID' => $userID,
                    'total' => $total,
                    'orderType' => $orderType
                ]);
            $orderID = $pdo->lastInsertId();

            // Insert order items + deduct stock + increment reorderLevel
            foreach ($cartItems as $item) {
                $pdo->prepare("INSERT INTO tblOrderItems (orderID, itemID, quantity, price) VALUES (:orderID, :itemID, :quantity, :price)")
                    ->execute([
                        'orderID'  => $orderID,
                        'itemID'   => $item['itemID'],
                        'quantity' => $item['quantity'],
                        'price'    => $item['itemPrice']
                    ]);

                // Deduct stock
                $pdo->prepare("UPDATE tblItems SET itemQuantity = itemQuantity - :qty WHERE itemID = :itemID")
                    ->execute(['qty' => $item['quantity'], 'itemID' => $item['itemID']]);

                // Increment reorderLevel by 1
                $pdo->prepare("UPDATE tblItems SET reorderLevel = reorderLevel + 1 WHERE itemID = :itemID")
                    ->execute(['itemID' => $item['itemID']]);
            }

            // Generate bill number with duplicate check
            $billDate  = date('Y-m-d');
            $dueDate   = date('Y-m-d', strtotime('+15 days'));
            $yearMonth = date('Y-m');

            $billNumber = '';
            $attempt = 1;
            do {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(MAX(CAST(SUBSTRING(billNumber, -3) AS UNSIGNED)), 0) + :attempt as nextNum
                    FROM tblBills 
                    WHERE billNumber LIKE :prefix
                ");
                $stmt->execute(['prefix' => 'BILL-' . $yearMonth . '%', 'attempt' => $attempt]);
                $nextNum    = $stmt->fetchColumn();
                $billNumber = 'BILL-' . $yearMonth . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

                // Check if this bill number already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tblBills WHERE billNumber = :billNumber");
                $stmt->execute(['billNumber' => $billNumber]);
                $exists = $stmt->fetchColumn() > 0;

                $attempt++;
            } while ($exists && $attempt <= 100); // Safety limit

            if ($exists) {
                throw new Exception('Could not generate unique bill number');
            }

            // Insert into tblBills
            $pdo->prepare("INSERT INTO tblBills (userID, billNumber, billDate, dueDate, totalAmount, status) VALUES (:userID, :billNumber, :billDate, :dueDate, :total, 'unpaid')")
                ->execute([
                    'userID'     => $userID,
                    'billNumber' => $billNumber,
                    'billDate'   => $billDate,
                    'dueDate'    => $dueDate,
                    'total'      => $total
                ]);
            $billID = $pdo->lastInsertId();

            // Link bill to order
            $pdo->prepare("INSERT INTO tblBillOrders (billID, orderID) VALUES (:billID, :orderID)")
                ->execute(['billID' => $billID, 'orderID' => $orderID]);

            // Clear cart
            $pdo->prepare("DELETE FROM tblCartItems WHERE cartID = :cartID")
                ->execute(['cartID' => $cart['cartID']]);

            // Redirect to bill preview
            header('Location: bill.php?billID=' . $billID);
            exit();
        }
    } catch (PDOException $e) {
        $message     = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Fetch cart items
$cartItems = [];
$total = 0;
$stmt = $pdo->prepare("SELECT cartID FROM tblCart WHERE userID = :userID");
$stmt->execute(['userID' => $userID]);
$cart = $stmt->fetch();

if ($cart) {
    $stmt = $pdo->prepare("
        SELECT tblCartItems.cartItemID, tblCartItems.quantity, 
               tblItems.itemID, tblItems.itemName, tblItems.itemDescription, 
               tblItems.itemPrice, tblItems.itemUnit, tblItems.itemImage
        FROM tblCartItems
        JOIN tblItems ON tblCartItems.itemID = tblItems.itemID
        WHERE tblCartItems.cartID = :cartID
    ");
    $stmt->execute(['cartID' => $cart['cartID']]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = array_sum(array_map(fn($i) => $i['itemPrice'] * $i['quantity'], $cartItems));
}
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Cart</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <header class="sticky-top">
        <nav class="navbar navbar-expand-sm navbar-dark bg-success">
            <div class="w-75 container-lg">
                <a class="navbar-brand me-auto" href="#">
                    <img
                        src="fresh-track.png"
                        alt="FreshTrack"
                        class="img-fluid d-block w-auto z-1 mt-2 mx-5" />
                </a>
                <button
                    class="navbar-toggler p-4"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapsibleNavId"
                    aria-controls="collapsibleNavId"
                    aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="collapsibleNavId">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="shop.php" aria-current="page">
                                Shop
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="cart.php">Cart</a>
                            <span class="visually-hidden">(current)</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="hotel_orders.php">Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bill.php">Transactions</a>
                        </li>
                        <li class="nav-item dropdown d-flex align-items-center mx-3">
                            <img src="user-icon.svg" alt="user-icon" width="35">
                            <a
                                class="nav-link dropdown-toggle"
                                href="#"
                                id="dropdownId"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
                                <?php echo $_SESSION['username'] ?? 'Guest'; ?>
                            </a>
                            <div class="dropdown-menu" aria-labelledby="dropdownId">
                                <a class="dropdown-item btn btn-danger" href="index.php">Log Out</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main>
        <div class="container-lg mt-5">
            <h2>Your Cart</h2>
            <p>Review your items before checking out.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($cartItems)): ?>
                <div class="text-center py-5">
                    <h4 class="text-muted">Your cart is empty.</h4>
                    <a href="shop.php" class="btn btn-success mt-3">Browse Shop</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Item</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <img src="<?php echo htmlspecialchars($item['itemImage'] ?? 'placeholder.png'); ?>"
                                                            alt="<?php echo htmlspecialchars($item['itemName']); ?>"
                                                            style="width:50px; height:50px; object-fit:cover;" class="rounded">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($item['itemName']); ?></strong>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($item['itemDescription']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>₱<?php echo number_format($item['itemPrice'], 2); ?></td>
                                                <td><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['itemUnit']); ?></td>
                                                <td><strong>₱<?php echo number_format($item['itemPrice'] * $item['quantity'], 2); ?></strong></td>
                                                <td>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="remove">
                                                        <input type="hidden" name="cartItemID" value="<?php echo $item['cartItemID']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-success-subtle">
                                <h5 class="mb-0">Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Items (<?php echo count($cartItems); ?>)</span>
                                    <span>₱<?php echo number_format($total, 2); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-3">
                                    <strong>Total</strong>
                                    <strong>₱<?php echo number_format($total, 2); ?></strong>
                                </div>

                                <form method="POST">
                                    <input type="hidden" name="action" value="checkout">

                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Order Type</label>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="orderType" id="pickup" value="pickup" checked required>
                                            <label class="form-check-label" for="pickup">
                                                Pickup
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="orderType" id="delivery" value="delivery" required>
                                            <label class="form-check-label" for="delivery">
                                                Delivery
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-success w-100">Checkout</button>
                                </form>
                                <a href="shop.php" class="btn btn-outline-secondary w-100 mt-2">Continue Shopping</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="script.js"></script>
</body>

</html>