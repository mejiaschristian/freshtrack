<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$message = "";
$messageType = "";

$billID = $_GET['billID'] ?? null;

?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Hotel Orders</title>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS v5.3.8 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css">
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
                <div class="mx-5 p-2 bg-white rounded-5 d-flex align-items-center justify-content-center text-center">
                    <p class="mb-0"><b>Hotel Name:</b> <?php echo $_SESSION['username'] ?? 'Guest'; ?></p>
                </div>

                <div class="collapse navbar-collapse" id="collapsibleNavId">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item">
                            <a class="nav-link" href="#" aria-current="page">
                                Shop
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">Cart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="hotel_orders.php">Orders
                                <span class="visually-hidden">(current)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bill.php">Transactions</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle"
                                href="#"
                                id="dropdownId"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
                                More
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
                            <p class="mb-1 badge bg-primary fs-6"><strong>For </strong><span id="modal_orderType"></span></p>
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
                </div>
            </div>
        </div>
    </div>
    <main>
        <div class="container-lg mt-5">
            <h2>Your Orders</h2>
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
                                    echo '    <p class="card-text"><strong>Hotel:</strong> ' . htmlspecialchars($order['hotelName'] ?? 'N/A') . '</p>';
                                    echo '    <p class="card-text"><strong>Date:</strong> ' . date('M d, Y', strtotime($order['orderDate'])) . '</p>';
                                    echo '    <p class="card-text"><strong>Total:</strong> ₱' . number_format($order['totalAmount'], 2) . '</p>';
                                    echo '    <p class="card-text"><span class="badge bg-warning text-dark">Pending</span></p>';
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
                                (tblOrders.status = 'billed' OR tblOrders.status = '')
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
                                    echo '    <p class="card-text"><span class="badge bg-success">Billed</span></p>';
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