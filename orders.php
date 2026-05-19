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
                <a class="navbar-brand me-auto" href="#">
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
                            <a class="nav-link" href="purchases.php">Purchases</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions.php">Transactions</a>
                        </li>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="users.php">Users</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main>
        <div class="container-fluid mt-5 w-75">
            <h2>Orders</h2>
            <p>Manage your orders here.</p>
            <div class="mt-5">
                <div class="card mb-4">
                    <h3 class="card-header bg-success-subtle">
                        Pending Orders
                    </h3>
                    <div class="orders card-body">
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM tblOrders WHERE status = 'pending'");
                        $stmt->execute();
                        $pendingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($pendingOrders)) {
                            foreach ($pendingOrders as $order) {
                                echo '<div class="card mb-3">';
                                echo '  <div class="card-body">';
                                echo '    <h5 class="card-title">Order #' . htmlspecialchars($order['orderID'] ?? 'N/A') . '</h5>';
                                echo '    <p class="card-text"><strong>Customer:</strong> ' . htmlspecialchars($order['orderName'] ?? 'N/A') . '</p>';
                                echo '    <p class="card-text"><span class="badge bg-warning text-dark">Pending</span></p>';
                                echo '    <a href="order_details.php?orderID=' . urlencode($order['orderID']) . '" class="btn btn-primary">View Details</a>';
                                echo '    <a href="update_order.php?orderID=' . urlencode($order['orderID']) . '" class="btn btn-success">Update Status</a>';
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
        </div>
    </main>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>

</html>