<?php
session_start();
require_once 'auth.php';
require_once 'db.php';

// DEBUG: Check if get_bill_details.php exists
if (!file_exists('get_bill_details.php')) {
    echo "<!-- WARNING: get_bill_details.php NOT FOUND in " . getcwd() . " -->";
}

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$message = "";
$messageType = "";

// Fetch all bills with customer information
$stmt = $pdo->prepare("
    SELECT tblBills.*, tblusers.fullName
    FROM tblBills
    INNER JOIN tblBillOrders ON tblBills.billID = tblBillOrders.billID
    INNER JOIN tblOrders ON tblBillOrders.orderID = tblOrders.orderID
    INNER JOIN tblusers ON tblBills.userID = tblusers.userID
    WHERE tblOrders.status != 'pending'
    ORDER BY tblBills.billDate DESC
");
$stmt->execute();
$allBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Bills & Transactions</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <!-- Navbar -->
    <header class="sticky-top">
        <nav class="navbar navbar-expand-sm navbar-dark bg-success">
            <div class="container-fluid w-75">
                <a class="navbar-brand me-auto" href="dashboard.php">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid" />
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="inventory.php">Inventory</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link active" href="transactions.php">Transactions</a></li>
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

    <main class="container-fluid mt-5 w-75">
        <h2 class="mb-1">Bills & Transactions</h2>
        <p class="text-muted">View all customer bills and transactions</p>

        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Bills Table -->
        <div class="card mt-2">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">All Bills</h5>
                <span class="badge bg-light text-success"><?php echo count($allBills); ?> Bills</span>
            </div>
            <div class="cards-cont card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Bill ID</th>
                            <th>Bill Number</th>
                            <th>Customer</th>
                            <th>Bill Date</th>
                            <th>Due Date</th>
                            <th>Total Amount</th>
                            <th>Penalty</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allBills)): ?>
                            <?php foreach ($allBills as $b): ?>
                                <tr class="<?php echo strtotime($b['dueDate']) < strtotime('today') && $b['status'] === 'unpaid' ? 'table-danger' : ''; ?>">
                                    <td><?php echo htmlspecialchars($b['billID']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($b['billNumber']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($b['fullName']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($b['billDate'])); ?></td>
                                    <td>
                                        <span class="<?php echo strtotime($b['dueDate']) < strtotime('today') && $b['status'] !== 'paid' ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('M d, Y', strtotime($b['dueDate'])); ?>
                                        </span>
                                    </td>
                                    <td>₱<?php echo number_format($b['totalAmount'], 2); ?></td>
                                    <td>
                                        <?php echo $b['penaltyAmount'] > 0 ? '₱' . number_format($b['penaltyAmount'], 2) : '—'; ?>
                                    </td>
                                    <td>
                                        <span class="badge
                                        <?php
                                        if ($b['status'] === 'paid') {
                                            echo 'bg-success';
                                        } elseif ($b['status'] === 'partial') {
                                            echo 'bg-warning text-dark';
                                        } else {
                                            echo 'bg-danger';
                                        }
                                        ?>">
                                            <?php echo strtoupper($b['status']); ?>
                                        </span>
                                        <?php if (strtotime($b['dueDate']) < strtotime('today') && $b['status'] !== 'paid'): ?>
                                            <br><small class="text-danger">OVERDUE</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewBill(<?php echo $b['billID']; ?>)">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No bills found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Bills</h6>
                        <h3 class="text-success"><?php echo count($allBills); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Paid</h6>
                        <h3 class="text-success"><?php echo count(array_filter($allBills, fn($b) => $b['status'] === 'paid')); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Unpaid</h6>
                        <h3 class="text-danger"><?php echo count(array_filter($allBills, fn($b) => $b['status'] === 'unpaid')); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Revenue</h6>
                        <?php
                        $totalRevenue = array_sum(
                            array_map(
                                fn($b) =>
                                in_array($b['status'], ['paid', 'partial'])
                                    ? $b['totalAmount']
                                    : 0,
                                $allBills
                            )
                        );
                        ?>

                        <h3 class="text-primary">
                            ₱<?php echo number_format($totalRevenue, 2); ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>

</html>