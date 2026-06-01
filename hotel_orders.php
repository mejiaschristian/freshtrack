<?php
session_start();
require_once 'auth.php';
require_once 'db.php';
// Include the automation script logic to process operations seamlessly
require_once 'cron_process_recurring.php';
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}
// Helper function to sync cached values from batches to tblItems
function syncItemFromBatchesLocal($pdo, $itemID)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity), 0) AS totalQty,
               MIN(CASE WHEN quantity > 0 THEN expiryDate END) AS fifoExpiry
        FROM tblItemBatches
        WHERE itemID = :id
    ");
    $stmt->execute(['id' => $itemID]);
    $sync = $stmt->fetch(PDO::FETCH_ASSOC);

    $updateItemStmt = $pdo->prepare("
        UPDATE tblItems 
        SET itemQuantity = :qty, 
            itemExpiryDate = :exp 
        WHERE itemID = :itemID
    ");
    $updateItemStmt->execute([
        'qty'    => $sync['totalQty'],
        'exp'    => $sync['fifoExpiry'] ?? date('Y-m-d'),
        'itemID' => $itemID
    ]);
}
$userID = $_SESSION['user_id'];
$message = "";
$messageType = "";
// AUTOMATIC TRIGGER RUNTIME ENGINE CHECKER
// Every time a page loads, this parses background subscriptions to ensure everything is up to date
processAutomaticRecurringBatches($pdo);

// Handle cancel order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
    $orderID = $_POST['orderID'] ?? null;

    if ($orderID) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM tblOrders WHERE orderID = :orderID AND userID = :userID AND status = 'pending'");
            $stmt->execute(['orderID' => $orderID, 'userID' => $userID]);
            $order = $stmt->fetch();

            if ($order) {
                // Get order items to restore stock
                $stmt = $pdo->prepare("SELECT * FROM tblOrderItems WHERE orderID = :orderID");
                $stmt->execute(['orderID' => $orderID]);
                $orderItems = $stmt->fetchAll();

                // Restore stock accurately across split batches using Newest-to-Oldest Logic
                foreach ($orderItems as $item) {
                    $quantityToRestore = (int)$item['quantity'];
                    $itemID = $item['itemID'];

                    // 1. Get all batches for this item that have room to receive returned stock,
                    // sorted by DESC to replenish the newest batches first
                    $batchStmt = $pdo->prepare("
                        SELECT batchID, quantity, initialQty 
                        FROM tblItemBatches 
                        WHERE itemID = :itemID AND quantity < initialQty
                        ORDER BY expiryDate DESC
                    ");
                    $batchStmt->execute([
                        'itemID' => $itemID
                    ]);
                    $activeBatches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($activeBatches as $batch) {
                        if ($quantityToRestore <= 0) break;

                        $currentQty = (int)$batch['quantity'];
                        $maxQty     = (int)$batch['initialQty'];
                        $roomLeft   = $maxQty - $currentQty; // How many items this batch is missing

                        if ($roomLeft > 0) {
                            // Determine how much to put back into this specific batch
                            $amountToReturn = min($quantityToRestore, $roomLeft);

                            $updateBatch = $pdo->prepare("
                                UPDATE tblItemBatches 
                                SET quantity = quantity + :amount 
                                WHERE batchID = :batchID
                            ");
                            $updateBatch->execute([
                                'amount'  => $amountToReturn,
                                'batchID' => $batch['batchID']
                            ]);

                            // Deduct from our remaining pool of items waiting to be returned
                            $quantityToRestore -= $amountToReturn;
                        }
                    }

                    // 2. Emergency Backup Fallback: If there's still leftover quantity,
                    // add the remainder to the newest available batch.
                    if ($quantityToRestore > 0) {
                        $fallbackStmt = $pdo->prepare("
                            SELECT batchID FROM tblItemBatches 
                            WHERE itemID = :itemID
                            ORDER BY expiryDate DESC LIMIT 1
                        ");
                        $fallbackStmt->execute(['itemID' => $itemID]);
                        $fallbackBatch = $fallbackStmt->fetch();

                        if ($fallbackBatch) {
                            $updateFallback = $pdo->prepare("UPDATE tblItemBatches SET quantity = quantity + :qty WHERE batchID = :batchID");
                            $updateFallback->execute(['qty' => $quantityToRestore, 'batchID' => $fallbackBatch['batchID']]);
                        }
                    }

                    // 3. Keep the frontend web cache table perfectly synchronized
                    syncItemFromBatchesLocal($pdo, $itemID);
                }

                // Get bill IDs before deleting
                $stmt = $pdo->prepare("SELECT billID FROM tblBillOrders WHERE orderID = :orderID");
                $stmt->execute(['orderID' => $orderID]);
                $bills = $stmt->fetchAll();

                // Delete tblBillOrders FIRST (child)
                $pdo->prepare("DELETE FROM tblBillOrders WHERE orderID = :orderID")
                    ->execute(['orderID' => $orderID]);

                // Then delete tblBills (parent)
                foreach ($bills as $bill) {
                    $pdo->prepare("DELETE FROM tblBills WHERE billID = :billID")
                        ->execute(['billID' => $bill['billID']]);
                }

                // Delete order items
                $pdo->prepare("DELETE FROM tblOrderItems WHERE orderID = :orderID")
                    ->execute(['orderID' => $orderID]);

                // Delete the order
                $pdo->prepare("DELETE FROM tblOrders WHERE orderID = :orderID")
                    ->execute(['orderID' => $orderID]);

                $message = "Order cancelled successfully!";
                $messageType = "success";
            } else {
                $message = "Order not found or cannot be cancelled.";
                $messageType = "warning";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Handle AJAX request for Order Details
if (isset($_GET['action']) && $_GET['action'] === 'get_order_details') {
    header('Content-Type: application/json');
    $orderID = $_GET['orderID'] ?? 0;

    try {
        // Fetch order info along with a DATEDIFF calculation to see how many days it spans
        $stmt = $pdo->prepare("
            SELECT o.*, tblusers.fullName AS hotelName,
                   DATEDIFF(o.deliveryDate, o.orderDate) AS total_days
            FROM tblOrders o
            INNER JOIN tblusers ON o.userID = tblusers.userID
            WHERE o.orderID = :orderID AND o.userID = :userID
        ");
        $stmt->execute(['orderID' => $orderID, 'userID' => $userID]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // Fetch associated order items
            $itemStmt = $pdo->prepare("
                SELECT oi.*, i.itemName 
                FROM tblOrderItems oi
                INNER JOIN tblItems i ON oi.itemID = i.itemID
                WHERE oi.orderID = :orderID
            ");
            $itemStmt->execute(['orderID' => $orderID]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'order' => $order,
                'items' => $items
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

$billID = $_GET['billID'] ?? null;
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Hotel Orders</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header class="sticky-top">
        <nav class="navbar navbar-expand-lg navbar-dark bg-success">
            <div class="container-lg">
                <a class="navbar-brand me-auto" href="shop.php">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid" />
                </a>
                <button
                    class="navbar-toggler"
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
                            <a class="nav-link" href="shop.php">Shop</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">Cart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="hotel_orders.php">Orders</a>
                            <span class="visually-hidden">(current)</span>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="recurring_orders.php">Recurring</a></li>
                        <li class="nav-item">
                            <a class="nav-link" href="bill.php">Transactions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about.php">About</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle d-flex align-items-center gap-2 border-start border-1 ms-3 px-3"
                                href="#"
                                id="dropdownId"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
                                <img src="user-icon.svg" alt="user-icon" width="35">
                                <span><?php echo $_SESSION['username'] ?? 'Guest'; ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownId">
                                <a class="dropdown-item" href="index.php">
                                    <i class="bi bi-box-arrow-right"></i> Log Out
                                </a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsLabel">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modal_currentOrderID">
                    <input type="hidden" id="modal_currentStatus">

                    <div class="row mb-3 align-items-start">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Order #:</strong> <span id="modal_orderID"></span></p>
                            <p class="mb-1"><strong>Hotel:</strong> <span id="modal_orderHotel"></span></p>
                            <div class="mt-2 d-flex gap-2 flex-wrap">
                                <span class="badge bg-primary fs-6 text-capitalize" id="modal_orderType"></span>
                                <span class="badge fs-6 text-capitalize" id="modal_orderFrequency"></span>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end mt-2 mt-md-0">
                            <p class="mb-1"><strong>Date:</strong> <span id="modal_orderDate"></span></p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-warning text-uppercase text-black" id="modal_orderStatus"></span></p>
                        </div>
                    </div>

                    <div class="p-3 bg-light border rounded mb-3">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <strong>Preferred Time Slot:</strong> <span id="modal_deliveryTimeSlot" class="text-capitalize text-secondary"></span>
                            </div>
                            <div class="col-sm-6">
                                <strong>Estimated Delivery:</strong> <span id="modal_estimatedDelivery" class="text-secondary"></span>
                            </div>
                            <div class="col-12 border-top pt-2 mt-2">
                                <strong>Fulfillment Timeline:</strong> <span id="modal_daysDifference" class="badge bg-secondary">0</span> Day(s) structured receiving window.
                            </div>
                        </div>
                    </div>
                    <hr>

                    <table class="table table-bordered align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Item</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="modal_orderItems">
                        </tbody>
                    </table>
                    <div class="text-end">
                        <h5>Total: <strong id="modal_orderTotal"></strong></h5>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <form method="POST" action="hotel_orders.php" style="display:inline;">
                        <input type="hidden" name="action" value="cancel_order">
                        <input type="hidden" id="modal_cancelOrderID" name="orderID">
                        <button type="submit" class="btn btn-danger" id="cancelOrderBtn" onclick="return confirm('Are you sure you want to cancel this order?');">Cancel Order</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <main>
        <div class="container-lg mt-5">
            <h2 class="mb-1">Your Orders</h2>
            <p class="text-muted">Check your orders</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <h3 class="card-header bg-success text-white">Pending Orders</h3>
                        <div class="cards-cont card-body">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT tblOrders.*, tblusers.fullName AS hotelName
                                FROM tblOrders
                                INNER JOIN tblusers ON tblOrders.userID = tblusers.userID
                                WHERE tblOrders.userID = :userID AND
                                (tblOrders.status = 'pending' OR tblOrders.status = '')
                                ORDER BY tblOrders.orderDate DESC
                            ");
                            $stmt->execute(['userID' => $_SESSION['user_id']]);
                            $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($pendingOrders)) {
                                foreach ($pendingOrders as $order) {
                                    echo '<div class="order-card card border-left-primary mb-3 position-relative">';
                                    echo '  <div class="card-body">';
                                    echo '    <h5 class="card-title">Order #' . htmlspecialchars($order['orderID']) . '</h5>';
                                    echo '    <p class="card-text"><strong>Hotel:</strong> ' . htmlspecialchars($order['hotelName']) . '</p>';
                                    echo '    <p class="card-text"><strong>Date:</strong> ' . date('M d, Y', strtotime($order['orderDate'])) . '</p>';
                                    echo '    <p class="card-text"><strong>Total:</strong> ₱' . number_format($order['totalAmount'], 2) . '</p>';
                                    echo '    <p class="card-text text-uppercase"><span class="badge bg-warning text-dark"> ' . htmlspecialchars($order['status'] ?? 'N/A') . '</span></p>';
                                    echo '    <button class="btn btn-primary me-2" onclick="viewOrderDetails(' . $order['orderID'] . ')">View Details</button>';
                                    echo '  </div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p class="text-muted">No pending orders at the moment.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <h3 class="card-header bg-success text-white">Billed Orders</h3>
                        <div class="cards-cont card-body">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT tblOrders.*, tblusers.fullName AS hotelName, tblBillOrders.billID
                                FROM tblOrders
                                INNER JOIN tblusers ON tblOrders.userID = tblusers.userID
                                LEFT JOIN tblBillOrders ON tblOrders.orderID = tblBillOrders.orderID
                                WHERE tblOrders.userID = :userID AND
                                (tblOrders.status = 'billed' OR tblOrders.status = 'paid')
                                ORDER BY tblOrders.orderDate DESC  
                            ");
                            $stmt->execute(['userID' => $_SESSION['user_id']]);

                            $billedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($billedOrders)) {
                                foreach ($billedOrders as $order) {
                                    echo '<div class="order-card card border-left-success mb-3">';
                                    echo '  <div class="card-body">';
                                    echo '    <h5 class="card-title">Order #' . htmlspecialchars($order['orderID']) . '</h5>';
                                    echo '    <p class="card-text"><strong>Hotel:</strong> ' . htmlspecialchars($order['hotelName'] ?? 'N/A') . '</p>';
                                    echo '    <p class="card-text"><strong>Date:</strong> ' . date('M d, Y', strtotime($order['orderDate'])) . '</p>';
                                    echo '    <p class="card-text"><strong>Total:</strong> ₱' . number_format($order['totalAmount'], 2) . '</p>';
                                    echo '    <p class="card-text text-uppercase"><span class="badge bg-success"> ' . htmlspecialchars($order['status'] ?? 'N/A') . '</span></p>';
                                    echo '    <button class="btn btn-primary me-2" onclick="viewOrderDetails(' . $order['orderID'] . ')">View Details</button>';
                                    if (!empty($order['billID'])) {
                                        echo '    <a href="bill.php?billID=' . htmlspecialchars($order['billID']) . '" class="btn btn-outline-success">View Bill</a>';
                                    }
                                    echo '  </div>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<p class="text-muted">No billed orders at the moment.</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
    <footer>
    </footer>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="script.js"></script>


</body>

</html>