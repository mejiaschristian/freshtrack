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

// Handle add purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        try {
            $totalCost = 0;

            // Insert purchase
            $purchaseStmt = $pdo->prepare("INSERT INTO tblpurchases (userID, totalCost) VALUES (:userID, :totalCost)");
            $purchaseStmt->execute([
                ':userID' => $_SESSION['user_id'],
                ':totalCost' => $totalCost
            ]);

            $purchaseID = $pdo->lastInsertId();
            $items = $_POST['items'] ?? [];

            // Insert purchase details
            if (!empty($items)) {
                $detailStmt = $pdo->prepare("INSERT INTO tblpurchasedetails (purchaseID, itemID, quantity, unitCost) VALUES (:purchaseID, :itemID, :quantity, :unitCost)");

                foreach ($items as $itemID => $data) {
                    if (!empty($data['quantity']) && !empty($data['unitCost'])) {
                        $quantity = $data['quantity'];
                        $unitCost = $data['unitCost'];
                        $subtotal = $quantity * $unitCost;
                        $totalCost += $subtotal;

                        $detailStmt->execute([
                            ':purchaseID' => $purchaseID,
                            ':itemID' => $itemID,
                            ':quantity' => $quantity,
                            ':unitCost' => $unitCost
                        ]);

                        // Update inventory
                        $updateStmt = $pdo->prepare("UPDATE tblitems SET itemQuantity = itemQuantity + :quantity WHERE itemID = :itemID");
                        $updateStmt->execute([
                            ':quantity' => $quantity,
                            ':itemID' => $itemID
                        ]);
                    }
                }
            }

            // Update total cost
            $updatePurchaseStmt = $pdo->prepare("UPDATE tblpurchases SET totalCost = :totalCost WHERE purchaseID = :purchaseID");
            $updatePurchaseStmt->execute([
                ':totalCost' => $totalCost,
                ':purchaseID' => $purchaseID
            ]);

            $message = "Purchase order created successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

$purchases = getAllPurchases($pdo);
$items = getAllItems($pdo);
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Purchases</title>
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
                        <li class="nav-item"><a class="nav-link active" href="purchases.php">Purchases</a></li>
                        <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="container-fluid py-4 w-75">
        <div class="row mb-4">
            <div class="col">
                <h1 class="mb-0">Purchases</h1>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Purchase Button -->
        <div class="mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
                + New Purchase Order
            </button>
        </div>

        <!-- Purchases Table -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Purchase History</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Purchase ID</th>
                            <th>Date</th>
                            <th>User</th>
                            <th>Total Cost</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($purchases)): ?>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td>#<?php echo $purchase['purchaseID']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($purchase['purchaseDate'])); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['fullName']); ?></td>
                                    <td><strong>₱<?php echo number_format($purchase['totalCost'], 2); ?></strong></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal" onclick="viewPurchase(<?php echo $purchase['purchaseID']; ?>)">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No purchases found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Purchase Modal -->
    <div class="modal fade" id="addPurchaseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">New Purchase Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div id="purchaseItems">
                            <div class="purchase-item mb-3">
                                <div class="row">
                                    <div class="col-md-5">
                                        <label class="form-label">Item</label>
                                        <select name="items[1][itemID]" class="form-control" required>
                                            <option value="">Select Item</option>
                                            <?php foreach ($items as $item): ?>
                                                <option value="<?php echo $item['itemID']; ?>"><?php echo htmlspecialchars($item['itemName']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" name="items[1][quantity]" class="form-control" min="1" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Unit Cost</label>
                                        <input type="number" name="items[1][unitCost]" class="form-control" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addPurchaseItem()">+ Add Item</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Purchase Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let itemCount = 1;

        function addPurchaseItem() {
            itemCount++;
            const items = document.getElementById('purchaseItems');
            const newItem = document.createElement('div');
            newItem.className = 'purchase-item mb-3';
            newItem.innerHTML = `
                <div class="row">
                    <div class="col-md-5">
                        <select name="items[${itemCount}][itemID]" class="form-control" required>
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo $item['itemID']; ?>"><?php echo htmlspecialchars($item['itemName']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="number" name="items[${itemCount}][quantity]" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" name="items[${itemCount}][unitCost]" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
            `;
            items.appendChild(newItem);
        }
    </script>
</body>

</html>