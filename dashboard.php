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

//variables for data anayltics
$ordersTrend       = getOrdersTrend($pdo);
$topSellingItems   = getTopSellingItems($pdo);
$statusBreakdown   = getOrderStatusBreakdown($pdo);
$expiringWithValue = getExpiringItemsWithValue($pdo, 7);
$revenueComp       = getRevenueComparison($pdo);

?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Dashboard</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
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
                        <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
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

    <main class="container-fluid mt-5 w-75">
        <h2 class="mb-1">Dashboard</h2>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>

        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card border-left-success">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Orders</h6>
                        <h3 class="card-text text-success"><?php echo $stats['totalOrders']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-warning">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Pending Orders</h6>
                        <h3 class="card-text text-warning"><?php echo $stats['pendingOrders']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-info">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Items</h6>
                        <h3 class="card-text text-info"><?php echo $stats['totalItems']; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card border-left-danger">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Low Stock Items</h6>
                        <h3 class="card-text text-danger"><?php echo $stats['lowStockCount']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-success">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Users</h6>
                        <h3 class="card-text text-success"><?php echo $stats['totalUsers']; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card border-left-primary">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Inventory Value</h6>
                        <h3 class="card-text text-primary">₱<?php echo number_format($stats['inventoryValue'], 2); ?></h3>
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
        <div class="card mb-3">
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
                        <?php
                        if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['orderID']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customerName']); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['orderDate'])); ?></td>
                                    <td>
                                        <?php
                                        $badgeColor = match ($order['status']) {
                                            'pending' => 'warning text-dark',
                                            'billed'  => 'primary',
                                            'partial' => 'warning text-dark',
                                            'paid'    => 'success',
                                            default   => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?php echo $badgeColor; ?>">
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
        <!-- Data Analytics Section -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Data Analytics</h5>
            </div>
            <div class="analytics-body card-body">

                <!-- Revenue Comparison -->
                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="card shadow-sm">
                            <div class="card-body text-center">
                                <h5 class="card-title text-muted">Revenue This Week vs Last Week</h5>
                                <?php
                                $thisWeek = $revenueComp['thisWeek'] ?? 0;
                                $lastWeek = $revenueComp['lastWeek'] ?? 0;
                                $diff     = $thisWeek - $lastWeek;
                                $pct      = $lastWeek > 0 ? round(($diff / $lastWeek) * 100, 1) : null;
                                $arrow    = $diff >= 0 ? '▲' : '▼';
                                $color    = $diff >= 0 ? 'success' : 'danger';
                                ?>
                                <div class="d-flex align-items-center justify-content-center text-center gap-4 flex-wrap mt-2">
                                    <div>
                                        <div class="text-muted small">This Week</div>
                                        <div class="fs-4 fw-bold text-success">₱<?php echo number_format($thisWeek, 2); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Last Week</div>
                                        <div class="fs-4 fw-bold text-secondary">₱<?php echo number_format($lastWeek, 2); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Change</div>
                                        <div class="fs-5 fw-bold text-<?php echo $color; ?>">
                                            <?php echo $arrow; ?> ₱<?php echo number_format(abs($diff), 2); ?>
                                            <?php if ($pct !== null): ?>
                                                <span class="fs-6">(<?php echo ($diff >= 0 ? '+' : ''); ?><?php echo $pct; ?>%)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Orders Trend + Order Status Breakdown -->
                <div class="row">

                    <!-- Orders & Revenue Trend (last 7 days) -->
                    <div class="col-md-8 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Orders & Revenue Trend <small class="text-secondary">(Last 7 Days)</small></h5>
                                <canvas id="ordersTrendChart" height="160"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Order Status Breakdown -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Order Status Breakdown</h5>
                                <canvas id="statusChart" height="160"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Top Selling Items + Expiry / Wastage -->
                <div class="row mb-4">

                    <!-- Top Selling Items -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Top-Selling Items <small class="text-secondary">(by Quantity)</small></h5>
                                <canvas id="topItemsChart" height="160"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Expiry / Wastage Rate -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-muted">Expiry / Wastage (Next 7 Days)</h5>
                                <?php
                                $totalWaste = array_sum(array_column($expiringWithValue, 'wasteValue'));
                                ?>
                                <?php if (!empty($expiringWithValue)): ?>
                                    <div class="alert alert-danger py-2 mb-2">
                                        Estimated waste value: <strong>₱<?php echo number_format($totalWaste, 2); ?></strong>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Qty</th>
                                                    <th>Expires</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($expiringWithValue as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['itemName']); ?></td>
                                                        <td><?php echo $item['itemQuantity']; ?></td>
                                                        <td><?php echo date('M d', strtotime($item['itemExpiryDate'])); ?></td>
                                                        <td class="text-danger">₱<?php echo number_format($item['wasteValue'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mt-3 text-center">No items expiring soon.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>


            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="bootstrap.bundle.min.js"></script>
    <script>
        // ---- 1. Orders & Revenue Trend ----
        const trendLabels = <?php
                            // Build full 7-day label array, filling zeros for missing days
                            $days = [];
                            for ($i = 6; $i >= 0; $i--) {
                                $days[date('Y-m-d', strtotime("-$i days"))] = ['orders' => 0, 'revenue' => 0];
                            }
                            foreach ($ordersTrend as $row) {
                                if (isset($days[$row['day']])) {
                                    $days[$row['day']]['orders']  = (int)$row['orderCount'];
                                    $days[$row['day']]['revenue'] = (float)$row['revenue'];
                                }
                            }
                            echo json_encode(array_map(fn($d) => date('D M d', strtotime($d)), array_keys($days)));
                            ?>;
        const trendOrders = <?php echo json_encode(array_column(array_values($days), 'orders')); ?>;
        const trendRevenue = <?php echo json_encode(array_column(array_values($days), 'revenue')); ?>;

        new Chart(document.getElementById('ordersTrendChart'), {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [{
                        label: 'Orders',
                        data: trendOrders,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Revenue (₱)',
                        data: trendRevenue,
                        type: 'line',
                        borderColor: 'rgba(13, 110, 253, 0.9)',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    y1: {
                        position: 'right',
                        title: {
                            display: true,
                            text: '₱ Revenue'
                        },
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // ---- 2. Order Status Breakdown (Doughnut) ----
        const statusData = <?php
                            $statusMap = ['pending' => 0, 'billed' => 0, 'partial' => 0, 'paid' => 0];
                            foreach ($statusBreakdown as $row) {
                                $statusMap[$row['status']] = (int)$row['count'];
                            }
                            echo json_encode(array_values($statusMap));
                            ?>;
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Billed', 'Partial', 'Paid'],
                datasets: [{
                    data: statusData,
                    backgroundColor: ['#ffc107', '#0d6efd', '#fd7e14', '#198754'],
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // ---- 3. Top Selling Items (Horizontal Bar) ----
        const topItemsLabels = <?php echo json_encode(array_column($topSellingItems, 'itemName')); ?>;
        const topItemsQty = <?php echo json_encode(array_map('intval', array_column($topSellingItems, 'totalQty'))); ?>;
        const topItemsRevenue = <?php echo json_encode(array_map('floatval', array_column($topSellingItems, 'totalRevenue'))); ?>;

        new Chart(document.getElementById('topItemsChart'), {
            type: 'bar',
            data: {
                labels: topItemsLabels,
                datasets: [{
                        label: 'Qty Sold',
                        data: topItemsQty,
                        backgroundColor: 'rgba(25, 135, 84, 0.75)',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Revenue (₱)',
                        data: topItemsRevenue,
                        backgroundColor: 'rgba(13, 110, 253, 0.6)',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                scales: {
                    y: {
                        stacked: false
                    },
                    y1: {
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: '₱'
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>