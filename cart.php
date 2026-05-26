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

// ── Remove cart item ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $cartItemID = $_POST['cartItemID'];
    $pdo->prepare("DELETE FROM tblCartItems WHERE cartItemID = :id")
        ->execute(['id' => $cartItemID]);
    header('Location: cart.php');
    exit();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function calcDeliveryDate(string $orderType, string $timeSlot): array {
    $today = new DateTime();
    $cutoff = new DateTime('today 10:00');

    if ($orderType === 'pickup') {
        // Pickup is always same-day
        return [
            'date'      => $today->format('Y-m-d'),
            'label'     => $today->format('M d, Y') . ' (Today – Pickup)',
            'timeRange' => $timeSlot === 'morning' ? '8:00 AM – 12:00 PM' : ($timeSlot === 'afternoon' ? '1:00 PM – 5:00 PM' : '6:00 PM – 8:00 PM'),
        ];
    }

    // Delivery: same-day only if order placed before 10 AM; else next day (1–3 day window)
    if ($today < $cutoff) {
        $deliveryDate = clone $today;
    } else {
        $deliveryDate = new DateTime('tomorrow');
    }

    // Skip Sundays
    if ($deliveryDate->format('N') == 7) {
        $deliveryDate->modify('+1 day');
    }

    $timeRange = match($timeSlot) {
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

function calcNextRecurringDate(string $frequency, string $firstDate): string {
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

            $pdo->prepare("
                INSERT INTO tblOrders
                  (userID, totalAmount, orderDate, status, orderType, deliveryDate, deliveryTimeSlot, estimatedDelivery)
                VALUES (:userID, :total, NOW(), 'pending', :orderType, :deliveryDate, :timeSlot, :estimated)
            ")->execute([
                'userID'      => $userID,
                'total'       => $total,
                'orderType'   => $orderType,
                'deliveryDate'=> $schedule['date'],
                'timeSlot'    => $timeSlot,
                'estimated'   => $schedule['label'] . ', ' . $schedule['timeRange'],
            ]);
            $orderID = $pdo->lastInsertId();

            foreach ($cartItems as $item) {
                $pdo->prepare("INSERT INTO tblOrderItems (orderID, itemID, quantity, price) VALUES (:orderID, :itemID, :quantity, :price)")
                    ->execute(['orderID' => $orderID, 'itemID' => $item['itemID'], 'quantity' => $item['quantity'], 'price' => $item['itemPrice']]);

                $pdo->prepare("UPDATE tblItems SET itemQuantity = itemQuantity - :qty WHERE itemID = :itemID")
                    ->execute(['qty' => $item['quantity'], 'itemID' => $item['itemID']]);
                $pdo->prepare("UPDATE tblItems SET reorderLevel = reorderLevel + 1 WHERE itemID = :itemID")
                    ->execute(['itemID' => $item['itemID']]);
            }

            // Bill number
            $yearMonth  = date('Y-m');
            $billNumber = '';
            $attempt    = 1;
            do {
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(billNumber, -3) AS UNSIGNED)), 0) + :attempt as nextNum FROM tblBills WHERE billNumber LIKE :prefix");
                $stmt->execute(['prefix' => 'BILL-' . $yearMonth . '%', 'attempt' => $attempt]);
                $nextNum    = $stmt->fetchColumn();
                $billNumber = 'BILL-' . $yearMonth . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                $stmt       = $pdo->prepare("SELECT COUNT(*) FROM tblBills WHERE billNumber = :billNumber");
                $stmt->execute(['billNumber' => $billNumber]);
                $exists     = $stmt->fetchColumn() > 0;
                $attempt++;
            } while ($exists && $attempt <= 100);

            $pdo->prepare("INSERT INTO tblBills (userID, billNumber, billDate, dueDate, totalAmount, status) VALUES (:userID, :billNumber, NOW(), :dueDate, :total, 'unpaid')")
                ->execute(['userID' => $userID, 'billNumber' => $billNumber, 'dueDate' => date('Y-m-d', strtotime('+15 days')), 'total' => $total]);
            $billID = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO tblBillOrders (billID, orderID) VALUES (:billID, :orderID)")
                ->execute(['billID' => $billID, 'orderID' => $orderID]);

            $pdo->prepare("DELETE FROM tblCartItems WHERE cartID = :cartID")
                ->execute(['cartID' => $cart['cartID']]);

            header('Location: bill.php?billID=' . $billID);
            exit();
        }
    } catch (PDOException $e) {
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

        // Create recurring template
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
        $recurringID = $pdo->lastInsertId();

        // Save template items
        foreach ($cartItems as $item) {
            $pdo->prepare("INSERT INTO tblRecurringOrderItems (recurringID, itemID, quantity) VALUES (:recurringID, :itemID, :quantity)")
                ->execute(['recurringID' => $recurringID, 'itemID' => $item['itemID'], 'quantity' => $item['quantity']]);
        }

        // Process FIRST delivery immediately
        $total = array_sum(array_map(fn($i) => $i['itemPrice'] * $i['quantity'], $cartItems));

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
        $orderID = $pdo->lastInsertId();

        foreach ($cartItems as $item) {
            $pdo->prepare("INSERT INTO tblOrderItems (orderID, itemID, quantity, price) VALUES (:orderID, :itemID, :quantity, :price)")
                ->execute(['orderID' => $orderID, 'itemID' => $item['itemID'], 'quantity' => $item['quantity'], 'price' => $item['itemPrice']]);

            $pdo->prepare("UPDATE tblItems SET itemQuantity = itemQuantity - :qty WHERE itemID = :itemID")
                ->execute(['qty' => $item['quantity'], 'itemID' => $item['itemID']]);
        }

        // Update nextDeliveryDate to NEXT cycle
        $nextNext = calcNextRecurringDate($frequency, $schedule['date']);
        $pdo->prepare("UPDATE tblRecurringOrders SET nextDeliveryDate = :d WHERE recurringID = :id")
            ->execute(['d' => $nextNext, 'id' => $recurringID]);

        // Clear cart
        $pdo->prepare("DELETE FROM tblCartItems WHERE cartID = :cartID")
            ->execute(['cartID' => $cart['cartID']]);

        header('Location: recurring_orders.php?setup=1');
        exit();
    } catch (Exception $e) {
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
    <style>
        .checkout-tabs .nav-link { border-radius: 8px 8px 0 0; font-weight: 500; }
        .checkout-tabs .nav-link.active { background: #198754; color: #fff; }
        .tab-content { border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px; padding: 1.25rem; }
        .delivery-estimate-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; }
        .recurring-badge { background: linear-gradient(135deg, #16a34a, #15803d); color: white; border-radius: 20px; padding: 2px 12px; font-size: 0.78rem; font-weight: 600; }
        .time-slot-card { border: 2px solid #dee2e6; border-radius: 8px; padding: 0.75rem; cursor: pointer; transition: all 0.2s; }
        .time-slot-card:has(input:checked) { border-color: #198754; background: #f0fdf4; }
        .order-type-card { border: 2px solid #dee2e6; border-radius: 8px; padding: 0.75rem 1rem; cursor: pointer; transition: all 0.2s; }
        .order-type-card:has(input:checked) { border-color: #198754; background: #f0fdf4; }
    </style>
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

<!-- Checkout Confirm Modal -->
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
                <!-- Cart Items -->
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

                <!-- Order Summary & Checkout -->
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

                            <!-- Checkout Tabs -->
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
                                <!-- ONE-TIME TAB -->
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

                                <!-- RECURRING TAB -->
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
// Delivery estimate calculator (mirrors PHP logic)
function calcDeliveryEstimate(orderType, timeSlot) {
    const now   = new Date();
    const cutoff = new Date();
    cutoff.setHours(10, 0, 0, 0);

    const timeRanges = {
        morning:   '8:00 AM – 12:00 PM',
        afternoon: '1:00 PM – 5:00 PM',
        evening:   '6:00 PM – 8:00 PM'
    };

    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const days   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

    let deliveryDate;
    if (orderType === 'pickup') {
        deliveryDate = new Date(now);
        return `Today (Pickup) – ${timeRanges[timeSlot] || timeRanges.morning}`;
    } else {
        deliveryDate = (now < cutoff) ? new Date(now) : (() => { const d = new Date(now); d.setDate(d.getDate()+1); return d; })();
        if (deliveryDate.getDay() === 0) deliveryDate.setDate(deliveryDate.getDate()+1);
    }

    const label = `${days[deliveryDate.getDay()]}, ${months[deliveryDate.getMonth()]} ${deliveryDate.getDate()}`;
    return `${label} – ${timeRanges[timeSlot] || timeRanges.morning}`;
}

function getFormValues(formID) {
    const form     = document.getElementById(formID);
    const orderType = form.querySelector('input[name="orderType"]:checked')?.value || 'pickup';
    const timeSlot  = form.querySelector('input[name="timeSlot"]:checked')?.value  || 'morning';
    const frequency = form.querySelector('input[name="frequency"]:checked')?.value || null;
    return { orderType, timeSlot, frequency };
}

let pendingFormID = null;

function showCheckoutConfirm(type) {
    pendingFormID = type === 'once' ? 'checkoutForm' : 'recurringForm';
    const form    = document.getElementById(pendingFormID);
    const { orderType, timeSlot, frequency } = getFormValues(pendingFormID);

    const isRecurring = type === 'recurring';
    document.getElementById('confirmModalTitle').textContent = isRecurring ? 'Confirm Recurring Order' : 'Confirm Checkout';
    document.getElementById('confirmRecurringBadge').classList.toggle('d-none', !isRecurring);
    document.getElementById('confirmFrequencyRow').style.display = isRecurring ? 'flex' : 'none';
    document.getElementById('confirmFrequency').textContent  = isRecurring ? (frequency === 'weekly' ? 'Every Week' : 'Every Month') : '';

    document.getElementById('confirmItemCount').textContent  = form.dataset.itemCount;
    document.getElementById('confirmTotal').textContent      = '₱' + form.dataset.total;
    document.getElementById('confirmOrderType').textContent  = orderType.charAt(0).toUpperCase() + orderType.slice(1);

    const timeLabels = { morning: 'Morning (8 AM–12 PM)', afternoon: 'Afternoon (1–5 PM)', evening: 'Evening (6–8 PM)' };
    document.getElementById('confirmTimeSlot').textContent = timeLabels[timeSlot] || timeSlot;

    const estimate = calcDeliveryEstimate(orderType, timeSlot);
    document.getElementById('confirmDeliveryDate').textContent = estimate.split(' – ')[0];
    document.getElementById('confirmEstimateText').textContent  = estimate;

    new bootstrap.Modal(document.getElementById('checkoutConfirmModal')).show();
}

document.getElementById('confirmCheckoutBtn').addEventListener('click', function() {
    if (pendingFormID) document.getElementById(pendingFormID).submit();
});

// Live estimate update
document.addEventListener('change', function(e) {
    if (e.target.name === 'orderType' || e.target.name === 'timeSlot') {
        // Update both forms' estimate previews if present
        ['checkoutForm', 'recurringForm'].forEach(function(fid) {
            const form = document.getElementById(fid);
            if (!form) return;
            const { orderType, timeSlot } = getFormValues(fid);
            const el = form.querySelector('.estimate-preview');
            if (el) el.textContent = calcDeliveryEstimate(orderType, timeSlot);
        });
    }
});
</script>
</body>
</html>