<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}
?>


<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Orders</title>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS v5.3.8 -->
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
            <div class="container-fluid w-75">
                <a class="navbar-brand me-auto" href="dashboard.php">
                    <img
                        src="fresh-track.png"
                        alt="FreshTrack"
                        class="img-fluid" />
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
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="inventory.php">Inventory</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="orders.php">Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions.php">Transactions</a>
                        </li>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="users.php">Users</a>
                            </li>
                        <?php endif; ?>
                        <li class="border-start border-success-subtle ps-3 nav-item dropdown d-flex align-items-center mx-3">
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

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsLabel">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Order Info -->
                    <input type="hidden" id="modal_currentOrderID">
                    <input type="hidden" id="modal_currentStatus">
                    <div class="row mb-3">
                        <div class="col">
                            <p class="mb-1"><strong>Order #:</strong> <span id="modal_orderID"></span></p>
                            <p class="mb-1"><strong>Hotel:</strong> <span id="modal_orderHotel"></span></p>
                            <p class="mb-1 badge bg-primary fs-6">For <span id="modal_orderType"></span></p>
                        </div>
                        <div class="col text-end">
                            <p class="mb-1"><strong>Date:</strong> <span id="modal_orderDate"></span></p>
                            <p class="mb-1"><strong>Status:</strong> <span id="modal_orderStatus"></span></p>
                        </div>
                    </div>
                    <hr>
                    <!-- Items Table -->
                    <table class="table table-bordered">
                        <thead class="table-secondary">
                            <tr>
                                <th>Item</th>
                                <th>Unit Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="modal_orderItems">
                            <!-- filled by JS -->
                        </tbody>
                    </table>
                    <div class="text-end">
                        <h5>Total: <strong id="modal_orderTotal"></strong></h5>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="completeOrderBtn" onclick="completeOrder()">Complete Order</button>
                    <a href="#" class="btn btn-primary d-none" id="viewBillBtn">View Bill</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Bill View Modal -->
    <div class="modal fade" id="adminBillModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Bill Receipt</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col">
                            <p class="mb-1"><strong>Bill #:</strong> <span id="abill_number"></span></p>
                            <p class="mb-1"><strong>Hotel:</strong> <span id="abill_hotel"></span></p>
                        </div>
                        <div class="col text-end">
                            <p class="mb-1"><strong>Bill Date:</strong> <span id="abill_date"></span></p>
                            <p class="mb-1"><strong>Due Date:</strong> <span id="abill_due"></span></p>
                            <p class="mb-1"><strong>Status:</strong> <span id="abill_status"></span></p>
                        </div>
                    </div>
                    <hr>
                    <table class="table table-bordered table-sm">
                        <thead class="table-secondary">
                            <tr>
                                <th>Item</th>
                                <th>Unit Price</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="abill_items"></tbody>
                    </table>
                    <div class="text-end mt-2">
                        <p class="mb-1">Subtotal: <strong id="abill_subtotal"></strong></p>
                        <p class="mb-1 text-danger" id="abill_penalty_row">Penalty (5%): <strong id="abill_penalty"></strong></p>
                        <h5>Total Due: <strong id="abill_total"></strong></h5>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div id="abill_action_buttons">
                        <button type="button" class="btn btn-warning me-2" id="markPartialBtn" onclick="markBillStatus('partial')">
                            Mark as Partial
                        </button>
                        <button type="button" class="btn btn-success" id="markPaidBtn" onclick="markBillStatus('paid')">
                            Mark as Paid
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Complete Order Modal -->
    <div class="modal fade" id="confirmCompleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Complete Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to mark <strong>Order #<span id="confirm_orderID"></span></strong> as billed and generate a bill?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmCompleteBtn">Yes, Complete Order</button>
                </div>
            </div>
        </div>
    </div>
    <main>
        <div class="container-fluid mt-5 w-75">
            <h2 class="mb-1">Orders</h2>
            <p class="text-muted">Manage your orders here.</p>
            <div class="orders mt-5 row">

                <!-- Pending Orders -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <h3 class="card-header bg-success text-white">Pending Orders</h3>
                        <div class="cards-cont card-body">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT tblOrders.*, tblusers.fullName AS hotelName
                                FROM tblOrders
                                INNER JOIN tblusers ON tblOrders.userID = tblusers.userID
                                WHERE tblOrders.status = 'pending' OR tblOrders.status = ''
                                ORDER BY tblOrders.orderDate DESC
                            ");
                            $stmt->execute();
                            $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($pendingOrders)) {
                                foreach ($pendingOrders as $order) {
                                    echo '<div class="order-card card border-left-primary mb-3 position-relative">';
                                    echo '  <div class="card-body">';
                                    echo '    <h5 class="card-title">Order #' . htmlspecialchars($order['orderID']) . '</h5>';
                                    echo '    <p class="card-text"><strong>Hotel:</strong> ' . htmlspecialchars($order['hotelName'] ?? 'N/A') . '</p>';
                                    echo '    <p class="card-text"><strong>Date:</strong> ' . date('M d, Y', strtotime($order['orderDate'])) . '</p>';
                                    echo '    <p class="card-text"><strong>Total:</strong> ₱' . number_format($order['totalAmount'], 2) . '</p>';
                                    echo '    <p class="card-text"><span class="badge bg-warning text-dark text-uppercase">' . htmlspecialchars($order['status']) . '</span></p>';
                                    echo '    <button class="btn btn-primary" onclick="viewOrderDetails(' . $order['orderID'] . ')">View Details</button>';
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

                <!-- Billed Orders -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <h3 class="card-header bg-success text-white">Billed Orders</h3>
                        <div class="cards-cont card-body">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT tblOrders.*, tblusers.fullName AS hotelName
                                FROM tblOrders
                                INNER JOIN tblusers ON tblOrders.userID = tblusers.userID
                                WHERE (tblOrders.status = 'billed' OR tblOrders.status = 'paid')
                                ORDER BY tblOrders.orderDate DESC
                            ");
                            $stmt->execute();
                            $completedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (!empty($completedOrders)) {
                                foreach ($completedOrders as $order) {
                                    echo '<div class="order-card card border-left-success mb-3">';
                                    echo '  <div class="card-body">';
                                    echo '    <h5 class="card-title">Order #' . htmlspecialchars($order['orderID']) . '</h5>';
                                    echo '    <p class="card-text"><strong>Hotel:</strong> ' . htmlspecialchars($order['hotelName'] ?? 'N/A') . '</p>';
                                    echo '    <p class="card-text"><strong>Date:</strong> ' . date('M d, Y', strtotime($order['orderDate'])) . '</p>';
                                    echo '    <p class="card-text"><strong>Total:</strong> ₱' . number_format($order['totalAmount'], 2) . '</p>';
                                    echo '    <p class="card-text"><span class="badge bg-success text-uppercase">' . htmlspecialchars($order['status']) . '</span></p>';
                                    echo '    <button class="btn btn-primary" onclick="viewOrderDetails(' . $order['orderID'] . ')">View Details</button>';
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
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="script.js"></script>
</body>

</html>