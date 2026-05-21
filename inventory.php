<?php
session_start();
require 'auth.php';
require 'db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = $_POST['itemName'];
        $description = $_POST['itemDescription'];
        $category = $_POST['categoryID'];
        $price = $_POST['itemPrice'];
        $quantity = $_POST['itemQuantity'] ?? 1;
        $unit = $_POST['itemUnit'];
        $expiryDate = $_POST['itemExpiryDate'] ?? date('Y-m-d', strtotime('+7 days'));

        // Handle image upload
        $image = null;
        if (!empty($_FILES['itemImage']['name'])) {
            $uploadDir = 'uploads/items/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($_FILES['itemImage']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('item_') . '.' . $ext;
            $destination = $uploadDir . $filename;

            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array(strtolower($ext), $allowed)) {
                if (move_uploaded_file($_FILES['itemImage']['tmp_name'], $destination)) {
                    $image = $destination; // save path to DB
                }
            } else {
                $message = "Invalid image format.";
                $messageType = "warning";
            }
        }

        if (!empty($name) && !empty($category) && !empty($price) && !empty($quantity)) {
            try {
                $sql = "INSERT INTO tblItems (itemName, itemDescription, itemImage, categoryID, itemPrice, itemQuantity, itemUnit, itemExpiryDate) 
                    VALUES (:itemName, :itemDescription, :itemImage, :categoryID, :itemPrice, :itemQuantity, :itemUnit, :itemExpiryDate)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'itemName' => $name,
                    'itemDescription' => $description,
                    'itemImage' => $image,
                    'categoryID' => $category,
                    'itemPrice' => $price,
                    'itemQuantity' => $quantity,
                    'itemUnit' => $unit,
                    'itemExpiryDate' => $expiryDate
                ]);
                $message = "Item '$name' added successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    } elseif ($action === 'edit') {
        $name = $_POST['itemName'];
        $description = $_POST['itemDescription'];
        $category = $_POST['categoryID'];
        $price = $_POST['itemPrice'];
        $quantity = $_POST['itemQuantity'];
        $unit = $_POST['itemUnit'];
        $expiryDate = $_POST['itemExpiryDate'] ?? date('Y-m-d', strtotime('+7 days'));

        // Keep existing image by default
        $image = $_POST['existingImage'] ?? null;

        if (!empty($_FILES['itemImage']['name'])) {
            $uploadDir = 'uploads/items/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($_FILES['itemImage']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('item_') . '.' . $ext;
            $destination = $uploadDir . $filename;

            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array(strtolower($ext), $allowed)) {
                if (move_uploaded_file($_FILES['itemImage']['tmp_name'], $destination)) {
                    // Delete old image file if it exists
                    if (!empty($image) && file_exists($image)) {
                        unlink($image);
                    }
                    $image = $destination;
                }
            }
        }

        try {
            $sql = "UPDATE tblItems SET itemName=:name, itemDescription=:description, itemImage=:image, 
                categoryID=:category, itemPrice=:price, itemQuantity=:quantity, itemUnit=:unit, 
                itemExpiryDate=:expiryDate WHERE itemID=:itemID";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'name' => $name,
                'description' => $description,
                'image' => $image,
                'category' => $category,
                'price' => $price,
                'quantity' => $quantity,
                'unit' => $unit,
                'expiryDate' => $expiryDate,
                'itemID' => $_POST['itemID']
            ]);
            $message = "Item '$name' successfully updated!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif ($action === 'delete') {
        $name = $_POST['itemName'] ?? 'Item';
        try {
            $sql = "DELETE FROM tblItems WHERE itemID = :itemID";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['itemID' => $_POST['itemID']]);
            $message = "Item '$name' successfully deleted!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Get search and filter parameters
$search   = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$sortBy   = $_GET['sortBy'] ?? 'itemID'; // Default sort to ID
$sortOrder = $_GET['sortOrder'] ?? 'ASC';

// Build query
$sql = "SELECT tblItems.*, tblCategories.categoryName FROM tblItems 
        JOIN tblCategories ON tblItems.categoryID = tblCategories.categoryID 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    // Search by ID or Name
    $sql .= " AND (tblItems.itemName LIKE :search OR tblItems.itemDescription LIKE :search OR tblItems.itemID = :exactSearch)";
    $params['search'] = '%' . $search . '%';
    $params['exactSearch'] = $search; // Exact match for ID
}

if (!empty($category)) {
    $sql .= " AND tblItems.categoryID = :category";
    $params['category'] = $category;
}

// Sanitize sort column (Added itemID and itemDateAdded)
$allowedSort = ['itemID', 'itemName', 'itemPrice', 'itemQuantity', 'itemDateAdded', 'itemExpiryDate'];
if (!in_array($sortBy, $allowedSort)) $sortBy = 'itemID';
if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'ASC';

$sql .= " ORDER BY tblItems." . $sortBy . " " . $sortOrder;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filter dropdown
$catStmt = $pdo->query("SELECT * FROM tblCategories ORDER BY categoryName");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Inventory</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link
        href="bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <header class="sticky-top">
        <nav class="navbar navbar-expand-sm navbar-dark bg-success">
            <div class="container-fluid w-75 ">
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
                            <a class="nav-link active" href="inventory.php">Inventory</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">Orders</a>
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
        <div class="container-fluid w-75">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show m-2" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addItemLabel">Add New Item</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="itemName" name="itemName" placeholder="Enter item name" required>
                                    <label for="itemName" class="form-label">Item Name</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="itemDescription" name="itemDescription" placeholder="Enter item description" required>
                                    <label for="itemDescription" class="form-label">Item Description</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="file" class="form-control" id="itemImage" name="itemImage" placeholder="Upload item image" required>
                                    <label for="itemImage" class="form-label">Item Image</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="categoryID" name="categoryID" required>
                                        <option value="">Select Category</option>
                                        <option value="3">Fruits</option>
                                        <option value="2">Vegetables</option>
                                        <option value="1">Dairy</option>
                                        <option value="4">Beverages</option>
                                    </select>
                                    <label for="categoryID" class="form-label">Category</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="itemPrice" name="itemPrice" step="0.01" placeholder="Enter unit price" required>
                                    <label for="itemPrice" class="form-label">Unit Price</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="itemQuantity" name="itemQuantity" placeholder="Enter stock quantity" required>
                                    <label for="itemQuantity" class="form-label">Stock Quantity</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="itemUnit" name="itemUnit" placeholder="Enter item unit" required>
                                    <label for="itemUnit" class="form-label">Item Unit (e.g., kg, pcs)</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="itemExpiryDate" name="itemExpiryDate" placeholder="Select expiry date" required>
                                    <label for="itemExpiryDate" class="form-label">Expiry Date</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-success w-100">Save Item</button>
                                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Close</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editItemLabel">Edit Item</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" id="itemID" name="itemID">
                                <input type="hidden" id="current_srp" name="current_srp">
                                <input type="hidden" id="edit_existingImage" name="existingImage">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="edit_itemName" name="itemName" required>
                                    <label for="edit_itemName" class="form-label">Item Name</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="edit_itemDescription" name="itemDescription" required>
                                    <label for="edit_itemDescription" class="form-label">Item Description</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="file" class="form-control" id="edit_itemImage" name="itemImage" placeholder="Upload item image">
                                    <label for="edit_itemImage" class="form-label">Item Image</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="edit_categoryID" name="categoryID" required>
                                        <option value="">Select Category</option>
                                        <option value="3">Fruits</option>
                                        <option value="2">Vegetables</option>
                                        <option value="1">Dairy</option>
                                        <option value="4">Beverages</option>
                                    </select>
                                    <label for="edit_categoryID" class="form-label">Category</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="edit_itemPrice" name="itemPrice" step="0.01" placeholder="Enter unit price" required>
                                    <label for="edit_itemPrice" class="form-label">Unit Price</label>
                                    <button type="button" class="btn btn btn-outline-primary w-100 mt-2" onclick="calculateSRP()">Calculate SRP</button>
                                    <div class="mt-2"><span id="srpDisplay"></span></div>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="edit_itemQuantity" name="itemQuantity" placeholder="Enter stock quantity" required>
                                    <label for="edit_itemQuantity" class="form-label">Stock Quantity</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="edit_itemUnit" name="itemUnit" placeholder="Enter item unit" required>
                                    <label for="edit_itemUnit" class="form-label">Item Unit (e.g., kg, pcs)</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="edit_itemExpiryDate" name="itemExpiryDate" placeholder="Select expiry date" required>
                                    <label for="edit_itemExpiryDate" class="form-label">Expiry Date</label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-success w-100">Update Item</button>
                                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Close</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteItemLabel">Delete Item</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" id="deleteItemID" name="itemID">
                                <input type="hidden" id="deleteItemName" name="itemName">
                                <p>Are you sure you want to delete <strong id="deleteItemNameDisplay"></strong>?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="container-lg mt-5">
                <h2>Inventory</h2>
                <p>Manage your inventory here.</p>
                <div class="mt-5">
                    <div class="card mb-4">
                        <div class="card-header bg-success-subtle">
                            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <input type="text" name="search" class="form-control" style="width: 250px;" placeholder="Search ID or Name..." value="<?php echo htmlspecialchars($search); ?>">

                                <select name="category" class="form-select w-auto">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['categoryID']; ?>" <?php if ($category == $cat['categoryID']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($cat['categoryName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="sortBy" class="form-select w-auto">
                                    <option value="itemID" <?php if ($sortBy == 'itemID') echo 'selected'; ?>>Sort by ID</option>
                                    <option value="itemName" <?php if ($sortBy == 'itemName') echo 'selected'; ?>>Sort by Name</option>
                                    <option value="itemDateAdded" <?php if ($sortBy == 'itemDateAdded') echo 'selected'; ?>>Sort by Date Added</option>
                                    <option value="itemQuantity" <?php if ($sortBy == 'itemQuantity') echo 'selected'; ?>>Sort by Stock Quantity</option>
                                </select>

                                <select name="sortOrder" class="form-select w-auto">
                                    <option value="ASC" <?php if ($sortOrder == 'ASC') echo 'selected'; ?>>Ascending ↑</option>
                                    <option value="DESC" <?php if ($sortOrder == 'DESC') echo 'selected'; ?>>Descending ↓</option>
                                </select>

                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="inventory.php" class="btn btn-outline-secondary">Reset</a>
                            </form>
                            <hr>
                            <div class="d-flex flex-wrap gap-3 align-items-center">
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                    + Add New Item
                                </button>
                            </div>
                        </div>
                        <div class="inventory card-body p-0">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Date Added</th>
                                        <th>Expiration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // FIXED: Loop through the $items array built by the search parameters at the top
                                    if (empty($items)):
                                    ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">No items found matching your search criteria.</td>
                                        </tr>
                                        <?php
                                    else:
                                        foreach ($items as $row):
                                        ?>
                                            <tr class="<?php
                                                        if ($row['itemQuantity'] < 1) {
                                                            echo 'table-danger';
                                                        } elseif ($row['itemQuantity'] < 10) {
                                                            echo 'table-warning';
                                                        } elseif (strtotime($row['itemExpiryDate']) < strtotime('+3 days')) {
                                                            echo 'table-danger';
                                                        }
                                                        ?>">
                                                <td><?php echo htmlspecialchars($row['itemID']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($row['itemName']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['itemDescription']); ?></td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success">
                                                        <?php echo htmlspecialchars($row['categoryName']); ?>
                                                    </span>
                                                </td>
                                                <td>₱<?php echo number_format($row['itemPrice'], 2); ?></td>
                                                <td><?php echo $row['itemQuantity']; ?></td>
                                                <td><?php echo htmlspecialchars($row['itemUnit']); ?></td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($row['itemDateAdded'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($row['itemExpiryDate'])); ?>
                                                    </small>
                                                </td>
                                                <td class="row m-0">
                                                    <div class="col-md mb-1">
                                                        <button class="w-100 btn btn-sm btn-primary" onclick='loadEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                                                    </div>
                                                    <div class="col-md">
                                                        <button class="w-100 col btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteItemModal" onclick="setDeleteItemID(<?php echo $row['itemID']; ?>, '<?php echo htmlspecialchars($row['itemName']); ?>')">Delete</button>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
    </main>
    <script
        src="bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="script.js"></script>
</body>

</html>