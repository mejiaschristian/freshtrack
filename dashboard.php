<?php
session_start();
require_once 'auth.php';
require_once 'functions.php';
require_once 'cron_process_recurring.php';

// Check if user is logged in and is admin or staff
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
    header('Location: dashboard.php');
    exit();
}

// AUTOMATIC TRIGGER RUNTIME ENGINE CHECKER
// Every time a page loads, this parses background subscriptions to ensure everything is up to date
processAutomaticRecurringBatches($pdo);

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
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item"><a class="nav-link active" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="inventory.php">Inventory</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
                        <?php endif; ?>
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
                <a href="orders.php" class="text-decoration-none">
                    <div class="card dashboard-card border-left-success">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Total Orders</h6>
                            <h3 class="card-text text-success"><?php echo $stats['totalOrders']; ?></h3>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="orders.php" class="text-decoration-none">
                    <div class="card dashboard-card border-left-warning">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Pending Orders</h6>
                            <h3 class="card-text text-warning"><?php echo $stats['pendingOrders']; ?></h3>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="inventory.php" class="text-decoration-none">
                    <div class="card dashboard-card border-left-info">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Total Items</h6>
                            <h3 class="card-text text-info"><?php echo $stats['totalItems']; ?></h3>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <a href="inventory.php" class="text-decoration-none">
                    <div class="card dashboard-card border-left-danger">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Low Stock Items</h6>
                            <h3 class="card-text text-danger"><?php echo $stats['lowStockCount']; ?></h3>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="users.php" class="text-decoration-none">
                    <div class="card dashboard-card border-left-success">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Total Users</h6>
                            <h3 class="card-text text-success"><?php echo $stats['totalUsers']; ?></h3>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4 mb-3">
                <a href="inventory.php" class="text-decoration-none">
                    <div class="card dashboard-card border-left-primary">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Inventory Value</h6>
                            <h3 class="card-text text-primary">₱<?php echo number_format($stats['inventoryValue'], 2); ?></h3>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Alerts Section -->
        <?php if (!empty($lowStockItems)): ?>
            <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
                <h5 class="alert-heading">⚠️ Low Stock Alert</h5>
                <p class="mb-2"><?php echo count($lowStockItems); ?> item(s) are running low on stock:</p>
                <div style="max-height: 150px; overflow-y: auto; padding-right: 10px;">
                    <ul class="mb-0">
                        <?php foreach ($lowStockItems as $item): ?>
                            <li><strong><?php echo htmlspecialchars($item['itemName'] ?? $item['itemname'] ?? 'Item'); ?></strong> - Only <?php echo $item['itemQuantity'] ?? $item['itemquantity'] ?? 0; ?> left</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($expiringItems)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <h5 class="alert-heading">🔴 Nearing Expiration (Next 7 Days)</h5>
                <p class="mb-2"><?php echo count($expiringItems); ?> batch record(s) nearing limit:</p>
                <div style="max-height: 150px; overflow-y: auto; padding-right: 10px;">
                    <ul class="mb-0">
                        <?php foreach ($expiringItems as $batch): ?>
                            <li><?php echo htmlspecialchars($batch['batchCode'] ?? $batch['batchcode'] ?? 'N/A'); ?>: <?php echo htmlspecialchars($batch['itemName'] ?? $batch['itemname'] ?? 'Item'); ?> (Qty: <?php echo $batch['quantity']; ?>) - Expires on <?php echo date('M d, Y', strtotime($batch['expiryDate'] ?? $batch['expirydate'])); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
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
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Date Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($recentOrders)): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <?php
                                // Reference the new aliased key 'orderStatus' first
                                $currentStatus = $order['orderStatus'] ?? $order['status'] ?? 'pending';

                                // Determine visual badge colors dynamically
                                $badgeClass = 'bg-secondary';
                                if ($currentStatus === 'paid') $badgeClass = 'bg-success';
                                elseif ($currentStatus === 'pending') $badgeClass = 'bg-warning text-dark';
                                elseif ($currentStatus === 'partial') $badgeClass = 'bg-info text-dark';
                                elseif ($currentStatus === 'unpaid' || $currentStatus === 'billed') $badgeClass = 'bg-danger';
                                ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($order['orderID']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customerName']); ?></td>
                                    <td>₱<?php echo number_format($order['totalAmount'], 2); ?></td>
                                    <td>
                                        <?php $displayStatus = ($currentStatus === 'billed') ? 'unpaid' : $currentStatus; ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo strtoupper(htmlspecialchars($displayStatus)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['orderDate'])); ?></td>
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

                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-muted text-center mb-4">Weekly Sales Performance Trends</h5>

                                <?php
                                $thisWeekR = $revenueComp['thisWeekRealized'] ?? 0;
                                $lastWeekR = $revenueComp['lastWeekRealized'] ?? 0;
                                $diffR     = $thisWeekR - $lastWeekR;
                                $pctR      = $lastWeekR > 0 ? round(($diffR / $lastWeekR) * 100, 1) : null;
                                $arrowR    = $diffR >= 0 ? '▲' : '▼';
                                $colorR    = $diffR >= 0 ? 'success' : 'danger';
                                ?>
                                <div class="row justify-content-center g-0 mb-3">
                                    <div class="col-md-2"></div>
                                    <div class="col-md-4 mb-4 pb-3">
                                        <div class="small fw-semibold text-muted text-center text-uppercase mb-2">Collected Cash Revenue (Paid / Partial)</div>
                                        <div class="d-flex align-items-center justify-content-center text-center gap-4 flex-wrap">
                                            <div>
                                                <div class="text-muted small">This Week</div>
                                                <div class="fs-5 fw-bold text-success">₱<?php echo number_format($thisWeekR, 2); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Last Week</div>
                                                <div class="fs-5 fw-bold text-secondary">₱<?php echo number_format($lastWeekR, 2); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Trend</div>
                                                <div class="fs-6 fw-bold text-<?php echo $colorR; ?>">
                                                    <?php echo $arrowR; ?> ₱<?php echo number_format(abs($diffR), 2); ?>
                                                    <?php if ($pctR !== null): ?>
                                                        <span>(<?php echo ($diffR >= 0 ? '+' : ''); ?><?php echo $pctR; ?>%)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                    $thisWeekE = $revenueComp['thisWeekExpected'] ?? 0;
                                    $lastWeekE = $revenueComp['lastWeekExpected'] ?? 0;
                                    $diffE     = $thisWeekE - $lastWeekE;
                                    $pctE      = $lastWeekE > 0 ? round(($diffE / $lastWeekE) * 100, 1) : null;
                                    $arrowE    = $diffE >= 0 ? '▲' : '▼';
                                    $colorE    = $diffE >= 0 ? 'info' : 'warning text-dark';
                                    ?>
                                    <div class="col-md-4">
                                        <div class="small fw-semibold text-muted text-center text-uppercase mb-2">Expected Outbound Revenue (Unpaid / Pending)</div>
                                        <div class="d-flex align-items-center justify-content-center text-center gap-4 flex-wrap">
                                            <div>
                                                <div class="text-muted small">This Week</div>
                                                <div class="fs-5 fw-bold text-info">₱<?php echo number_format($thisWeekE, 2); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Last Week</div>
                                                <div class="fs-5 fw-bold text-secondary">₱<?php echo number_format($lastWeekE, 2); ?></div>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Trend</div>
                                                <div class="fs-6 fw-bold text-<?php echo $diffE >= 0 ? 'success' : 'danger'; ?>">
                                                    <?php echo $arrowE; ?> ₱<?php echo number_format(abs($diffE), 2); ?>
                                                    <?php if ($pctE !== null): ?>
                                                        <span>(<?php echo ($diffE >= 0 ? '+' : ''); ?><?php echo $pctE; ?>%)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2"></div>
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
                                <h5 class="card-title text-muted mb-3">Expiry / Wastage (Next 7 Days)</h5>
                                <?php
                                $totalWaste = !empty($expiringWithValue) ? array_sum(array_column($expiringWithValue, 'total_waste_value')) : 0;
                                ?>
                                <?php if (!empty($expiringWithValue)): ?>
                                    <div class="alert alert-danger py-2 mb-2">
                                        Estimated waste value: <strong>₱<?php echo number_format($totalWaste, 2); ?></strong>
                                    </div>
                                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                                        <table class="table table-sm table-hover mb-0 align-middle">
                                            <thead class="table-light sticky-top">
                                                <tr>
                                                    <th>Item Name</th>
                                                    <th class="text-center">Total Qty</th>
                                                    <th class="text-end">Est. Loss Value</th>
                                                    <th class="text-center">Batches</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($expiringWithValue as $item): ?>
                                                    <?php
                                                    $wName = $item['itemName'] ?? $item['itemname'] ?? 'Unknown';
                                                    $uniqueID = $item['itemID'] ?? rand(100, 999);
                                                    ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($wName); ?></strong></td>
                                                        <td class="text-center"><?php echo $item['total_qty']; ?></td>
                                                        <td class="text-end text-danger fw-semibold">₱<?php echo number_format($item['total_waste_value'], 2); ?></td>
                                                        <td class="text-center">
                                                            <button class="btn btn-xs btn-outline-secondary py-0 px-2" type="button" data-bs-toggle="collapse" data-bs-target="#batchDetails_<?php echo $uniqueID; ?>">
                                                                View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <tr class="collapse" id="batchDetails_<?php echo $uniqueID; ?>">
                                                        <td colspan="4" class="bg-light p-2">
                                                            <div class="mx-3 border rounded p-2 bg-white">
                                                                <table class="table table-sm table-borderless mb-0" style="font-size: 0.85rem;">
                                                                    <thead class="text-muted">
                                                                        <tr>
                                                                            <th>Batch Code</th>
                                                                            <th>Quantity</th>
                                                                            <th>Expiration Date</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($item['batches'] as $b): ?>
                                                                            <tr>
                                                                                <td><code><?php echo htmlspecialchars($b['batchCode'] ?? $b['batchcode'] ?? 'N/A'); ?></code></td>
                                                                                <td><?php echo $b['quantity']; ?></td>
                                                                                <td><?php echo date('M d, Y', strtotime($b['expiryDate'] ?? $b['expirydate'])); ?></td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted mb-0">No items expiring or past due found.</p>
                                    </div>
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
                                $days[date('Y-m-d', strtotime("-$i days"))] = ['orders' => 0, 'revenue' => 0, 'expected_revenue' => 0];
                            }
                            foreach ($ordersTrend as $row) {
                                if (isset($days[$row['day']])) {
                                    $days[$row['day']]['orders']           = (int)$row['orderCount'];
                                    $days[$row['day']]['revenue']          = (float)$row['revenue'];
                                    $days[$row['day']]['expected_revenue'] = (float)($row['expected_revenue'] ?? 0);
                                }
                            }
                            echo json_encode(array_map(fn($d) => date('D M d', strtotime($d)), array_keys($days)));
                            ?>;
        const trendOrders = <?php echo json_encode(array_column(array_values($days), 'orders')); ?>;
        const trendRevenue = <?php echo json_encode(array_column(array_values($days), 'revenue')); ?>;
        const trendExpectedRevenue = <?php echo json_encode(array_column(array_values($days), 'expected_revenue')); ?>;

        new Chart(document.getElementById('ordersTrendChart'), {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [{
                        label: 'Orders Count',
                        data: trendOrders,
                        backgroundColor: 'rgba(25, 135, 84, 0.7)',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Collected Revenue (₱)',
                        data: trendRevenue,
                        type: 'line',
                        borderColor: 'rgba(13, 110, 253, 0.9)',
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y1'
                    },
                    {
                        label: 'Expected Revenue (₱)',
                        data: trendExpectedRevenue,
                        type: 'line',
                        borderColor: 'rgba(13, 202, 240, 0.9)', // Clear Cyan / Light-Blue line
                        backgroundColor: 'transparent',
                        borderDash: [5, 5], // Dotted/Dashed stroke formatting
                        tension: 0.3,
                        fill: false,
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
                            text: 'Orders Vol'
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
                            text: 'Value (₱)'
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
                labels: ['Pending', 'Unpaid', 'Partial', 'Paid'], // Billed renamed to Unpaid
                datasets: [{
                    data: statusData,
                    backgroundColor: ['#ffc107', '#fd7e14', '#0d6efd', '#198754'],
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