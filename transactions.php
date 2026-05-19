<?php
session_start();
require_once 'auth.php';
require_once 'functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$message = "";
$messageType = "";

// Handle add transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        try {
            $itemID = $_POST['itemID'];
            $transactionType = $_POST['transactionType'];
            $quantity = $_POST['quantity'];
            $remarks = $_POST['remarks'] ?? '';

            if (!empty($itemID) && !empty($transactionType) && !empty($quantity)) {
                // Insert transaction
                $stmt = $pdo->prepare("
                    INSERT INTO tblstocktransactions (itemID, userID, transactionType, quantity, remarks) 
                    VALUES (:itemID, :userID, :transactionType, :quantity, :remarks)
                ");
                $stmt->execute([
                    ':itemID' => $itemID,
                    ':userID' => $_SESSION['user_id'],
                    ':transactionType' => $transactionType,
                    ':quantity' => $quantity,
                    ':remarks' => $remarks
                ]);

                // Update inventory
                if ($transactionType === 'IN') {
                    $updateStmt = $pdo->prepare("UPDATE tblitems SET itemQuantity = itemQuantity + :quantity WHERE itemID = :itemID");
                } else {
                    $updateStmt = $pdo->prepare("UPDATE tblitems SET itemQuantity = itemQuantity - :quantity WHERE itemID = :itemID");
                }
                $updateStmt->execute([
                    ':quantity' => $quantity,
                    ':itemID' => $itemID
                ]);

                $message = "Stock transaction recorded successfully!";
                $messageType = "success";
            } else {
                $message = "Please fill in all required fields.";
                $messageType = "warning";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

$transactions = getAllTransactions($pdo);
$items = getAllItems($pdo);
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Stock Transactions</title>
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
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid"/>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="inventory.php">Inventory</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="purchases.php">Purchases</a></li>
                        <li class="nav-item"><a class="nav-link active" href="transactions.php">Transactions</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container-fluid py-4 w-75">
        <div class="row mb-4">
            <div class="col">
                <h1 class="mb-0">Stock Transactions</h1>
                <p class="text-muted">Track all stock movements (IN/OUT)</p>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Transaction Button -->
        <div class="mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                + Record Transaction
            </button>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Transaction History</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>User</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $trans): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($trans['transactionDate'])); ?></td>
                                    <td><?php echo htmlspecialchars($trans['itemName']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $trans['transactionType'] === 'IN' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $trans['transactionType']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $trans['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($trans['fullName']); ?></td>
                                    <td><?php echo htmlspecialchars($trans['remarks'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Record Stock Transaction</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label for="itemID" class="form-label">Item</label>
                            <select id="itemID" name="itemID" class="form-control" required>
                                <option value="">Select Item</option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['itemID']; ?>">
                                        <?php echo htmlspecialchars($item['itemName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="transactionType" class="form-label">Transaction Type</label>
                            <select id="transactionType" name="transactionType" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="IN">Stock In (Received)</option>
                                <option value="OUT">Stock Out (Sold/Used)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
                        </div>

                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks (Optional)</label>
                            <textarea id="remarks" name="remarks" class="form-control" rows="3" placeholder="Add any notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>