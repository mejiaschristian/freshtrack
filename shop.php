<?php
session_start();
include 'db.php';
require_once 'auth.php';
require_once 'cron_process_recurring.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== "hotel") {
    header('Location: dashboard.php');
    exit();
}
// AUTOMATIC TRIGGER RUNTIME ENGINE CHECKER
// Every time a page loads, this parses background subscriptions to ensure everything is up to date
processAutomaticRecurringBatches($pdo);

$search   = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';

$sql = "SELECT tblItems.*, tblcategories.categoryName,
               COALESCE(b.totalQty, 0) AS itemQuantity
        FROM tblItems
        JOIN tblcategories ON tblItems.categoryID = tblcategories.categoryID
        LEFT JOIN (
            SELECT itemID, SUM(quantity) AS totalQty
            FROM tblItemBatches
            WHERE quantity > 0 AND (batchStatus = 'active' AND batchStatus != 'archived')
            GROUP BY itemID
        ) b ON tblItems.itemID = b.itemID
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql     .= " AND (tblItems.itemName LIKE :search OR tblItems.itemDescription LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if (!empty($category)) {
    $sql     .= " AND tblItems.categoryID = :category";
    $params['category'] = $category;
}
$sql .= " ORDER BY tblItems.itemDateAdded DESC";

$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------
// Fetch ALL active batches for every item, sorted by expiry (FIFO)
// ---------------------------------------------------------------
$itemBatchesMap = [];
$fifoExpiryMap  = [];

if (!empty($items)) {
    $ids = implode(',', array_map('intval', array_column($items, 'itemID')));

    // Query all active batches with quantity > 0, ordered by closest expiry
    $batchRows = $pdo->query("
        SELECT   itemID, expiryDate, quantity
        FROM     tblItemBatches
        WHERE    itemID IN ($ids)
          AND    quantity > 0 AND (batchStatus = 'active' AND batchStatus != 'archived')
        ORDER BY itemID, expiryDate ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($batchRows as $b) {
        $itemBatchesMap[$b['itemID']][] = [
            'expiryDate' => $b['expiryDate'],
            'quantity'   => (int)$b['quantity']
        ];
    }

    // Determine FIFO expiry for UI display on the card grid
    foreach ($itemBatchesMap as $itemId => $batches) {
        if (!empty($batches)) {
            $fifoExpiryMap[$itemId] = $batches[0]['expiryDate'];
        }
    }

    // Fallback to tblItems.itemExpiryDate for items with no batch row yet
    foreach ($items as $row) {
        if (!isset($fifoExpiryMap[$row['itemID']])) {
            $fifoExpiryMap[$row['itemID']] = null;
        }
        // Ensure every item has at least an empty array array payload for batches
        if (!isset($itemBatchesMap[$row['itemID']])) {
            $itemBatchesMap[$row['itemID']] = [];
        }
    }
}

$successItem = $_GET['success_item'] ?? '';
$successQty  = $_GET['success_qty']  ?? '';
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Store</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/x-icon" href="favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>

    <header class="sticky-top">
        <nav class="navbar navbar-expand-lg navbar-dark bg-success">
            <div class="container-lg">
                <a class="navbar-brand me-auto" href="shop.php">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid" />
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapsibleNavId" aria-controls="collapsibleNavId"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="collapsibleNavId">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item"><a class="nav-link active" href="shop.php">Shop</a><span class="visually-hidden">(current)</span></li>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="hotel_orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="recurring_orders.php">Recurring</a></li>
                        <li class="nav-item"><a class="nav-link" href="bill.php">Transactions</a></li>
                        <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 border-start border-1 ms-3 px-3"
                                href="#" id="dropdownId" data-bs-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <img src="user-icon.svg" alt="user-icon" width="35">
                                <span><?php echo $_SESSION['username'] ?? 'Guest'; ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownId">
                                <a class="dropdown-item" href="index.php">
                                    <i class="bi bi-box-arrow-right"></i> Log Out
                                </a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="modal fade" id="addToCartModal" tabindex="-1" aria-labelledby="addToCartLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addToCartLabel">Add to Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="cart_action.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="itemID" id="modal_itemID">

                        <div class="d-flex gap-3 mb-3">
                            <img id="modal_itemImage" src="" alt="Item Image" class="rounded"
                                style="width:100px;height:100px;object-fit:cover;">
                            <div>
                                <h5 id="modal_itemName" class="mb-1"></h5>
                                <p id="modal_itemDescription" class="text-muted small mb-1"></p>
                                <span class="badge bg-success" id="modal_itemCategory"></span>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Price per unit:</span>
                            <strong id="modal_itemPrice"></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Available total stock:</span>
                            <strong id="modal_itemQuantity"></strong>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted small fw-bold d-block mb-2">Available Batch Freshness</span>
                            <div id="modal_batches_container" class="d-flex flex-column gap-2" style="max-height: 200px; overflow-y: auto;">
                            </div>
                            <div class="text-muted mt-2" style="font-size: 0.75rem; font-style: italic;">
                                ℹ Orders are fulfilled using FIFO (oldest stock first).
                            </div>
                        </div>

                        <div class="form-floating">
                            <input type="number" class="form-control" id="modal_quantity"
                                name="quantity" value="1" min="1" required>
                            <label for="modal_quantity">Quantity</label>
                        </div>
                        <div class="mt-3 text-end">
                            <span>Subtotal: </span>
                            <strong id="modal_subtotal">₱0.00</strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success w-100">Add to Cart</button>
                        <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <main>
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <a href="cart.php" class="text-decoration-none">
                <div id="cartToast" class="toast align-items-center text-bg-success border-0"
                    role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body" id="cartToastMessage">Item added to cart!</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </a>
            <div id="errorToast" class="toast align-items-center text-bg-danger border-0"
                role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body" id="errorToastMessage">
                        ⚠️ Cannot add item: Total quantity exceeds available batch stocks!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>

        <div class="container-lg mt-5">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h2 class="mb-1">Welcome, <?php echo $_SESSION['username'] ?? 'Guest'; ?>!</h2>
                    <p class="text-muted">Browse and purchase fresh items for your hotel!</p>
                </div>
                <span class="badge bg-success fs-6"><?php echo count($items); ?> items</span>
            </div>

            <form method="GET" action="shop.php" class="card card-body mb-4 bg-light border-0 shadow-sm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label text-muted small mb-1">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search"
                                placeholder="Search items…"
                                value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-success" type="submit">
                                <img src="search.svg" alt="Search" width="18">
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted small mb-1">Category</label>
                        <select class="form-select" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <option value="3" <?php echo $category == '3' ? 'selected' : ''; ?>>Fruits</option>
                            <option value="2" <?php echo $category == '2' ? 'selected' : ''; ?>>Vegetables</option>
                            <option value="1" <?php echo $category == '1' ? 'selected' : ''; ?>>Dairy</option>
                            <option value="4" <?php echo $category == '4' ? 'selected' : ''; ?>>Beverages</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <a href="shop.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </div>
                <?php if (!empty($search) || !empty($category)): ?>
                    <div class="mt-2 small text-muted">
                        Showing results
                        <?php if (!empty($search)): ?>for "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
                        <?php if (!empty($category)):
                            $catNames = ['1' => 'Dairy', '2' => 'Vegetables', '3' => 'Fruits', '4' => 'Beverages'];
                        ?>in <strong><?php echo $catNames[$category] ?? ''; ?></strong><?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>

            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">No items found.</h5>
                    <a href="shop.php" class="btn btn-success mt-3">Browse All Items</a>
                </div>
            <?php else: ?>
                <div class="items-row row row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-2 g-md-3 g-lg-3">
                    <?php foreach ($items as $row):
                        $fifoExpiry = $fifoExpiryMap[$row['itemID']] ?? null;
                        $today      = strtotime('today');
                        $daysLeft   = $fifoExpiry ? round((strtotime($fifoExpiry) - $today) / 86400) : null;

                        // Determine expiry badge style
                        $expiryClass = 'expiry-ok';
                        $expiryLabel = '';
                        if ($fifoExpiry) {
                            $expiryLabel = date('M d, Y', strtotime($fifoExpiry));
                            if ($daysLeft <= 0) {
                                $expiryClass = 'expiry-crit';
                                $expiryLabel .= ' · EXPIRED';
                            } elseif ($daysLeft <= 3) {
                                $expiryClass = 'expiry-crit';
                                $expiryLabel .= ' · ' . $daysLeft . 'd left';
                            } elseif ($daysLeft <= 7) {
                                $expiryClass = 'expiry-soon';
                                $expiryLabel .= ' · ' . $daysLeft . 'd left';
                            }
                        }
                    ?>
                        <?php if (!($daysLeft !== null && $daysLeft <= 0)): ?>
                            <div class="col d-flex justify-content-center">
                                <div class="item-card card w-75 shadow-sm d-flex flex-column">
                                    <?php if (!empty($row['itemImage'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['itemImage']); ?>"
                                            class="card-img-top"
                                            style="height:120px; object-fit:cover;"
                                            alt="<?php echo htmlspecialchars($row['itemName']); ?>">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="height:100px;">
                                            <span class="text-muted small">No image</span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="card-body d-flex flex-column p-2 flex-grow-1">
                                        <span class="badge bg-success-subtle text-success mb-1 align-self-start small">
                                            <?php echo htmlspecialchars($row['categoryName']); ?>
                                        </span>
                                        <h6 class="card-title small mb-1"><?php echo htmlspecialchars($row['itemName']); ?></h6>
                                        <p class="card-text text-muted small mb-2" style="font-size:0.75rem;">
                                            <?php echo htmlspecialchars(substr($row['itemDescription'], 0, 40)); ?>…
                                        </p>
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong class="text-success small">₱<?php echo number_format($row['itemPrice'], 2); ?></strong>
                                                <small class="text-muted" style="font-size:0.7rem;">per <?php echo htmlspecialchars($row['itemUnit']); ?></small>
                                            </div>
                                            <small class="text-muted">Stock: <?php echo $row['itemQuantity']; ?> <?php echo htmlspecialchars($row['itemUnit']); ?></small>
                                            <br>
                                            <?php if ($row['reorderLevel'] > 0): ?>
                                                <small class="badge bg-success-subtle text-muted">Items sold: <?php echo $row['reorderLevel']; ?></small>
                                            <?php endif; ?>
                                            <br>
                                            <?php if ($fifoExpiry): ?>
                                                <span class="expiry-badge <?php echo $expiryClass; ?> mt-1">
                                                    ED: <?php echo $expiryLabel; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="card-footer bg-white border-0 p-1">
                                        <?php if ($row['itemQuantity'] > 0 && ($daysLeft === null || $daysLeft > 0)): ?>
                                            <button class="btn btn-success btn-sm w-100"
                                                onclick='openCartModal(<?php echo json_encode(array_merge($row, ["batches" => $itemBatchesMap[$row['itemID']]])); ?>)'>
                                                Add to Cart
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm w-100" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script>
        function openCartModal(item) {
            document.getElementById('modal_itemID').value = item.itemID;
            document.getElementById('modal_itemName').textContent = item.itemName;
            document.getElementById('modal_itemDescription').textContent = item.itemDescription;
            document.getElementById('modal_itemCategory').textContent = item.categoryName ?? '';
            document.getElementById('modal_itemPrice').textContent =
                '₱' + parseFloat(item.itemPrice).toFixed(2) + ' / ' + item.itemUnit;
            document.getElementById('modal_itemQuantity').textContent =
                item.itemQuantity + ' ' + item.itemUnit;
            document.getElementById('modal_itemImage').src = item.itemImage || 'placeholder.png';

            // Build the Active Batches layout dynamically
            const container = document.getElementById('modal_batches_container');
            container.innerHTML = '';

            if (item.batches && item.batches.length > 0) {
                item.batches.forEach((batch, index) => {
                    const batchDate = new Date(batch.expiryDate);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    // Calculate exact differences in days
                    const diffTime = batchDate - today;
                    const diffDays = Math.round(diffTime / 86400000);

                    // Set styling based on expiry rules
                    let badgeClass = 'text-bg-success';
                    let statusText = `${diffDays} days left`;

                    if (diffDays <= 0) {
                        badgeClass = 'text-bg-danger';
                        statusText = 'Expired';
                    } else if (diffDays <= 3) {
                        badgeClass = 'text-bg-danger';
                        statusText = `${diffDays} days left`;
                    } else if (diffDays <= 7) {
                        badgeClass = 'text-bg-warning';
                        statusText = `${diffDays} days left`;
                    }

                    const formattedDate = batchDate.toLocaleDateString('en-PH', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });

                    // Construct row UI
                    const batchRow = document.createElement('div');
                    batchRow.className = 'p-2 border rounded bg-white d-flex justify-content-between align-items-center shadow-sm';

                    let fifoBadgeHtml = index === 0 ? '<span class="badge text-bg-secondary ms-1" style="font-size:0.65rem;">FIFO First</span>' : '';

                    batchRow.innerHTML = `
                <div>
                    <div class="fw-semibold small">Expires: ${formattedDate} ${fifoBadgeHtml}</div>
                    <div class="text-muted small">Remaining: ${batch.quantity} ${item.itemUnit}</div>
                </div>
                <span class="badge ${badgeClass}">${statusText}</span>
            `;
                    container.appendChild(batchRow);
                });
            } else {
                container.innerHTML = '<div class="text-muted small p-2 border rounded bg-light">No explicit batch information available.</div>';
            }

            const qtyInput = document.getElementById('modal_quantity');
            qtyInput.value = 1;
            qtyInput.max = item.itemQuantity;
            document.getElementById('modal_subtotal').textContent = '₱' + parseFloat(item.itemPrice).toFixed(2);

            qtyInput.oninput = function() {
                const total = this.value * parseFloat(item.itemPrice);
                document.getElementById('modal_subtotal').textContent = '₱' + total.toFixed(2);
            };

            new bootstrap.Modal(document.getElementById('addToCartModal')).show();
        }

        // Cart success toast
        // Cart feedback messages (Success or Error)
        const urlParams = new URLSearchParams(window.location.search);
        const successItem = urlParams.get('success_item');
        const successQty = urlParams.get('success_qty');
        const errorCode = urlParams.get('error');

        if (successItem) {
            const toastEl = document.getElementById('cartToast');
            document.getElementById('cartToastMessage').textContent =
                successItem + ' x' + successQty + ' successfully added to cart!';
            new bootstrap.Toast(toastEl, {
                delay: 4000
            }).show();
        }

        // ⚠️ Check if backend rejected the addition due to stock limits
        if (errorCode === 'insufficient_stock') {
            const errorToastEl = document.getElementById('errorToast');
            document.getElementById('errorToastMessage').innerHTML =
                '<strong>⚠️ Stock Limit Reached:</strong> You cannot add that amount. The quantity matches or exceeds what is currently available across all fresh batches, including what is already sitting inside your shopping cart.';
            new bootstrap.Toast(errorToastEl, {
                delay: 6000
            }).show();
        } else if (errorCode === 'invalid_qty') {
            const errorToastEl = document.getElementById('errorToast');
            document.getElementById('errorToastMessage').textContent = '⚠️ Please enter a valid quantity of 1 or more.';
            new bootstrap.Toast(errorToastEl, {
                delay: 4000
            }).show();
        }
    </script>
</body>

</html>