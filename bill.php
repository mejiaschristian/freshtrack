<?php
session_start();
require_once 'db.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$billID = $_GET['billID'] ?? null;

// If specific bill requested, fetch it; otherwise show all bills for this user
if ($billID) {
    // Single bill view
    $stmt = $pdo->prepare("
        SELECT tblBills.*, tblusers.fullName 
        FROM tblBills 
        JOIN tblusers ON tblBills.userID = tblusers.userID 
        WHERE tblBills.billID = :billID AND tblBills.userID = :userID
    ");
    $stmt->execute(['billID' => $billID, 'userID' => $userID]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        header('Location: bill.php');
        exit();
    }

    // Check and apply penalty if overdue
    if ($bill['status'] === 'unpaid' && strtotime($bill['dueDate']) < strtotime('today')) {
        $penalty = $bill['totalAmount'] * 0.05; // 5% penalty
        $pdo->prepare("UPDATE tblBills SET penaltyAmount = :penalty WHERE billID = :billID")
            ->execute(['penalty' => $penalty, 'billID' => $billID]);
        $bill['penaltyAmount'] = $penalty;
    }

    // Get orders under this bill
    $stmt = $pdo->prepare("
        SELECT tblOrders.orderID, tblOrders.orderDate, tblOrders.totalAmount, tblOrders.orderType
        FROM tblBillOrders
        JOIN tblOrders ON tblBillOrders.orderID = tblOrders.orderID
        WHERE tblBillOrders.billID = :billID
    ");
    $stmt->execute(['billID' => $billID]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all items under each order
    $orderItems = [];
    foreach ($orders as $order) {
        $stmt = $pdo->prepare("
            SELECT tblOrderItems.*, tblItems.itemName, tblItems.itemUnit
            FROM tblOrderItems
            JOIN tblItems ON tblOrderItems.itemID = tblItems.itemID
            WHERE tblOrderItems.orderID = :orderID
        ");
        $stmt->execute(['orderID' => $order['orderID']]);
        $orderItems[$order['orderID']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// All bills for this user
$stmt = $pdo->prepare("SELECT * FROM tblBills WHERE userID = :userID ORDER BY billDate DESC");
$stmt->execute(['userID' => $userID]);
$allBills = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">

<head>
    <title>FreshTrack - Bills</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <header class="sticky-top">
        <nav class="navbar navbar-expand-sm navbar-dark bg-success">
            <div class="w-75 container-lg">
                <a class="navbar-brand me-auto" href="#">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid d-block w-auto z-1 mt-2 mx-5" />
                </a>
                <div class="mx-5 p-2 bg-white rounded-5 d-flex align-items-center justify-content-center text-center">
                    <p class="mb-0"><b>Hotel Name:</b> <?php echo $_SESSION['username'] ?? 'Guest'; ?></p>
                </div>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <li class="nav-item">
                            <a class="nav-link" href="hotel_orders.php">Orders
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link active" href="bill.php">Transactions</a></li>
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

    <main class="container-lg mt-5">

        <?php if ($billID && $bill): ?>
            <!-- ===== SINGLE BILL VIEW ===== -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">🧾 Bill Receipt</h4>
                                <small><?php echo htmlspecialchars($bill['billNumber']); ?></small>
                            </div>
                            <span class="badge fs-6 
                            <?php echo $bill['status'] === 'paid' ? 'bg-light text-success' : ($bill['status'] === 'partial' ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                <?php echo strtoupper($bill['status']); ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <!-- Hotel & Bill Info -->
                            <div class="row mb-4">
                                <div class="col">
                                    <p class="mb-1"><strong>Hotel:</strong> <?php echo htmlspecialchars($bill['fullName']); ?></p>
                                    <p class="mb-1"><strong>Bill Date:</strong> <?php echo date('F d, Y', strtotime($bill['billDate'])); ?></p>
                                </div>
                                <div class="col text-end">
                                    <p class="mb-1"><strong>Due Date:</strong>
                                        <span class="<?php echo strtotime($bill['dueDate']) < strtotime('today') && $bill['status'] !== 'paid' ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('F d, Y', strtotime($bill['dueDate'])); ?>
                                        </span>
                                    </p>
                                    <?php if (strtotime($bill['dueDate']) < strtotime('today') && $bill['status'] !== 'paid'): ?>
                                        <span class="badge bg-danger">OVERDUE</span>
                                    <?php else: ?>
                                        <p class="mb-1 text-muted small">Due in <?php echo ceil((strtotime($bill['dueDate']) - strtotime('today')) / 86400); ?> days</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr>

                            <!-- Order Items -->
                            <?php foreach ($orders as $order): ?>
                                <p class="text-muted small mb-1">
                                    Order #<?php echo $order['orderID']; ?> —
                                    <?php echo date('F d, Y h:i A', strtotime($order['orderDate'])); ?>
                                </p>
                                <table class="table table-bordered table-sm mb-4">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Item</th>
                                            <th>Unit Price</th>
                                            <th>Qty</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orderItems[$order['orderID']] as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['itemName']); ?></td>
                                                <td>₱<?php echo number_format($item['price'], 2); ?> / <?php echo htmlspecialchars($item['itemUnit']); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="bg-light">
                                                <strong>Order Type:</strong> <?php echo htmlspecialchars(ucfirst($order['orderType'] ?? 'N/A')); ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php endforeach; ?>

                            <hr>

                            <!-- Totals -->
                            <div class="d-flex justify-content-end">
                                <table class="table w-auto text-end">
                                    <tr>
                                        <td class="pe-4">Subtotal:</td>
                                        <td><strong>₱<?php echo number_format($bill['totalAmount'], 2); ?></strong></td>
                                    </tr>
                                    <?php if ($bill['penaltyAmount'] > 0): ?>
                                        <tr class="text-danger">
                                            <td class="pe-4">Penalty (5%):</td>
                                            <td><strong>₱<?php echo number_format($bill['penaltyAmount'], 2); ?></strong></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr class="fs-5">
                                        <td class="pe-4">Total Due:</td>
                                        <td><strong>₱<?php echo number_format($bill['totalAmount'] + $bill['penaltyAmount'], 2); ?></strong></td>
                                    </tr>
                                </table>
                            </div>

                            <?php if ($bill['status'] !== 'paid'): ?>
                                <div class="alert alert-info mt-3">
                                    💡 You may pay this bill in full or request partial payment. Contact FreshTrack to process your payment.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer d-flex justify-content-between">
                            <a href="bill.php" class="btn btn-outline-secondary">← Back to Bills</a>
                            <button onclick="window.print()" class="btn btn-success">🖨️ Print Receipt</button>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ===== ALL BILLS LIST ===== -->
            <h2>My Bills</h2>
            <p>View and track all your bills here.</p>

            <?php if (empty($allBills)): ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">No bills yet.</h5>
                    <a href="shop.php" class="btn btn-success mt-3">Start Shopping</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Bill Number</th>
                                    <th>Bill Date</th>
                                    <th>Due Date</th>
                                    <th>Total</th>
                                    <th>Penalty</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allBills as $b): ?>
                                    <tr class="<?php echo strtotime($b['dueDate']) < strtotime('today') && $b['status'] === 'unpaid' ? 'table-danger' : ''; ?>">
                                        <td><strong><?php echo htmlspecialchars($b['billNumber']); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($b['billDate'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($b['dueDate'])); ?></td>
                                        <td>₱<?php echo number_format($b['totalAmount'], 2); ?></td>
                                        <td><?php echo $b['penaltyAmount'] > 0 ? '₱' . number_format($b['penaltyAmount'], 2) : '—'; ?></td>
                                        <td>
                                            <span class="badge 
                                            <?php echo $b['status'] === 'paid' ? 'bg-success' : ($b['status'] === 'partial' ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                <?php echo strtoupper($b['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="bill.php?billID=<?php echo $b['billID']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>

</html>