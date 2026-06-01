<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$userID = $_SESSION['user_id'];

function checkRecurringStockAvailability($pdo, $recurringID)
{
    $stmt = $pdo->prepare("
        SELECT ri.*, i.itemID
        FROM tblRecurringOrderItems ri
        JOIN tblItems i ON ri.itemID = i.itemID
        WHERE ri.recurringID = :recurringID
    ");
    $stmt->execute(['recurringID' => $recurringID]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = date('Y-m-d');

    foreach ($items as $item) {
        // Check available stock from all batches
        $checkStmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) as totalAvailable 
            FROM tblItemBatches 
            WHERE itemID = :itemID AND quantity > 0 AND expiryDate >= :today
        ");
        $checkStmt->execute(['itemID' => $item['itemID'], 'today' => $today]);
        $availableStock = (int)$checkStmt->fetchColumn();

        // If any item doesn't have enough stock, return false
        if ($availableStock < (int)$item['quantity']) {
            return false;
        }
    }

    return true;
}

// ── Handle frequency / time slot change (AJAX) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_recurring') {
    header('Content-Type: application/json');
    $recurringID = (int)($_POST['recurringID'] ?? 0);
    $frequency   = $_POST['frequency']   ?? null;
    $timeSlot    = $_POST['timeSlot']    ?? null;
    $orderType   = $_POST['orderType']   ?? null;
    $status      = $_POST['status']      ?? null;

    // Verify ownership
    $stmt = $pdo->prepare("SELECT * FROM tblRecurringOrders WHERE recurringID = :id AND userID = :uid");
    $stmt->execute(['id' => $recurringID, 'uid' => $userID]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rec) {
        echo json_encode(['error' => 'Not found']);
        exit();
    }

    // 🔒 paused_no_stock is system-controlled — block ALL manual status changes on it.
    // It will auto-resume through the cron once stock is replenished.
    if ($rec['status'] === 'paused_no_stock' && $status) {
        echo json_encode(['error' => 'This order is paused due to insufficient stock and will resume automatically once inventory is restocked. Manual status changes are not allowed.']);
        exit();
    }

    $fields = [];
    $params = ['id' => $recurringID];

    if ($frequency && in_array($frequency, ['weekly', 'monthly'])) {
        $fields[] = 'frequency = :frequency';
        $params['frequency'] = $frequency;

        // Recalculate next delivery date from today
        $today = new DateTime();
        if ($today->format('N') == 7) $today->modify('+1 day'); // skip Sunday
        $base = $today->format('Y-m-d');
        $nextDate = $frequency === 'weekly'
            ? (new DateTime($base))->modify('+7 days')->format('Y-m-d')
            : (new DateTime($base))->modify('+1 month')->format('Y-m-d');
        $fields[] = 'nextDeliveryDate = :nextDate';
        $params['nextDate'] = $nextDate;
    }
    if ($timeSlot && in_array($timeSlot, ['morning', 'afternoon', 'evening'])) {
        $fields[] = 'deliveryTimeSlot = :timeSlot';
        $params['timeSlot'] = $timeSlot;
    }
    if ($orderType && in_array($orderType, ['pickup', 'delivery'])) {
        $fields[] = 'orderType = :orderType';
        $params['orderType'] = $orderType;
    }
    if ($status && in_array($status, ['active', 'paused', 'cancelled'])) {
        $fields[] = 'status = :status';
        $params['status'] = $status;
    }

    if (!empty($fields)) {
        $pdo->prepare("UPDATE tblRecurringOrders SET " . implode(', ', $fields) . " WHERE recurringID = :id")
            ->execute($params);
    }

    // Return fresh data
    $stmt = $pdo->prepare("SELECT * FROM tblRecurringOrders WHERE recurringID = :id");
    $stmt->execute(['id' => $recurringID]);
    echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    exit();
}

// ── Handle item qty update (AJAX) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_item') {
    header('Content-Type: application/json');
    $recurringItemID = (int)($_POST['recurringItemID'] ?? 0);
    $quantity        = (int)($_POST['quantity'] ?? 1);

    // Verify ownership via join
    $stmt = $pdo->prepare("
        SELECT ri.recurringItemID FROM tblRecurringOrderItems ri
        JOIN tblRecurringOrders r ON ri.recurringID = r.recurringID
        WHERE ri.recurringItemID = :riid AND r.userID = :uid
    ");
    $stmt->execute(['riid' => $recurringItemID, 'uid' => $userID]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Not found']);
        exit();
    }

    if ($quantity < 1) {
        $pdo->prepare("DELETE FROM tblRecurringOrderItems WHERE recurringItemID = :id")->execute(['id' => $recurringItemID]);
    } else {
        $pdo->prepare("UPDATE tblRecurringOrderItems SET quantity = :qty WHERE recurringItemID = :id")
            ->execute(['qty' => $quantity, 'id' => $recurringItemID]);
    }
    echo json_encode(['success' => true]);
    exit();
}

// ── Handle cancel / delete (AJAX) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_recurring') {
    header('Content-Type: application/json');
    $recurringID = (int)($_POST['recurringID'] ?? 0);

    $stmt = $pdo->prepare("SELECT recurringID FROM tblRecurringOrders WHERE recurringID = :id AND userID = :uid");
    $stmt->execute(['id' => $recurringID, 'uid' => $userID]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Not found']);
        exit();
    }

    // Soft-cancel (preserves history)
    $pdo->prepare("UPDATE tblRecurringOrders SET status = 'cancelled' WHERE recurringID = :id")
        ->execute(['id' => $recurringID]);

    echo json_encode(['success' => true]);
    exit();
}

// ── Handle Hard Permanent Delete (AJAX) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_recurring_permanently') {
    header('Content-Type: application/json');
    $recurringID = (int)($_POST['recurringID'] ?? 0);

    $stmt = $pdo->prepare("SELECT recurringID FROM tblRecurringOrders WHERE recurringID = :id AND userID = :uid AND status = 'cancelled'");
    $stmt->execute(['id' => $recurringID, 'uid' => $userID]);
    if (!$stmt->fetch()) {
        echo json_encode(['error' => 'Not found or not cancelled yet']);
        exit();
    }

    // Table cascade foreign keys drop the items automatically, let's remove parent row cleanly
    $pdo->prepare("DELETE FROM tblRecurringOrders WHERE recurringID = :id")->execute(['id' => $recurringID]);

    echo json_encode(['success' => true]);
    exit();
}

// ── Fetch all recurring orders for this user ──────────────────────────────────
$stmt = $pdo->prepare("
    SELECT r.*
    FROM tblRecurringOrders r
    WHERE r.userID = :userID
    ORDER BY r.createdAt DESC
");
$stmt->execute(['userID' => $userID]);
$recurringOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch items for each
$recurringItems = [];
if (!empty($recurringOrders)) {
    $ids = array_column($recurringOrders, 'recurringID');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT ri.*, i.itemName, i.itemUnit, i.itemPrice, i.itemImage
        FROM tblRecurringOrderItems ri
        JOIN tblItems i ON ri.itemID = i.itemID
        WHERE ri.recurringID IN ($placeholders)
        ORDER BY ri.recurringID, i.itemName
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $recurringItems[$row['recurringID']][] = $row;
    }
}

$setupSuccess = isset($_GET['setup']);
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack – Recurring Orders</title>
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
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="hotel_orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link active" href="recurring_orders.php">Recurring</a></li>
                        <li class="nav-item"><a class="nav-link" href="bill.php">Transactions</a></li>
                        <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 border-start border-1 ms-3 px-3"
                                href="#" id="dropdownId" data-bs-toggle="dropdown">
                                <img src="user-icon.svg" alt="" width="35">
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

    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Cancel Recurring Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">You are about to cancel the following recurring order:</p>
                    <div class="card bg-light border-0">
                        <div class="card-body py-2">
                            <p class="mb-1"><strong>Products:</strong> <span id="cancelItemList"></span></p>
                            <p class="mb-1"><strong>Frequency:</strong> <span id="cancelFrequency"></span></p>
                            <p class="mb-0"><strong>Next Schedule:</strong> <span id="cancelNextDate"></span></p>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0 small">
                        ⚠️ Once cancelled, this recurring order will stop running. You can clear it permanently from your list using the delete handle.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelBtn">Yes, Cancel Order</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Delete Template Record</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete <strong>Recurring Template #<span id="deleteTargetText"></span></strong> from your profile record history?</p>
                    <p class="text-danger small mb-0">⚠️ This operation cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmHardDeleteBtn">Delete Permanently</button>
                </div>
            </div>
        </div>
    </div>

    <main class="container-lg mt-5">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div>
                <h2 class="mb-0">Recurring Orders</h2>
                <p class="text-muted mb-0">Manage your automatic delivery schedules.</p>
            </div>
            <a href="shop.php" class="btn btn-success">+ New Order</a>
        </div>

        <?php if ($setupSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                ✅ Recurring order set up successfully! Your first delivery has been placed.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div id="recurringList" class="mt-4">
            <?php if (empty($recurringOrders)): ?>
                <div class="text-center py-5">
                    <div style="font-size:3rem">🔁</div>
                    <h5 class="text-muted mt-2">No recurring orders yet.</h5>
                    <p class="text-muted">Go to your cart and choose the Recurring tab to set one up.</p>
                    <a href="shop.php" class="btn btn-success">Browse Shop</a>
                </div>
            <?php else: ?>
                <?php foreach ($recurringOrders as $rec):
                    $items   = $recurringItems[$rec['recurringID']] ?? [];
                    $itemTotal = array_sum(array_map(fn($i) => $i['itemPrice'] * $i['quantity'], $items));
                    $statusClass = 'status-badge-' . $rec['status'];
                    $isCancelled    = $rec['status'] === 'cancelled';
                    $isPausedNoStock = $rec['status'] === 'paused_no_stock';
                    $isPickup = $rec['orderType'] === 'pickup';
                    $fulfillmentLabel = $isPickup ? 'Pickup' : 'Delivery';
                ?>
                    <div class="card recurring-card card-locked-state mb-4 <?php echo $isCancelled ? 'opacity-75' : ''; ?>"
                        id="rec-card-<?php echo $rec['recurringID']; ?>" data-order-type="<?php echo $rec['orderType']; ?>">

                        <?php if ($isCancelled): ?>
                            <button type="button" class="card-delete-corner-btn" title="Delete Permanent Record"
                                onclick="triggerHardDelete(<?php echo $rec['recurringID']; ?>)">&times;</button>
                        <?php endif; ?>

                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 pe-4">
                                <div>
                                    <span class="badge <?php echo $statusClass; ?> fs-6 me-2 status-label-text">
                                        <?php echo $rec['status'] === 'paused_no_stock' ? 'PAUSED (NO STOCK)' : strtoupper($rec['status']); ?>
                                    </span>
                                    <span class="fw-bold">Recurring #<?php echo $rec['recurringID']; ?></span>
                                    <span class="text-muted small ms-2">
                                        Created <?php echo date('M d, Y', strtotime($rec['createdAt'])); ?>
                                    </span>
                                </div>

                            </div>

                            <?php if ($isPausedNoStock): ?>
                                <div class="alert alert-danger d-flex align-items-start gap-2 py-2 mb-3 small" role="alert">
                                    <span style="font-size:1.1rem;">⚠️</span>
                                    <div>
                                        <strong>Auto-Paused — Insufficient Stock</strong><br>
                                        One or more items in this order do not have enough inventory to fulfill the next delivery.
                                        This order will <strong>resume automatically</strong> once stock is replenished.
                                        No action is needed on your part.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="row g-3">
                                <div class="col-md-7">
                                    <h6 class="text-muted fw-bold mb-2">Products</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0 align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Price</th>
                                                    <th>Qty</th>
                                                    <th>Subtotal</th>
                                                    <th class="edit-actions-th" style="display:none;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($items as $item): ?>
                                                    <tr id="item-row-<?php echo $item['recurringItemID']; ?>">
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <img src="<?php echo htmlspecialchars($item['itemImage'] ?? 'placeholder.png'); ?>"
                                                                    style="width:32px;height:32px;object-fit:cover;" class="rounded">
                                                                <span><?php echo htmlspecialchars($item['itemName']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td>₱<?php echo number_format($item['itemPrice'], 2); ?></td>
                                                        <td>
                                                            <input type="number" min="1" max="999" disabled
                                                                class="form-control form-control-sm item-qty-input"
                                                                value="<?php echo $item['quantity']; ?>"
                                                                data-item-price="<?php echo $item['itemPrice']; ?>"
                                                                data-recurring-item-id="<?php echo $item['recurringItemID']; ?>"
                                                                onchange="trackLocalQtyChange(this)">
                                                        </td>
                                                        <td class="item-subtotal-td">₱<?php echo number_format($item['itemPrice'] * $item['quantity'], 2); ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-outline-danger btn-item-remove" style="display:none;"
                                                                onclick="removeRecurringItem(this, <?php echo $item['recurringItemID']; ?>, <?php echo $rec['recurringID']; ?>)"
                                                                title="Remove item">×</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($items)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-muted text-center">No items</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="text-end fw-bold">Estimated Total:</td>
                                                    <td class="fw-bold total-sum-placeholder">₱<?php echo number_format($itemTotal, 2); ?></td>
                                                    <td class="edit-actions-th" style="display:none;"></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <h6 class="text-muted fw-bold mb-2">Schedule Settings</h6>

                                    <?php if (!$isCancelled): ?>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Frequency</label>
                                            <div class="btn-group w-100 frequency-btn-group" role="group">
                                                <button type="button" disabled
                                                    class="btn btn-outline-success freq-btn data-freq-trigger <?php echo $rec['frequency'] === 'weekly' ? 'active' : ''; ?>"
                                                    onclick="toggleGroupActive(this, 'weekly')">
                                                    Weekly
                                                </button>
                                                <button type="button" disabled
                                                    class="btn btn-outline-success freq-btn data-freq-trigger <?php echo $rec['frequency'] === 'monthly' ? 'active' : ''; ?>"
                                                    onclick="toggleGroupActive(this, 'monthly')">
                                                    Monthly
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Order Type</label>
                                            <div class="btn-group w-100 order-type-btn-group" role="group">
                                                <button type="button" disabled
                                                    class="btn btn-outline-success freq-btn data-type-trigger <?php echo $rec['orderType'] === 'pickup' ? 'active' : ''; ?>"
                                                    onclick="toggleGroupActive(this, 'pickup'); updateFulfillmentLabels(this.closest('.card'), 'pickup');">
                                                    🏪 Pickup
                                                </button>
                                                <button type="button" disabled
                                                    class="btn btn-outline-success freq-btn data-type-trigger <?php echo $rec['orderType'] === 'delivery' ? 'active' : ''; ?>"
                                                    onclick="toggleGroupActive(this, 'delivery'); updateFulfillmentLabels(this.closest('.card'), 'delivery');">
                                                    🚚 Delivery
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Time Slot</label>
                                            <select class="form-select form-select-sm time-slot-select" disabled>
                                                <option value="morning" <?php echo $rec['deliveryTimeSlot'] === 'morning' ? 'selected' : ''; ?>>🌅 Morning (8 AM–12 PM)</option>
                                                <option value="afternoon" <?php echo $rec['deliveryTimeSlot'] === 'afternoon' ? 'selected' : ''; ?>>☀️ Afternoon (1–5 PM)</option>
                                                <option value="evening" <?php echo $rec['deliveryTimeSlot'] === 'evening' ? 'selected' : ''; ?>>🌆 Evening (6–8 PM)</option>
                                            </select>
                                        </div>

                                        <div class="next-delivery-chip text-center mb-3 text-chip-summary">
                                            📅 Next <span class="label-fulfillment-text"><?php echo $fulfillmentLabel; ?></span>:
                                            <strong id="next-date-<?php echo $rec['recurringID']; ?>">
                                                <?php echo date('M d, Y', strtotime($rec['nextDeliveryDate'])); ?>
                                            </strong>
                                        </div>

                                        <div class="row g-2 mb-2 container-action-toggles">
                                            <div class="col-6">
                                                <?php if (!$isPausedNoStock): ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm w-100 edit-toggle-btn"
                                                        onclick="enableCardEditMode(<?php echo $rec['recurringID']; ?>)">✏️ Edit Settings</button>
                                                    <button type="button" class="btn btn-success btn-sm w-100 save-submit-btn" style="display:none;"
                                                        onclick="saveCardSettings(<?php echo $rec['recurringID']; ?>)">💾 Save Settings</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-secondary btn-sm w-100" disabled title="Settings locked while auto-paused">🔒 Settings Locked</button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-6 status-action-wrapper">
                                                <?php if ($isPausedNoStock): ?>
                                                    <button class="btn btn-sm btn-secondary w-100" disabled title="Auto-resumes when stock is available">
                                                        ⏳ Auto-Resuming
                                                    </button>
                                                <?php elseif ($rec['status'] === 'active'): ?>
                                                    <button class="btn btn-sm btn-warning w-100"
                                                        onclick="updateRecurringState(<?php echo $rec['recurringID']; ?>, {status:'paused'}, this.closest('.card'))">
                                                        ⏸ Pause
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-success w-100 resume-btn"
                                                        onclick="updateRecurringState(<?php echo $rec['recurringID']; ?>, {status:'active'}, this.closest('.card'))"
                                                        <?php echo !checkRecurringStockAvailability($pdo, $rec['recurringID']) ? 'disabled title="Insufficient stock. Resume when items are restocked."' : ''; ?>>
                                                        ▶ Resume
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <button class="btn btn-outline-danger btn-sm w-100"
                                            onclick="confirmCancel(<?php echo $rec['recurringID']; ?>,
                                    '<?php echo addslashes(implode(', ', array_column($items, 'itemName'))); ?>',
                                    '<?php echo ucfirst($rec['frequency']); ?>',
                                    '<?php echo date('M d, Y', strtotime($rec['nextDeliveryDate'])); ?>')">
                                            🗑 Cancel Recurring Order
                                        </button>

                                    <?php else: ?>
                                        <div class="alert alert-danger py-2 small mb-2">
                                            This recurring template has been cancelled.
                                        </div>
                                        <div class="text-muted small">
                                            <p class="mb-1"><strong>Frequency Cycle:</strong> <?php echo ucfirst($rec['frequency']); ?></p>
                                            <p class="mb-1"><strong>Logistics Type:</strong> <?php echo ucfirst($rec['orderType']); ?></p>
                                            <p class="mb-0"><strong>Fulfillment Window:</strong> <?php echo ucfirst($rec['deliveryTimeSlot']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cancelRecurringID = null;
        let deleteTargetID = null;

        function updateFulfillmentLabels(card, orderType) {
            const isPickup = orderType === 'pickup';
            const currentLabel = isPickup ? 'Pickup' : 'Delivery';
            card.setAttribute('data-order-type', orderType);

            // Update main header chip if running
            const headChip = card.querySelector('.dynamic-fulfillment-chip');
            if (headChip) {
                const strongDate = headChip.querySelector('strong') ? headChip.querySelector('strong').textContent : headChip.textContent.split(':').pop().trim();
                headChip.innerHTML = `📅 Next ${currentLabel}: <strong>${strongDate}</strong>`;
            }

            // Update settings badge block label string representation context
            const labelSpan = card.querySelector('.label-fulfillment-text');
            if (labelSpan) {
                labelSpan.textContent = currentLabel;
            }
        }

        function toggleGroupActive(button, val) {
            const group = button.parentNode;
            group.querySelectorAll('.freq-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
        }

        function enableCardEditMode(recurringID) {
            const card = document.getElementById('rec-card-' + recurringID);
            card.classList.remove('card-locked-state');

            // Unhide hidden options and setup fields
            card.querySelectorAll('.freq-btn, .time-slot-select, .item-qty-input').forEach(el => el.disabled = false);
            card.querySelectorAll('.btn-item-remove, .edit-actions-th').forEach(el => el.style.display = 'table-cell');

            // Switch action buttons layouts
            card.querySelector('.edit-toggle-btn').style.display = 'none';
            card.querySelector('.save-submit-btn').style.display = 'block';
        }

        function trackLocalQtyChange(input) {
            const price = parseFloat(input.dataset.itemPrice) || 0;
            const qty = parseInt(input.value) || 0;
            const row = input.closest('tr');

            // Dynamic recalculation on-the-fly
            if (row) {
                const subtotalTd = row.querySelector('.item-subtotal-td');
                if (subtotalTd) {
                    subtotalTd.textContent = '₱' + (price * qty).toFixed(2);
                }
            }

            // Recalculate full summary column table bounds loop
            const card = input.closest('.card');
            let newTotal = 0;
            card.querySelectorAll('.item-qty-input').forEach(inp => {
                const p = parseFloat(inp.dataset.itemPrice) || 0;
                const q = parseInt(inp.value) || 0;
                newTotal += (p * q);
            });

            const totalPlaceholder = card.querySelector('.total-sum-placeholder');
            if (totalPlaceholder) {
                totalPlaceholder.textContent = '₱' + newTotal.toFixed(2);
            }
        }

        function saveCardSettings(recurringID) {
            const card = document.getElementById('rec-card-' + recurringID);

            // Gather all updated attributes safely from current unlocked controls state
            const selectedFreqBtn = card.querySelector('.frequency-btn-group .freq-btn.active');
            const selectedTypeBtn = card.querySelector('.order-type-btn-group .freq-btn.active');

            const frequency = selectedFreqBtn ? (selectedFreqBtn.textContent.trim().toLowerCase().includes('week') ? 'weekly' : 'monthly') : 'weekly';
            const orderType = selectedTypeBtn ? (selectedTypeBtn.textContent.trim().toLowerCase().includes('pick') ? 'pickup' : 'delivery') : 'delivery';
            const timeSlot = card.querySelector('.time-slot-select').value;

            // First save the template core configurations layout parameters via AJAX
            const bodyParams = new URLSearchParams({
                action: 'update_recurring',
                recurringID: recurringID,
                frequency: frequency,
                orderType: orderType,
                timeSlot: timeSlot
            });

            fetch('recurring_orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: bodyParams.toString()
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error updating baseline rules: ' + (data.error || 'Unknown'));
                        return;
                    }

                    // Push actual items variations row arrays updates synchronously loop
                    const itemPromises = [];
                    card.querySelectorAll('.item-qty-input').forEach(input => {
                        const recurringItemID = input.dataset.recurringItemId;
                        const quantity = parseInt(input.value) || 1;

                        const p = fetch('recurring_orders.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `action=update_item&recurringItemID=${recurringItemID}&quantity=${quantity}`
                        });
                        itemPromises.push(p);
                    });

                    Promise.all(itemPromises).then(() => {
                        // Re-lock Card views representation states
                        card.classList.add('card-locked-state');
                        card.querySelectorAll('.freq-btn, .time-slot-select, .item-qty-input').forEach(el => el.disabled = true);
                        card.querySelectorAll('.btn-item-remove, .edit-actions-th').forEach(el => el.style.display = 'none');

                        card.querySelector('.edit-toggle-btn').style.display = 'block';
                        card.querySelector('.save-submit-btn').style.display = 'none';

                        // Sync structural dates returned from DB calculations engine matching response
                        const d = data.data;
                        const nextEl = document.getElementById('next-date-' + recurringID);
                        if (nextEl && d.nextDeliveryDate) {
                            const dt = new Date(d.nextDeliveryDate + 'T00:00:00');
                            nextEl.textContent = dt.toLocaleDateString('en-PH', {
                                year: 'numeric',
                                month: 'short',
                                day: 'numeric'
                            });
                        }

                        updateFulfillmentLabels(card, d.orderType);
                        showSaveToast();
                    });
                })
                .catch(() => alert('Network processing exception encountered. Please review parameters.'));
        }

        function triggerHardDelete(recurringID) {
            deleteTargetID = recurringID;
            document.getElementById('deleteTargetText').textContent = recurringID;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        document.getElementById('confirmHardDeleteBtn').addEventListener('click', function() {
            if (!deleteTargetID) return;
            this.disabled = true;

            fetch('recurring_orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=delete_recurring_permanently&recurringID=${deleteTargetID}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                        const targetElementCard = document.getElementById('rec-card-' + deleteTargetID);
                        if (targetElementCard) {
                            targetElementCard.remove();
                        }
                        // If list became empty check trigger simple notice refresh reload alternative
                        if (document.querySelectorAll('.recurring-card').length === 0) {
                            location.reload();
                        }
                    } else {
                        alert('Failure executing purge: ' + data.error);
                    }
                    this.disabled = false;
                    deleteTargetID = null;
                })
                .catch(() => {
                    alert('Network exception trying to wipe template database index.');
                    this.disabled = false;
                });
        });

        function confirmCancel(recurringID, items, frequency, nextDate) {
            cancelRecurringID = recurringID;
            document.getElementById('cancelItemList').textContent = items || '—';
            document.getElementById('cancelFrequency').textContent = frequency;
            document.getElementById('cancelNextDate').textContent = nextDate;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

        document.getElementById('confirmCancelBtn').addEventListener('click', function() {
            if (!cancelRecurringID) return;
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Cancelling…';

            fetch('recurring_orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=cancel_recurring&recurringID=${cancelRecurringID}`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('cancelModal')).hide();
                        location.reload(); // Quick reset cleanly inserts corner close layouts directly from fresh template rows injection
                    } else {
                        alert('Error: ' + (data.error || 'Something went wrong'));
                    }
                    btn.disabled = false;
                    btn.textContent = 'Yes, Cancel Order';
                    cancelRecurringID = null;
                });
        });

        function updateRecurringState(recurringID, payload, card) {
            const body = new URLSearchParams({
                action: 'update_recurring',
                recurringID
            });
            for (const [k, v] of Object.entries(payload)) body.append(k, v);

            fetch('recurring_orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: body.toString()
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error: ' + (data.error || 'Unknown error'));
                        return;
                    }
                    const d = data.data;

                    // Sync main text states safely
                    card.querySelectorAll('.status-label-text').forEach(b => {
                        const statusClass = {
                            active: 'status-badge-active',
                            paused: 'status-badge-paused',
                            paused_no_stock: 'status-badge-paused_no_stock',
                            cancelled: 'status-badge-cancelled'
                        } [d.status] || '';
                        b.className = 'badge ' + statusClass + ' fs-6 me-2 status-label-text';
                        b.textContent = d.status === 'paused_no_stock' ? 'PAUSED (NO STOCK)' : d.status.toUpperCase();
                    });

                    // Toggle actionable text handles inside wrapper cells
                    const statusWrapper = card.querySelector('.status-action-wrapper');
                    if (statusWrapper) {
                        if (d.status === 'active') {
                            statusWrapper.innerHTML = `<button class="btn btn-sm btn-warning w-100" onclick="updateRecurringState(${recurringID}, {status:'paused'}, this.closest('.card'))">⏸ Pause</button>`;
                        } else {
                            statusWrapper.innerHTML = `<button class="btn btn-sm btn-success w-100" onclick="updateRecurringState(${recurringID}, {status:'active'}, this.closest('.card'))">▶ Resume</button>`;
                        }
                    }

                    showSaveToast();
                });
        }

        function removeRecurringItem(btn, recurringItemID, recurringID) {
            if (!confirm('Remove this item from the recurring order?')) return;
            fetch('recurring_orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=update_item&recurringItemID=${recurringItemID}&quantity=0`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const row = btn.closest('tr');
                        const card = btn.closest('.card');
                        row.remove();

                        // Recalculate total summary display fields setup
                        let newTotal = 0;
                        card.querySelectorAll('.item-qty-input').forEach(inp => {
                            const p = parseFloat(inp.dataset.itemPrice) || 0;
                            const q = parseInt(inp.value) || 0;
                            newTotal += (p * q);
                        });
                        const totalPlaceholder = card.querySelector('.total-sum-placeholder');
                        if (totalPlaceholder) {
                            totalPlaceholder.textContent = '₱' + newTotal.toFixed(2);
                        }
                        showSaveToast();
                    } else {
                        alert('Error: ' + (data.error || 'Unknown'));
                    }
                });
        }

        function showSaveToast() {
            let toast = document.getElementById('saveToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'saveToast';
                toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#198754;color:#fff;padding:10px 20px;border-radius:8px;z-index:9999;font-weight:500;transition:opacity 0.4s';
                toast.textContent = '✓ Configuration Saved';
                document.body.appendChild(toast);
            }
            toast.style.opacity = '1';
            clearTimeout(toast._t);
            toast._t = setTimeout(() => {
                toast.style.opacity = '0';
            }, 2000);
        }
    </script>
</body>

</html>