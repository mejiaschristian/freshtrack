<?php
session_start();
require_once 'auth.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$stats = getDashboardStats($pdo);
$lowStockItems = getLowStockItems($pdo);
$expiringItems = getExpiringItems($pdo, 7);
$recentOrders = getRecentOrders($pdo, 5);
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Dashboard</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <header class="sticky-top">
        <nav class="navbar navbar-expand-sm navbar-dark bg-success">
            <div class="container-fluid w-75">
                <a class="navbar-brand me-auto" href="dashboard.php">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid" />
                </a>
                <button class=" navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="inventory.php">Inventory</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="purchases.php">Purchases</a></li>
                        <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container-fluid py-4 w-75">
        <div class="row mb-4">
            <div class="col">
                <h1 class="mb-1">Dashboard</h1>
                <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-left-success">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Orders</h6>
                        <h2 class="card-text text-success"><?php echo $stats['totalOrders']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-warning">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Pending Orders</h6>
                        <h2 class="card-text text-warning"><?php echo $stats['pendingOrders']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-info">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Items</h6>
                        <h2 class="card-text text-info"><?php echo $stats['totalItems']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-left-danger">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Low Stock Items</h6>
                        <h2 class="card-text text-danger"><?php echo $stats['lowStockCount']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-success">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Users</h6>
                        <h2 class="card-text text-success"><?php echo $stats['totalUsers']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-primary">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Inventory Value</h6>
                        <h2 class="card-text text-primary">₱<?php echo number_format($stats['inventoryValue'], 2); ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts Section -->
        <?php if (!empty($lowStockItems)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h5 class="alert-heading">⚠️ Low Stock Alert</h5>
                <p><?php echo count($lowStockItems); ?> item(s) are running low on stock:</p>
                <ul class="mb-0">
                    <?php foreach (array_slice($lowStockItems, 0, 5) as $item): ?>
                        <li><?php echo htmlspecialchars($item['itemName']); ?> - Only <?php echo $item['itemQuantity']; ?> left</li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($expiringItems)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h5 class="alert-heading">🔴 Expiring Soon</h5>
                <p><?php echo count($expiringItems); ?> item(s) are expiring within 7 days:</p>
                <ul class="mb-0">
                    <?php foreach (array_slice($expiringItems, 0, 5) as $item): ?>
                        <li><?php echo htmlspecialchars($item['itemName']); ?> - Expires <?php echo date('M d, Y', strtotime($item['itemExpiryDate'])); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Recent Orders Section -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Recent Orders</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['orderID']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customerName']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['orderDate'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $order['status'] === 'pending' ? 'warning' : ($order['status'] === 'completed' ? 'success' : 'danger'); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No orders found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .card.border-left-success {
            border-left: 4px solid var(--primary-green);
        }

        .card.border-left-warning {
            border-left: 4px solid #ffc107;
        }

        .card.border-left-info {
            border-left: 4px solid #17a2b8;
        }

        .card.border-left-danger {
            border-left: 4px solid #dc3545;
        }

        .card.border-left-primary {
            border-left: 4px solid #007bff;
        }
    </style>
</body>

</html>