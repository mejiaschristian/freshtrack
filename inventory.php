<?php
session_start();
require 'auth.php';
require 'db.php';

// Check if user is logged in and is admin or staff
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff') {
    header('Location: dashboard.php');
    exit();
}

$message = "";
$messageType = "";

/* ---------------------------------------------------------------
   Batch number generator  →  BATCH-YYYY-MM-NNN
--------------------------------------------------------------- */
function generateBatchCode($pdo, $itemID)
{
    $ym = date('Y-m');
    $prefix = 'BATCH-' . $ym . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tblItemBatches WHERE batchCode LIKE :p");
    $stmt->execute(['p' => $prefix . '%']);
    $n = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad($n, 3, '0', STR_PAD_LEFT);
}

/* ---------------------------------------------------------------
   Recalculate tblItems.itemQuantity from live batch stock
   and update itemExpiryDate to the FIFO (soonest) expiry.
--------------------------------------------------------------- */
function syncItemFromBatches($pdo, $itemID)
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity), 0)  AS totalQty,
               MIN(CASE WHEN quantity > 0 THEN expiryDate END) AS fifoExpiry
        FROM tblItemBatches
        WHERE itemID = :id
          AND (batchStatus = 'active' OR batchStatus = '')
    ");
    $stmt->execute(['id' => $itemID]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("
        UPDATE tblItems
        SET itemQuantity  = :qty,
            itemExpiryDate = :exp
        WHERE itemID = :id
    ")->execute([
        'qty' => $row['totalQty'],
        'exp' => $row['fifoExpiry'] ?? date('Y-m-d'),
        'id'  => $itemID,
    ]);
}

/* ---------------------------------------------------------------
   POST handler
--------------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    /* ---- ADD ITEM (creates item + first batch) ---- */
    if ($action === 'add') {
        $name        = $_POST['itemName'];
        $description = $_POST['itemDescription'];
        $category    = $_POST['categoryID'];
        $price       = $_POST['itemPrice'];
        $unit        = $_POST['itemUnit'];
        $quantity    = (int)($_POST['itemQuantity'] ?? 0);
        $expiryDate  = $_POST['itemExpiryDate'] ?? date('Y-m-d', strtotime('+7 days'));
        $harvestDate = $_POST['harvestDate']    ?? date('Y-m-d');

        // Image upload
        $image = null;
        if (!empty($_FILES['itemImage']['name'])) {
            $uploadDir = 'uploads/items/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['itemImage']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $filename    = uniqid('item_') . '.' . $ext;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['itemImage']['tmp_name'], $destination)) {
                    $image = $destination;
                }
            } else {
                $message = "Invalid image format.";
                $messageType = "warning";
            }
        }

        if (!empty($name) && !empty($category) && !empty($price)) {
            try {
                $pdo->beginTransaction();

                // Insert the item (quantity and expiry are synced from batches)
                $pdo->prepare("
                    INSERT INTO tblItems
                        (itemName, itemDescription, itemImage, categoryID, itemPrice, itemQuantity, itemUnit, itemExpiryDate)
                    VALUES
                        (:itemName, :itemDescription, :itemImage, :categoryID, :itemPrice, :qty, :itemUnit, :itemExpiryDate)
                ")->execute([
                    'itemName'        => $name,
                    'itemDescription' => $description,
                    'itemImage'       => $image,
                    'categoryID'      => $category,
                    'itemPrice'       => $price,
                    'qty'             => $quantity,
                    'itemUnit'        => $unit,
                    'itemExpiryDate'  => $expiryDate,
                ]);
                $itemID = $pdo->lastInsertId();

                // Create opening batch
                $batchCode = generateBatchCode($pdo, $itemID);
                $pdo->prepare("
                    INSERT INTO tblItemBatches
                        (itemID, batchCode, harvestDate, expiryDate, quantity, initialQty)
                    VALUES
                        (:itemID, :batchCode, :harvestDate, :expiryDate, :qty, :qty2)
                ")->execute([
                    'itemID'      => $itemID,
                    'batchCode'   => $batchCode,
                    'harvestDate' => $harvestDate,
                    'expiryDate'  => $expiryDate,
                    'qty'         => $quantity,
                    'qty2'        => $quantity,
                ]);

                $pdo->commit();
                $message     = "Item '$name' added with batch $batchCode!";
                $messageType = "success";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message     = "Error: " . $e->getMessage();
                $messageType = "danger";
            }
        }

        /* ---- ADD BATCH to existing item ---- */
    } elseif ($action === 'add_batch') {
        $itemID       = (int)($_POST['itemID']          ?? 0);
        // FIXED: Matched against the customized form names passed from the view modal fields
        $quantity     = (int)($_POST['batchQuantity']   ?? 0);
        $expiryDate   = $_POST['batchExpiryDate']       ?? '';
        $harvestDate  = $_POST['batchHarvestDate']      ?? date('Y-m-d');

        if ($itemID < 1 || $quantity < 1 || empty($expiryDate)) {
            $message     = "Please fill in all required batch fields (quantity and expiry date).";
            $messageType = "warning";
        } else {
            try {
                $pdo->beginTransaction();

                $batchCode = generateBatchCode($pdo, $itemID);
                $pdo->prepare("INSERT INTO tblItemBatches (itemID, batchCode, harvestDate, expiryDate, quantity, initialQty)
                               VALUES (:itemID, :batchCode, :harvestDate, :expiryDate, :qty, :qty2)")
                    ->execute([
                        'itemID'      => $itemID,
                        'batchCode'   => $batchCode,
                        'harvestDate' => $harvestDate,
                        'expiryDate'  => $expiryDate,
                        'qty'         => $quantity,
                        'qty2'        => $quantity,
                    ]);

                syncItemFromBatches($pdo, $itemID);
                $pdo->commit();

                $message     = "Batch $batchCode added successfully!";
                $messageType = "success";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message     = "Error: " . $e->getMessage();
                $messageType = "danger";
            }
        }

        /* ---- EDIT item metadata ---- */
    } elseif ($action === 'edit') {
        $name        = $_POST['itemName'];
        $description = $_POST['itemDescription'];
        $category    = $_POST['categoryID'];
        $price       = $_POST['itemPrice'];
        $unit        = $_POST['itemUnit'];
        $image       = $_POST['existingImage'] ?? null;

        if (!empty($_FILES['itemImage']['name'])) {
            $uploadDir = 'uploads/items/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['itemImage']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $filename    = uniqid('item_') . '.' . $ext;
                $destination = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['itemImage']['tmp_name'], $destination)) {
                    if (!empty($image) && file_exists($image)) unlink($image);
                    $image = $destination;
                }
            }
        }

        try {
            $pdo->prepare("
                UPDATE tblItems
                SET itemName=:name, itemDescription=:description, itemImage=:image,
                    categoryID=:category, itemPrice=:price, itemUnit=:unit
                WHERE itemID=:itemID
            ")->execute([
                'name'        => $name,
                'description' => $description,
                'image'       => $image,
                'category'    => $category,
                'price'       => $price,
                'unit'        => $unit,
                'itemID'      => $_POST['itemID'],
            ]);
            $message     = "Item '$name' updated!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message     = "Error: " . $e->getMessage();
            $messageType = "danger";
        }

        /* ---- DELETE BATCH ---- */
    } elseif ($action === 'delete_batch') {
        $batchID = (int)$_POST['batchID'];
        $itemID  = (int)$_POST['itemID'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tblItemBatches SET batchStatus = 'archived' WHERE batchID = :id")
                ->execute(['id' => $batchID]);
            syncItemFromBatches($pdo, $itemID);
            $pdo->commit();
            $message     = "Batch archived.";
            $messageType = "success";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message     = "Error: " . $e->getMessage();
            $messageType = "danger";
        }

        /* ---- DELETE ITEM ---- */
    } elseif ($action === 'delete') {
        $name = $_POST['itemName'] ?? 'Item';
        try {
            $pdo->prepare("DELETE FROM tblItems WHERE itemID = :itemID")
                ->execute(['itemID' => $_POST['itemID']]);
            $message     = "Item '$name' deleted.";
            $messageType = "success";
        } catch (PDOException $e) {
            $message     = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

/* ---------------------------------------------------------------
   Archive expired batches and sync item stock before rendering
--------------------------------------------------------------- */
$pdo->prepare("UPDATE tblItemBatches
                SET batchStatus = 'archived'
                WHERE expiryDate < CURDATE()
                  AND (batchStatus = 'active' OR batchStatus = '')")
    ->execute();
$pdo->prepare("UPDATE tblItems i
                LEFT JOIN (
                    SELECT itemID,
                           COALESCE(SUM(quantity), 0) AS totalQty,
                           MIN(CASE WHEN quantity > 0 THEN expiryDate END) AS fifoExpiry
                    FROM tblItemBatches
                    WHERE batchStatus IN ('active', '')
                    GROUP BY itemID
                ) b ON i.itemID = b.itemID
                SET i.itemQuantity = COALESCE(b.totalQty, 0),
                    i.itemExpiryDate = COALESCE(b.fifoExpiry, CURDATE())")
    ->execute();

/* ---------------------------------------------------------------
   Query params
--------------------------------------------------------------- */
$search    = trim($_GET['search'] ?? '');
$category  = $_GET['category'] ?? '';
$sortBy    = $_GET['sortBy']    ?? 'itemID';
$sortOrder = $_GET['sortOrder'] ?? 'ASC';

$sql    = "SELECT tblItems.*, tblCategories.categoryName FROM tblItems
           JOIN tblCategories ON tblItems.categoryID = tblCategories.categoryID
           WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (tblItems.itemName LIKE :search OR tblItems.itemDescription LIKE :search OR tblItems.itemID = :exactSearch)";
    $params['search']      = '%' . $search . '%';
    $params['exactSearch'] = $search;
}
if (!empty($category)) {
    $sql .= " AND tblItems.categoryID = :category";
    $params['category'] = $category;
}

$allowedSort = ['itemID', 'itemName', 'itemPrice', 'itemQuantity', 'itemDateAdded', 'itemExpiryDate'];
if (!in_array($sortBy, $allowedSort)) $sortBy = 'itemID';
if (!in_array(strtoupper($sortOrder), ['ASC', 'DESC'])) $sortOrder = 'ASC';
$sql .= " ORDER BY tblItems." . $sortBy . " " . $sortOrder;

$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active batches for each item (FIFO order)
$batchMap = [];
if (!empty($items)) {
    $ids = implode(',', array_column($items, 'itemID'));
    $batchRows = $pdo->query("
        SELECT * FROM tblItemBatches
        WHERE itemID IN ($ids)
        ORDER BY itemID ASC, harvestDate ASC, batchID ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($batchRows as $b) {
        $batchMap[$b['itemID']][] = $b;
    }
}

$catStmt    = $pdo->query("SELECT * FROM tblCategories ORDER BY categoryName");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Inventory</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>

    <header class="sticky-top">
        <nav class="navbar navbar-expand-sm navbar-dark bg-success">
            <div class="container-fluid w-75">
                <a class="navbar-brand me-auto" href="dashboard.php">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid" />
                </a>
                <button class="navbar-toggler p-4" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapsibleNavId" aria-controls="collapsibleNavId"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="collapsibleNavId">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link active" href="inventory.php">Inventory</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <li class="nav-item"><a class="nav-link" href="users.php">Users</a></li>
                        <?php endif; ?>
                        <li class="border-start border-success-subtle ps-3 nav-item dropdown d-flex align-items-center mx-3">
                            <img src="user-icon.svg" alt="user-icon" width="35">
                            <a class="nav-link dropdown-toggle" href="#" id="dropdownId"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
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

    <main>
        <div class="container-fluid w-75">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show m-2" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addItemLabel">Add New Item + Opening Batch</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="itemName" name="itemName" placeholder="Name" required>
                                            <label>Item Name</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select class="form-select" id="categoryID" name="categoryID" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['categoryID']; ?>"><?php echo htmlspecialchars($cat['categoryName']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label>Category</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="itemDescription" placeholder="Description" required>
                                            <label>Description</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" name="itemPrice" step="0.01" placeholder="Price" required>
                                            <label>Unit Price (₱)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" name="itemUnit" placeholder="Unit" required>
                                            <label>Unit (kg, pcs, liter…)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold text-success">Item Image</label>
                                        <input type="file" class="form-control" name="itemImage">
                                    </div>

                                    <div class="col-12">
                                        <hr>
                                        <p class="fw-semibold text-success mb-0">Opening Batch Details</p>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" name="itemQuantity" placeholder="Qty" required min="0">
                                            <label>Batch Quantity</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="date" class="form-control" name="harvestDate" value="<?php echo date('Y-m-d'); ?>" required>
                                            <label>Harvest / Received Date</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="date" class="form-control" name="itemExpiryDate" required>
                                            <label>Expiry Date</label>
                                        </div>
                                    </div>
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
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editItemLabel">Edit Item</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" id="itemID" name="itemID">
                                <input type="hidden" id="edit_existingImage" name="existingImage">
                                <p class="text-muted small"><em>Stock quantity and expiry are managed through batches. Use "Add Batch" to restock.</em></p>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="edit_itemName" name="itemName" required>
                                            <label>Item Name</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <select class="form-select" id="edit_categoryID" name="categoryID" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['categoryID']; ?>"><?php echo htmlspecialchars($cat['categoryName']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label>Category</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="edit_itemDescription" name="itemDescription" required>
                                            <label>Description</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="edit_itemPrice" name="itemPrice" step="0.01" required>
                                            <label>Unit Price (₱)</label>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary w-100 mt-2" onclick="calculateSRP()">Calculate SRP</button>
                                        <div class="mt-1 small" id="srpDisplay"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="edit_itemUnit" name="itemUnit" required>
                                            <label>Unit</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Replace Image (optional)</label>
                                        <input type="file" class="form-control" name="itemImage">
                                    </div>
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

            <div class="modal fade" id="addBatchModal" tabindex="-1" aria-labelledby="addBatchLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success-subtle">
                            <h5 class="modal-title" id="addBatchLabel">Add New Batch</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" id="addBatchForm" onsubmit="return validateBatchForm()">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_batch">
                                <input type="hidden" id="batch_itemID" name="itemID">
                                <p class="fw-semibold" id="batch_itemName_display"></p>

                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="batchQuantityInput" name="batchQuantity" placeholder="Qty" min="1">
                                    <label>Quantity to Add <span class="text-danger">*</span></label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="batchHarvestDateInput" name="batchHarvestDate"
                                        value="<?php echo date('Y-m-d'); ?>">
                                    <label>Harvest / Received Date <span class="text-danger">*</span></label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control" id="batchExpiryDateInput" name="batchExpiryDate">
                                    <label>Expiry Date <span class="text-danger">*</span></label>
                                </div>
                                <div class="alert alert-info small p-2 mb-0">
                                    <strong>FIFO note:</strong> This batch will be placed behind older batches in the queue. Stock will be consumed from the oldest (earliest harvest) batch first.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-success w-100">Add Batch</button>
                                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="batchListModal" tabindex="-1" aria-labelledby="batchListLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-success-subtle text-success-emphasis border-bottom border-success-subtle">
                            <h5 class="modal-title fw-bold" id="batchListLabel">Manage Batches</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs mb-3" id="batchModalTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="active-batches-tab" data-bs-toggle="tab" data-bs-target="#active-batches-pane" type="button" role="tab" aria-controls="active-batches-pane" aria-selected="true">
                                        Active Batches
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="history-batches-tab" data-bs-toggle="tab" data-bs-target="#history-batches-pane" type="button" role="tab" aria-controls="history-batches-pane" aria-selected="false">
                                        Batch History
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="batchModalTabsContent">
                                <div class="tab-pane fade show active" id="active-batches-pane" role="tabpanel" aria-labelledby="active-batches-tab">
                                    <div id="batchListBody"></div>
                                </div>

                                <div class="tab-pane fade" id="history-batches-pane" role="tabpanel" aria-labelledby="history-batches-tab">
                                    <div id="batchHistoryBody"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="deleteBatchConfirmModal" tabindex="-1" aria-labelledby="deleteBatchConfirmModalLabel" aria-hidden="true" style="z-index: 1060;">
                <div class="modal-dialog modal-sm" style="margin-top: 5rem;">
                    <div class="modal-content border-danger shadow">
                        <div class="modal-header bg-danger-subtle text-danger-emphasis py-2">
                            <h6 class="modal-title fw-bold" id="deleteBatchConfirmModalLabel">Confirm Removal</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center py-3">
                            <p class="mb-0 small fw-semibold text-secondary">Are you sure you want to remove this active batch from inventory?</p>
                        </div>
                        <div class="modal-footer py-1 justify-content-center border-0">
                            <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">No</button>
                            <button type="button" id="executeBatchDeleteBtn" class="btn btn-sm btn-danger px-3">Yes, Remove</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteItemLabel">Delete Item</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" id="deleteItemID" name="itemID">
                                <input type="hidden" id="deleteItemName" name="itemName">
                                <p>Are you sure you want to delete <strong id="deleteItemNameDisplay"></strong>? All batches will be removed.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="container-fluid mt-5">
                <h2 class="mb-1">Inventory</h2>
                <p class="text-muted">Manage stock by harvest batch. Expiry displayed follows <strong>FIFO</strong> (earliest batch first).</p>

                <div class="mt-4">
                    <div class="card mb-4">
                        <div class="card-header bg-success-subtle">
                            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <input type="text" name="search" class="form-control" style="width:250px;"
                                    placeholder="Search ID or Name…"
                                    value="<?php echo htmlspecialchars($search); ?>">
                                <select name="category" class="form-select w-auto">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['categoryID']; ?>"
                                            <?php if ($category == $cat['categoryID']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($cat['categoryName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="sortBy" class="form-select w-auto">
                                    <option value="itemID" <?php if ($sortBy == 'itemID')        echo 'selected'; ?>>Sort by ID</option>
                                    <option value="itemName" <?php if ($sortBy == 'itemName')      echo 'selected'; ?>>Sort by Name</option>
                                    <option value="itemDateAdded" <?php if ($sortBy == 'itemDateAdded') echo 'selected'; ?>>Sort by Date Added</option>
                                    <option value="itemQuantity" <?php if ($sortBy == 'itemQuantity')  echo 'selected'; ?>>Sort by Stock</option>
                                    <option value="itemExpiryDate" <?php if ($sortBy == 'itemExpiryDate') echo 'selected'; ?>>Sort by Expiry (FIFO)</option>
                                </select>
                                <select name="sortOrder" class="form-select w-auto">
                                    <option value="ASC" <?php if ($sortOrder == 'ASC')  echo 'selected'; ?>>↑ ASC</option>
                                    <option value="DESC" <?php if ($sortOrder == 'DESC') echo 'selected'; ?>>↓ DESC</option>
                                </select>
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="inventory.php" class="btn btn-outline-secondary">Reset</a>
                            </form>
                            <hr>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                + Add New Item
                            </button>
                        </div>

                        <div class="inventory card-body p-0">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Total Stock</th>
                                        <th>Unit</th>
                                        <th>FIFO Expiry<br><small class="fw-normal text-muted">(next batch)</small></th>
                                        <th>Batches</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($items)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">No items found.</td>
                                        </tr>
                                        <?php else:
                                        foreach ($items as $row):
                                            $batches   = $batchMap[$row['itemID']] ?? [];
                                            $today     = strtotime('today');
                                            $fifoExpiry = null;
                                            foreach ($batches as $b) {
                                                if ($b['quantity'] > 0) {
                                                    $fifoExpiry = $b['expiryDate'];
                                                    break;
                                                }
                                            }
                                            $daysLeft  = $fifoExpiry ? round((strtotime($fifoExpiry) - $today) / 86400) : null;
                                            $rowClass  = '';
                                            if ($row['itemQuantity'] < 1) $rowClass = 'table-danger';
                                            elseif ($row['itemQuantity'] < 10) $rowClass = 'table-warning';
                                            elseif ($daysLeft !== null && $daysLeft <= 3) $rowClass = 'table-danger';
                                            elseif ($daysLeft !== null && $daysLeft <= 7) $rowClass = 'table-warning';
                                        ?>
                                            <tr class="<?php echo $rowClass; ?>">
                                                <td><?php echo $row['itemID']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($row['itemName']); ?></strong>
                                                    <div class="small text-muted"><?php echo htmlspecialchars(substr($row['itemDescription'], 0, 40)); ?>…</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success">
                                                        <?php echo htmlspecialchars($row['categoryName']); ?>
                                                    </span>
                                                </td>
                                                <td>₱<?php echo number_format($row['itemPrice'], 2); ?></td>
                                                <td>
                                                    <?php echo $row['itemQuantity']; ?>
                                                    <?php if ($row['itemQuantity'] < 1): ?>
                                                        <span class="badge bg-danger ms-1">OUT</span>
                                                    <?php elseif ($row['itemQuantity'] < 10): ?>
                                                        <span class="badge bg-warning text-dark ms-1">LOW</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['itemUnit']); ?></td>
                                                <td>
                                                    <?php if ($fifoExpiry): ?>
                                                        <?php
                                                        $cls = 'batch-expiry-ok';
                                                        if ($daysLeft <= 3)      $cls = 'batch-expiry-crit';
                                                        elseif ($daysLeft <= 7)  $cls = 'batch-expiry-soon';
                                                        ?>
                                                        <span class="batch-tag <?php echo $cls; ?>">
                                                            <?php echo date('M d, Y', strtotime($fifoExpiry)); ?>
                                                            <?php if ($daysLeft !== null): ?>
                                                                (<?php echo $daysLeft > 0 ? "$daysLeft days" : 'PAST EXPIRY'; ?>)
                                                            <?php endif; ?>
                                                        </span>
                                                        <div class="batch-tag fifo-badge mt-1">FIFO next</div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $activeBatches = array_filter($batches, fn($b) => ($b['batchStatus'] === 'active' || $b['batchStatus'] === '') && $b['quantity'] > 0);
                                                    $totalBatches  = count($batches);
                                                    echo '<span class="badge bg-secondary">' . count($activeBatches) . ' active / ' . $totalBatches . ' total</span>';
                                                    ?>
                                                    <br>
                                                    <button class="btn btn-sm btn-success mt-1 mb-1"
                                                        onclick='openAddBatch(<?php echo $row["itemID"]; ?>, "<?php echo htmlspecialchars($row["itemName"]); ?>")'>
                                                        + Batch
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success"
                                                        onclick='openBatchList(<?php echo $row["itemID"]; ?>, <?php echo htmlspecialchars(json_encode($batches), ENT_QUOTES); ?>, "<?php echo htmlspecialchars($row["itemName"]); ?>")'>
                                                        View
                                                    </button>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column gap-1">
                                                        <button class="btn btn-sm btn-primary"
                                                            onclick='loadEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                                                        <button class="btn btn-sm btn-danger"
                                                            data-bs-toggle="modal" data-bs-target="#deleteItemModal"
                                                            onclick="setDeleteItemID(<?php echo $row['itemID']; ?>, '<?php echo htmlspecialchars($row['itemName']); ?>')">
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>

</html>