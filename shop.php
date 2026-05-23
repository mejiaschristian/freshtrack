<?php
session_start();
include 'db.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Search and filter
$search   = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';

$sql    = "SELECT tblItems.*, tblcategories.categoryName FROM tblItems JOIN tblcategories ON tblItems.categoryID = tblcategories.categoryID WHERE 1=1";
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
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$successItem = $_GET['success_item'] ?? '';
$successQty  = $_GET['success_qty'] ?? '';
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Store</title>
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
            <div class="w-75 container-lg">
                <a class="navbar-brand me-auto" href="shop.php">
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
                <div class="collapse navbar-collapse" id="collapsibleNavId">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item">
                            <a class="nav-link active" href="shop.php" aria-current="page">
                                Shop
                                <span class="visually-hidden">(current)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">Cart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="hotel_orders.php">Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bill.php">Transactions</a>
                        </li>
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
    <!-- Add to Cart Modal -->
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
                            <img id="modal_itemImage" src="" alt="Item Image" class="rounded" style="width:100px; height:100px; object-fit:cover;">
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
                            <span>Available stock:</span>
                            <strong id="modal_itemQuantity"></strong>
                        </div>

                        <div class="form-floating">
                            <input type="number" class="form-control" id="modal_quantity" name="quantity" value="1" min="1" required>
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
        <!-- Toast -->
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <a href="cart.php" class="text-decoration-none">
                <div id="cartToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body" id="cartToastMessage">Item added to cart!</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            </a>
        </div>

        <div class="container-lg mt-5">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h2 class="mb-1">Welcome, <?php echo $_SESSION['username'] ?? 'Guest'; ?>!</h2>
                    <p class="text-muted">Browse and purchase fresh items for your hotel!</p>
                </div>
                <span class="badge bg-success fs-6"><?php echo count($items); ?> items</span>
            </div>

            <!-- Search & Filter Bar -->
            <form method="GET" action="shop.php" class="card card-body mb-4 bg-light border-0 shadow-sm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label text-muted small mb-1">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <?php if (!empty($category)): ?>in <strong>
                            <?php
                            $catNames = ['1' => 'Dairy', '2' => 'Vegetables', '3' => 'Fruits', '4' => 'Beverages'];
                            echo $catNames[$category] ?? '';
                            ?></strong><?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>

            <!-- Items Grid -->
            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">No items found.</h5>
                    <a href="shop.php" class="btn btn-success mt-3">Browse All Items</a>
                </div>
            <?php else: ?>
                <div class="items-row row row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-4">
                    <?php foreach ($items as $row): ?>
                        <div class="col">
                            <div class="item-card card h-100 shadow-sm">
                                <?php if (!empty($row['itemImage'])): ?>
                                    <img src="<?php echo htmlspecialchars($row['itemImage']); ?>"
                                        class="card-img-top"
                                        alt="<?php echo htmlspecialchars($row['itemName']); ?>"
                                        style="height: 180px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center" style="height:180px;">
                                        <span class="text-muted small">No image</span>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body d-flex flex-column">
                                    <span class="badge bg-success-subtle text-success mb-2 align-self-start">
                                        <?php echo htmlspecialchars($row['categoryName']); ?>
                                    </span>
                                    <h6 class="card-title"><?php echo htmlspecialchars($row['itemName']); ?></h6>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($row['itemDescription']); ?></p>
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-success fs-5">₱<?php echo number_format($row['itemPrice'], 2); ?></strong>
                                            <small class="text-muted">per <?php echo htmlspecialchars($row['itemUnit']); ?></small>
                                        </div>
                                        <small class="text-muted badge bg-danger-subtle"><?php echo $row['reorderLevel']; ?> items sold</small><br>
                                        <small class="text-muted">Stock: <?php echo $row['itemQuantity']; ?> <?php echo htmlspecialchars($row['itemUnit']); ?></small>
                                    </div>
                                </div>

                                <div class="card-footer bg-white border-0 p-2">
                                    <?php if ($row['itemQuantity'] > 0): ?>
                                        <button class="btn btn-success w-100"
                                            onclick='openCartModal(<?php echo json_encode($row); ?>)'>
                                            Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-secondary w-100" disabled>Out of Stock</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="script.js"></script>
</body>

</html>