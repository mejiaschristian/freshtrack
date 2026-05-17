<?php
require 'db.php';

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = $_POST['itemName'];
        $description = $_POST['itemDescription'];
        $category = $_POST['itemCategory'];
        $price = $_POST['itemPrice'];
        $quantity = $_POST['itemQuantity'] ?? 1;
        $unit = $_POST['itemUnit'];
        $dateAdded = $_POST['itemDateAdded'] ?? date('Y-m-d');
        $expiryDate = $_POST['itemExpiryDate'] ?? date('Y-m-d', strtotime('+7 days'));

        if (!empty($name) && !empty($category) && !empty($price) && !empty($quantity)) {
            try {
                $sql = "INSERT INTO tblItems (itemName, itemDescription, itemCategory, itemPrice, itemQuantity, itemUnit, itemDateAdded, itemExpiryDate) VALUES (:itemName, :itemDescription, :itemCategory, :itemPrice, :itemQuantity, :itemUnit, :itemDateAdded, :itemExpiryDate)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'itemName' => $name,
                    'itemDescription' => $description,
                    'itemCategory' => $category,
                    'itemPrice' => $price,
                    'itemQuantity' => $quantity,
                    'itemUnit' => $unit,
                    'itemDateAdded' => $dateAdded,
                    'itemExpiryDate' => $expiryDate
                ]);
                $message = "Item '$name' added successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "danger";
            }
        } else {
            $message = "Please fill in all fields.";
            $messageType = "warning";
        }
    } elseif ($action === 'edit') {
        $name = $_POST['itemName'];
        $description = $_POST['itemDescription'];
        $category = $_POST['itemCategory'];
        $price = $_POST['itemPrice'];
        $quantity = $_POST['itemQuantity'];
        $unit = $_POST['itemUnit'];
        $dateAdded = $_POST['itemDateAdded'] ?? date('Y-m-d');
        $expiryDate = $_POST['itemExpiryDate'] ?? date('Y-m-d', strtotime('+7 days'));

        try {
            $sql = "UPDATE tblItems SET itemName = :name, itemDescription = :description, itemCategory = :category, itemPrice = :price, itemQuantity = :quantity, itemUnit = :unit, itemDateAdded = :dateAdded, itemExpiryDate = :expiryDate WHERE itemID = :itemID";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'name' => $name,
                'description' => $description,
                'category' => $category,
                'price' => $price,
                'quantity' => $quantity,
                'unit' => $unit,
                'dateAdded' => $dateAdded,
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
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Inventory</title>
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
            <div class="w-75 container-fluid">
                <a class="navbar-brand me-auto" href="#">
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
                            <a
                                class="nav-link"
                                href="dashboard.php"
                                aria-current="page">
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="inventory.php">Inventory
                                <span class="visually-hidden">(current)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">Orders</a>
                        </li>
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
                            <div
                                class="dropdown-menu"
                                aria-labelledby="dropdownId">
                                <a class="dropdown-item" href="#">Settings</a>
                                <a
                                    class="dropdown-item btn btn-danger"
                                    href="index.php">Log Out</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main>
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
                    <form method="POST">
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
                                <select class="form-select" id="itemCategory" name="itemCategory" required>
                                    <option value="">Select Category</option>
                                    <option value="Fruits">Fruits</option>
                                    <option value="Vegetables">Vegetables</option>
                                    <option value="Dairy">Dairy</option>
                                </select>
                                <label for="itemCategory" class="form-label">Category</label>
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
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" id="itemID" name="itemID">
                            <input type="hidden" id="current_srp" name="current_srp">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_itemName" name="itemName" required>
                                <label for="edit_itemName" class="form-label">Item Name</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="edit_itemDescription" name="itemDescription" required>
                                <label for="edit_itemDescription" class="form-label">Item Description</label>
                            </div>
                            <div class="form-floating mb-3">
                                <select class="form-select" id="edit_itemCategory" name="itemCategory" required>
                                    <option value="">Select Category</option>
                                    <option value="Fruits">Fruits</option>
                                    <option value="Vegetables">Vegetables</option>
                                    <option value="Dairy">Dairy</option>
                                </select>
                                <label for="edit_itemCategory" class="form-label">Category</label>
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
                                <input type="date" class="form-control" id="edit_itemDateAdded" name="itemDateAdded" placeholder="Select date added" required>
                                <label for="edit_itemDateAdded" class="form-label">Date Added</label>
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
                    <h3 class="card-header bg-success-subtle">
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <div class="col">
                                <search>
                                    <input type="text" class="form-control" placeholder="Search inventory..." />
                                </search>
                            </div>
                            <button class="btn btn-outline-success" type="button">
                                <img src="search.svg" class="img-fluid" alt="Search" width="20">
                            </button>
                            <div class="col">
                                <div class="btn-group w-100">
                                    <button
                                        class="btn btn-secondary dropdown-toggle"
                                        type="button"
                                        id="triggerId"
                                        data-bs-toggle="dropdown"
                                        aria-haspopup="true"
                                        aria-expanded="false">
                                        Filter Column
                                    </button>
                                    <div
                                        class="dropdown-menu w-100"
                                        aria-labelledby="triggerId">
                                        <a class="dropdown-item" href="#">ID</a>
                                        <a class="dropdown-item" href="#">Name</a>
                                        <a class="dropdown-item" href="#">Description</a>
                                    </div>
                                </div>

                            </div>
                            <div class="col">
                                <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                    Add Item
                                </button>
                            </div>
                        </div>
                    </h3>
                    <div class="inventory card-body p-0">
                        <table class="table table-striped table-hover">
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
                                $stmt = $pdo->query("SELECT * FROM tblItems");
                                while ($row = $stmt->fetch()):
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
                                                <?php echo htmlspecialchars($row['itemCategory']); ?>
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
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </main>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="script.js"></script>
</body>

</html>