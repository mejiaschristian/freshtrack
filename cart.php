<?php
session_start();
include 'db.php';
require 'auth.php';
require 'functions.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$message = "";
$messageType = "";

// ── Remove cart item ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $cartItemID = $_POST['cartItemID'];
    $pdo->prepare("DELETE FROM tblCartItems WHERE cartItemID = :id")
        ->execute(['id' => $cartItemID]);
    header('Location: cart.php');
    exit();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function calcDeliveryDate(string $orderType, string $timeSlot): array
{
    $today = new DateTime();
    $cutoff = new DateTime('today 10:00');

    if ($orderType === 'pickup') {
        return [
            'date'      => $today->format('Y-m-d'),
            'label'     => $today->format('M d, Y') . ' (Today – Pickup)',
            'timeRange' => $timeSlot === 'morning' ? '8:00 AM – 12:00 PM' : ($timeSlot === 'afternoon' ? '1:00 PM – 5:00 PM' : '6:00 PM – 8:00 PM'),
        ];
    }

    if ($today < $cutoff) {
        $deliveryDate = clone $today;
    } else {
        $deliveryDate = new DateTime('tomorrow');
    }

    if ($deliveryDate->format('N') == 7) {
        $deliveryDate->modify('+1 day');
    }

    $timeRange = match ($timeSlot) {
        'morning'   => '8:00 AM – 12:00 PM',
        'afternoon' => '1:00 PM – 5:00 PM',
        'evening'   => '6:00 PM – 8:00 PM',
        default     => '8:00 AM – 12:00 PM'
    };

    return [
        'date'      => $deliveryDate->format('Y-m-d'),
        'label'     => $deliveryDate->format('M d, Y'),
        'timeRange' => $timeRange,
    ];
}

function calcNextRecurringDate(string $frequency, string $firstDate): string
{
    $d = new DateTime($firstDate);
    if ($frequency === 'weekly') {
        $d->modify('+7 days');
    } else {
        $d->modify('+1 month');
    }
    return $d->format('Y-m-d');
}

// ── One-time checkout ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    try {
        $orderType  = $_POST['orderType']  ?? 'pickup';
        $timeSlot   = $_POST['timeSlot']   ?? 'morning';
        $schedule   = calcDeliveryDate($orderType, $timeSlot);

        $stmt = $pdo->prepare("SELECT cartID FROM tblCart WHERE userID = :userID");
        $stmt->execute(['userID' => $userID]);
        $cart = $stmt->fetch();

        if ($cart) {
            $stmt = $pdo->prepare("
                SELECT tblCartItems.*, tblItems.itemPrice, tblItems.itemName
                FROM tblCartItems
                JOIN tblItems ON tblCartItems.itemID = tblItems.itemID
                WHERE tblCartItems.cartID = :cartID
            ");
            $stmt->execute(['cartID' => $cart['cartID']]);
            $cartItems = $stmt->fetchAll();

            $total = array_sum(array_map(fn($i) => $i['itemPrice'] * $i['quantity'], $cartItems));

            $pdo->beginTransaction();

            $pdo->prepare("
                INSERT INTO tblOrders
                  (userID, totalAmount, orderDate, status, orderType, deliveryDate, deliveryTimeSlot, estimatedDelivery)
                VALUES (:userID, :total, NOW(), 'pending', :orderType, :deliveryDate, :timeSlot, :estimated)
            ")->execute([
                'userID'      => $userID,
                'total'       => $total,
                'orderType'   => $orderType,
                'deliveryDate' => $schedule['date'],
                'timeSlot'    => $timeSlot,
                'estimated'   => $schedule['label'] . ', ' . $schedule['timeRange'],
            ]);
            $orderID = $pdo->lastInsertId();

            foreach ($cartItems as $item) {
                // 1. Figure out the batches FIRST
                $batchStmt = $pdo->prepare("
                    SELECT batchID, quantity 
                    FROM tblItemBatches 
                    WHERE itemID = :itemID AND quantity > 0 
                    ORDER BY harvestDate ASC, batchID ASC
                ");
                $batchStmt->execute(['itemID' => $item['itemID']]);
                $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

                $remaining = $item['quantity'];

                // 2. Loop through the batches, deduct stock, AND insert the order item simultaneously
                foreach ($batches as $batch) {
                    if ($remaining <= 0) break;
                    $deduct = min($remaining, (int)$batch['quantity']);

                    // Deduct from the specific batch
                    $pdo->prepare("UPDATE tblItemBatches SET quantity = quantity - :deduct WHERE batchID = :batchID")
                        ->execute(['deduct' => $deduct, 'batchID' => $batch['batchID']]);

                    // Insert into Order Items using the specific batchID
                    $pdo->prepare("INSERT INTO tblOrderItems (orderID, itemID, quantity, price, batchID) VALUES (:orderID, :itemID, :quantity, :price, :batchID)")
                        ->execute([
                            'orderID'  => $orderID,
                            'itemID'   => $item['itemID'],
                            'quantity' => $deduct,
                            'price'    => $item['itemPrice'],
                            'batchID'  => $batch['batchID']
                        ]);

                    $remaining -= $deduct;
                }

                // Sync global inventory quantities and soonest FIFO expiry date
                $syncStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(quantity), 0) AS totalQty, 
                           MIN(CASE WHEN quantity > 0 THEN expiryDate END) AS fifoExpiry 
                    FROM tblItemBatches 
                    WHERE itemID = :itemID
                ");
                $syncStmt->execute(['itemID' => $item['itemID']]);
                $sync = $syncStmt->fetch(PDO::FETCH_ASSOC);

                $pdo->prepare("
                    UPDATE tblItems 
                    SET itemQuantity = :qty, itemExpiryDate = :exp
                    WHERE itemID = :itemID
                ")->execute([
                    'qty'    => $sync['totalQty'],
                    'exp'    => $sync['fifoExpiry'] ?? date('Y-m-d'),
                    'itemID' => $item['itemID']
                ]);
            }

            $bill = createBillForOrder($pdo, $orderID);
            $billID = $bill['billID'];

            $pdo->prepare("DELETE FROM tblCartItems WHERE cartID = :cartID")
                ->execute(['cartID' => $cart['cartID']]);

            $pdo->commit();

            header('Location: bill.php?billID=' . $billID);
            exit();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message     = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// ── Set up recurring order ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup_recurring') {
    try {
        $orderType  = $_POST['orderType']  ?? 'delivery';
        $frequency  = $_POST['frequency']  ?? 'weekly';
        $timeSlot   = $_POST['timeSlot']   ?? 'morning';
        $schedule   = calcDeliveryDate($orderType, $timeSlot);

        $stmt = $pdo->prepare("SELECT cartID FROM tblCart WHERE userID = :userID");
        $stmt->execute(['userID' => $userID]);
        $cart = $stmt->fetch();

        if (!$cart) {
            throw new Exception("No cart found.");
        }

        $stmt = $pdo->prepare("
            SELECT tblCartItems.*, tblItems.itemPrice, tblItems.itemName
            FROM tblCartItems
            JOIN tblItems ON tblCartItems.itemID = tblItems.itemID
            WHERE tblCartItems.cartID = :cartID
        ");
        $stmt->execute(['cartID' => $cart['cartID']]);
        $cartItems = $stmt->fetchAll();

        if (empty($cartItems)) {
            throw new Exception("Cart is empty.");
        }

        $pdo->beginTransaction();

        // 1. Create primary parent blueprint record
        $pdo->prepare("
            INSERT INTO tblRecurringOrders (userID, frequency, orderType, deliveryTimeSlot, nextDeliveryDate, status)
            VALUES (:userID, :frequency, :orderType, :timeSlot, :nextDate, 'active')
        ")->execute([
            'userID'    => $userID,
            'frequency' => $frequency,
            'orderType' => $orderType,
            'timeSlot'  => $timeSlot,
            'nextDate'  => $schedule['date'],
        ]);
        $recurringID = $pdo->lastInsertId(); // Captured safely first!

        $total = array_sum(array_map(fn($i) => $i['itemPrice'] * $i['quantity'], $cartItems));

        // 2. Generate active delivery instance tracking back to recurring model template
        $pdo->prepare("
            INSERT INTO tblOrders (userID, totalAmount, orderDate, status, orderType, deliveryDate, deliveryTimeSlot, estimatedDelivery, recurringOrderID)
            VALUES (:userID, :total, NOW(), 'pending', :orderType, :deliveryDate, :timeSlot, :estimated, :recurringID)
        ")->execute([
            'userID'       => $userID,
            'total'        => $total,
            'orderType'    => $orderType,
            'deliveryDate' => $schedule['date'],
            'timeSlot'     => $timeSlot,
            'estimated'    => $schedule['label'] . ', ' . $schedule['timeRange'],
            'recurringID'  => $recurringID,
        ]);
        $orderID = $pdo->lastInsertId(); // Captured safely second!

        // 3. Process loops for BOTH tblOrderItems and tblRecurringOrderItems
        foreach ($cartItems as $item) {
            // 1. Figure out the batches FIRST
            $batchStmt = $pdo->prepare("
                    SELECT batchID, quantity 
                    FROM tblItemBatches 
                    WHERE itemID = :itemID AND quantity > 0 
                    ORDER BY harvestDate ASC, batchID ASC
                ");
            $batchStmt->execute(['itemID' => $item['itemID']]);
            $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

            $remaining = $item['quantity'];

            // 2. Loop through the batches, deduct stock, AND insert the order item simultaneously
            foreach ($batches as $batch) {
                if ($remaining <= 0) break;
                $deduct = min($remaining, (int)$batch['quantity']);

                // Deduct from the specific batch
                $pdo->prepare("UPDATE tblItemBatches SET quantity = quantity - :deduct WHERE batchID = :batchID")
                    ->execute(['deduct' => $deduct, 'batchID' => $batch['batchID']]);

                // Insert into Order Items using the specific batchID
                $pdo->prepare("INSERT INTO tblOrderItems (orderID, itemID, quantity, price, batchID) VALUES (:orderID, :itemID, :quantity, :price, :batchID)")
                    ->execute([
                        'orderID'  => $orderID,
                        'itemID'   => $item['itemID'],
                        'quantity' => $deduct,
                        'price'    => $item['itemPrice'],
                        'batchID'  => $batch['batchID']
                    ]);

                $remaining -= $deduct;
            }

            // POPULATE DISK TO PREVENT BLANK DETAILS IN recurring_orders.php
            $pdo->prepare("INSERT INTO tblRecurringOrderItems (recurringID, itemID, quantity) VALUES (:recurringID, :itemID, :quantity)")
                ->execute([
                    'recurringID' => $recurringID,
                    'itemID'      => $item['itemID'],
                    'quantity'    => $item['quantity'],
                ]);

            // Synchronize system global master stock balance counts
            $syncStmt = $pdo->prepare("
                SELECT COALESCE(SUM(quantity), 0) AS totalQty, 
                    MIN(CASE WHEN quantity > 0 THEN expiryDate END) AS fifoExpiry 
                FROM tblItemBatches 
                WHERE itemID = :itemID
            ");
            $syncStmt->execute(['itemID' => $item['itemID']]);
            $sync = $syncStmt->fetch(PDO::FETCH_ASSOC);

            $pdo->prepare("
                UPDATE tblItems 
                SET itemQuantity = :qty, itemExpiryDate = :exp 
                WHERE itemID = :itemID
            ")->execute([
                'qty'    => $sync['totalQty'],
                'exp'    => $sync['fifoExpiry'] ?? date('Y-m-d'),
                'itemID' => $item['itemID']
            ]);
        }

        // 4. Update core schedule tracker milestone
        $nextNext = calcNextRecurringDate($frequency, $schedule['date']);
        $pdo->prepare("UPDATE tblRecurringOrders SET nextDeliveryDate = :d WHERE recurringID = :id")
            ->execute(['d' => $nextNext, 'id' => $recurringID]);

        // Empty current user session cart space
        $pdo->prepare("DELETE FROM tblCartItems WHERE cartID = :cartID")
            ->execute(['cartID' => $cart['cartID']]);

        $pdo->commit();

        header('Location: recurring_orders.php?setup=1');
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message     = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// ── Fetch cart items ──────────────────────────────────────────────────────────
$cartItems = [];
$total     = 0;
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <header class="sticky-top">
        <nav class="navbar navbar-expand-lg navbar-dark bg-success">
            <div class="container-lg">
                <a class="navbar-brand me-auto" href="shop.php">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid" />
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavId">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="collapsibleNavId">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                        <li class="nav-item"><a class="nav-link active" href="cart.php">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="hotel_orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="recurring_orders.php">Recurring</a></li>
                        <li class="nav-item"><a class="nav-link" href="bill.php">Transactions</a></li>
                        <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 border-start border-1 ms-3 px-3"
                                href="#" id="dropdownId" data-bs-toggle="dropdown">
                                <img src="user-icon.svg" alt="user-icon" width="35">
                                <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="index.php">Log Out</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="modal fade" id="checkoutConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="confirmModalTitle">Confirm Checkout</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="confirmRecurringBadge" class="mb-3 d-none text-center">
                        <span class="recurring-badge">🔁 Recurring Order</span>
                    </div>
                    <p class="mb-2"><strong>Order Summary:</strong></p>
                    <div class="border-top border-bottom py-3 mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Items:</span><span id="confirmItemCount">0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total:</span><span id="confirmTotal">₱0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Order Type:</span><span id="confirmOrderType">—</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2" id="confirmFrequencyRow" style="display:none!important">
                            <span>Frequency:</span><span id="confirmFrequency">—</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Delivery Date:</span><span id="confirmDeliveryDate">—</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Time Slot:</span><span id="confirmTimeSlot">—</span>
                        </div>
                    </div>
                    <div class="delivery-estimate-box" id="confirmEstimateBox">
                        <div class="d-flex align-items-center gap-2">
                            <span style="font-size:1.4rem">🚚</span>
                            <div>
                                <div class="fw-bold text-success small">Estimated Delivery</div>
                                <div id="confirmEstimateText" class="small text-muted"></div>
                            </div>
                        </div>
                    </div>
                    <p class="alert alert-info small mt-3 mb-0">
                        💡 A bill will be generated after checkout. You can track your order in the Orders section.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmCheckoutBtn">Confirm Order</button>
                </div>
            </div>
        </div>
    </div>

    <main>
        <div class="container-lg mt-5">
            <h2 class="mb-1">Your Cart</h2>
            <p class="text-muted">Review your items before checking out.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
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
                    <div class="col-lg-7">
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
                                                            style="width:50px;height:50px;object-fit:cover;" class="rounded">
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

                                <ul class="nav checkout-tabs nav-tabs mb-0" id="checkoutTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="once-tab" data-bs-toggle="tab" data-bs-target="#once-pane" type="button">
                                            One-Time
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="recurring-tab" data-bs-toggle="tab" data-bs-target="#recurring-pane" type="button">
                                            🔁 Recurring
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="once-pane">
                                        <form method="POST" id="checkoutForm"
                                            data-item-count="<?php echo count($cartItems); ?>"
                                            data-total="<?php echo number_format($total, 2); ?>">
                                            <input type="hidden" name="action" value="checkout">
                                            <?php include '_checkout_fields.php'; ?>
                                            <button type="button" class="btn btn-success w-100 mt-3"
                                                onclick="showCheckoutConfirm('once')">Checkout</button>
                                        </form>
                                    </div>

                                    <div class="tab-pane fade" id="recurring-pane">
                                        <form method="POST" id="recurringForm"
                                            data-item-count="<?php echo count($cartItems); ?>"
                                            data-total="<?php echo number_format($total, 2); ?>">
                                            <input type="hidden" name="action" value="setup_recurring">

                                            <div class="mb-3 mt-2">
                                                <label class="form-label fw-bold">Delivery Frequency</label>
                                                <div class="d-flex gap-2">
                                                    <label class="order-type-card flex-fill text-center">
                                                        <input class="d-none" type="radio" name="frequency" value="weekly" checked>
                                                        <div class="fw-bold">Weekly</div>
                                                        <small class="text-muted">Every 7 days</small>
                                                    </label>
                                                    <label class="order-type-card flex-fill text-center">
                                                        <input class="d-none" type="radio" name="frequency" value="monthly">
                                                        <div class="fw-bold">Monthly</div>
                                                        <small class="text-muted">Every 30 days</small>
                                                    </label>
                                                </div>
                                            </div>

                                            <?php include '_checkout_fields.php'; ?>

                                            <div class="alert alert-success-subtle border border-success-subtle rounded mt-3 small">
                                                🔁 Your first delivery processes immediately. Subsequent orders are created automatically on schedule.
                                            </div>

                                            <button type="button" class="btn btn-success w-100 mt-1"
                                                onclick="showCheckoutConfirm('recurring')">Set Up Recurring Order</button>
                                        </form>
                                    </div>
                                </div>

                                <a href="shop.php" class="btn btn-outline-secondary w-100 mt-2">Continue Shopping</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
    <script>
        function calcDeliveryEstimate(orderType, timeSlot) {
            const now = new Date();
            const cutoff = new Date();
            cutoff.setHours(10, 0, 0, 0);

            const timeRanges = {
                morning: '8:00 AM – 12:00 PM',
                afternoon: '1:00 PM – 5:00 PM',
                evening: '6:00 PM – 8:00 PM'
            };

            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

            let deliveryDate;
            if (orderType === 'pickup') {
                deliveryDate = new Date(now);
                return `Today (Pickup) – ${timeRanges[timeSlot] || timeRanges.morning}`;
            } else {
                deliveryDate = (now < cutoff) ? new Date(now) : (() => {
                    const d = new Date(now);
                    d.setDate(d.getDate() + 1);
                    return d;
                })();
                if (deliveryDate.getDay() === 0) deliveryDate.setDate(deliveryDate.getDate() + 1);
            }

            const label = `${days[deliveryDate.getDay()]}., ${months[deliveryDate.getMonth()]} ${deliveryDate.getDate()}`;
            return `${label} – ${timeRanges[timeSlot] || timeRanges.morning}`;
        }

        function getFormValues(formID) {
            const form = document.getElementById(formID);
            const orderType = form.querySelector('input[name="orderType"]:checked')?.value || 'pickup';
            const timeSlot = form.querySelector('input[name="timeSlot"]:checked')?.value || 'morning';
            const frequency = form.querySelector('input[name="frequency"]:checked')?.value || null;
            return {
                orderType,
                timeSlot,
                frequency
            };
        }

        let pendingFormID = null;

        function showCheckoutConfirm(type) {
            pendingFormID = type === 'once' ? 'checkoutForm' : 'recurringForm';
            const form = document.getElementById(pendingFormID);
            const {
                orderType,
                timeSlot,
                frequency
            } = getFormValues(pendingFormID);

            const isRecurring = type === 'recurring';
            document.getElementById('confirmModalTitle').textContent = isRecurring ? 'Confirm Recurring Order' : 'Confirm Checkout';
            document.getElementById('confirmRecurringBadge').classList.toggle('d-none', !isRecurring);
            document.getElementById('confirmFrequencyRow').style.display = isRecurring ? 'flex' : 'none';
            document.getElementById('confirmFrequency').textContent = isRecurring ? (frequency === 'weekly' ? 'Every Week' : 'Every Month') : '';

            document.getElementById('confirmItemCount').textContent = form.dataset.itemCount;
            document.getElementById('confirmTotal').textContent = '₱' + form.dataset.total;
            document.getElementById('confirmOrderType').textContent = orderType.charAt(0).toUpperCase() + orderType.slice(1);

            const timeLabels = {
                morning: 'Morning (8 AM–12 PM)',
                afternoon: 'Afternoon (1–5 PM)',
                evening: 'Evening (6–8 PM)'
            };
            document.getElementById('confirmTimeSlot').textContent = timeLabels[timeSlot] || timeSlot;

            const estimate = calcDeliveryEstimate(orderType, timeSlot);
            document.getElementById('confirmDeliveryDate').textContent = estimate.split(' – ')[0];
            document.getElementById('confirmEstimateText').textContent = estimate;

            new bootstrap.Modal(document.getElementById('checkoutConfirmModal')).show();
        }

        document.getElementById('confirmCheckoutBtn').addEventListener('click', function() {
            if (pendingFormID) document.getElementById(pendingFormID).submit();
        });

        document.addEventListener('change', function(e) {
            if (e.target.name === 'orderType' || e.target.name === 'timeSlot') {
                ['checkoutForm', 'recurringForm'].forEach(function(fid) {
                    const form = document.getElementById(fid);
                    if (!form) return;
                    const {
                        orderType,
                        timeSlot
                    } = getFormValues(fid);
                    const el = form.querySelector('.estimate-preview');
                    if (el) el.textContent = calcDeliveryEstimate(orderType, timeSlot);
                });
            }
        });
    </script>
</body>

</html>