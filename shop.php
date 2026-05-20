<?php
session_start();
include 'db.php';

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
                <a class="navbar-brand me-auto" href="#">
                    <img
                        src="fresh-track.png"
                        alt="FreshTrack"
                        class="img-fluid d-block w-auto z-1 mt-2 mx-5" />
                </a>
                <search class="w-50">
                    <div class="input-group mb-3">
                        <input
                            type="text"
                            class="form-control"
                            placeholder="Search for items..."
                            aria-label="Search for items"
                            aria-describedby="button-search" />
                        <button class="btn bg-white type=" button" id="button-search">
                            <img src="search.svg" alt="Search" width="20" />
                        </button>
                    </div>
                </search>
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
                <div class="mx-5 p-2 bg-white rounded-5 d-flex align-items-center justify-content-center text-center">
                    <p class="mb-0"><b>Hotel Name:</b> <?php echo $_SESSION['username'] ?? 'Guest'; ?></p>
                </div>

                <div class="collapse navbar-collapse" id="collapsibleNavId">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item">
                            <a class="nav-link active" href="#" aria-current="page">
                                Shop
                                <span class="visually-hidden">(current)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">Cart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bill.php">Transactions</a>
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
                            <div class="dropdown-menu" aria-labelledby="dropdownId">
                                <a class="dropdown-item" href="settings.php">Settings</a>
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
            <div id="cartToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body" id="cartToastMessage">
                        Item added to cart!
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
        <div class="container-lg mt-5">
            <h2>Shop</h2>
            <p>Browse and purchase fresh items for your hotel!</p>
        </div>
        <div class="container">

            <div class="row">
                <?php
                try {
                    $stmt = $pdo->query("SELECT * FROM tblItems");

                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '
                            <div class="col g-3 mb-4">
                                <div class="item-card card h-100">
                                    <img src="' . htmlspecialchars($row['itemImage']) . '" class="card-img-top" alt="' . htmlspecialchars($row['itemName']) . '">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title">' . htmlspecialchars($row['itemName']) . '</h5>
                                        <p class="card-text">' . htmlspecialchars($row['itemDescription']) . '</p>
                                        <p class="card-text mt-auto"><strong>₱' . number_format($row['itemPrice'], 2) . ' per ' . htmlspecialchars($row['itemUnit']) . '</strong></p>
                                    </div>
                                    <div class="card-footer p-1">
                                        <button class="btn btn-success w-100" onclick=\'openCartModal(' . json_encode($row) . ')\'>Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                            ';
                    }
                } catch (PDOException $e) {
                    echo "Error: " . $e->getMessage();
                }
                ?>
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