<?php
include 'db.php';
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - Dashboard</title>
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
                            <a class="nav-link active" href="#" aria-current="page">
                                Dashboard
                                <span class="visually-hidden">(current)</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="inventory.php">Inventory</a>
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
    <main>
        <div class="container-lg mt-5">
            <h2>Dashboard</h2>
            <p>Welcome to your dashboard!</p>
        </div>
        <div class="container-lg">
            <div class="card mb-4">
                <h3 class="card-header bg-success-subtle">Summary</h3>
                <div
                    class="row d-flex align-items-stretch gap-2 justify-content-center text-center p-3">
                    <div class="col-md">
                        <div class="card w-100 h-100">
                            <h6 class="card-header">Sales</h6>
                            <div class="card-body">
                                <p class="card-text">
                                    ₱ 0.00
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="card w-100 h-100">
                            <h6 class="card-header">Total Inventory Cost</h6>
                            <div class="card-body">
                                <p class="card-text">
                                    <?php
                                    $stmt = $pdo->query("SELECT SUM(itemPrice * itemQuantity) FROM tblItems");
                                    $totalInventoryCost = $stmt->fetchColumn();
                                    echo '₱ ' . number_format($totalInventoryCost, 2);
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="card w-100 h-100">
                            <h6 class="card-header">Inventory Items</h6>
                            <div class="card-body">
                                <p class="card-text">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM tblItems");
                                    $inventoryItemsCount = $stmt->fetchColumn();
                                    echo $inventoryItemsCount;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="card w-100 h-100">
                            <h6 class="card-header">Low Stock Items</h6>
                            <div class="card-body">
                                <p class="card-text">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM tblItems WHERE itemQuantity < 10");
                                    $lowStockItemsCount = $stmt->fetchColumn();
                                    echo $lowStockItemsCount;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="card w-100 h-100">
                            <h6 class="card-header">Queued Orders</h6>
                            <div class="card-body">
                                <p class="card-text">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM tblorders WHERE status = 'pending'");
                                    $queuedOrdersCount = $stmt->fetchColumn();
                                    echo $queuedOrdersCount;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md">
                        <div class="card w-100 h-100">
                            <h6 class="card-header">
                                Ongoing Deliveries
                            </h6>
                            <div class="card-body">
                                <p class="card-text">
                                    <?php
                                    $stmt = $pdo->query("SELECT COUNT(*) FROM tblorders WHERE status = 'in_progress'");
                                    $inProgressDeliveriesCount = $stmt->fetchColumn();
                                    echo $inProgressDeliveriesCount;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div class="container-lg">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <h3 class="card-header bg-success-subtle">Analytics</h3>
                        <div class="card-body">
                            <p class="card-text">
                                Analytics content goes here.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <h3 class="card-header bg-success-subtle">Recent Activity</h3>
                        <div class="card-body">
                            <p class="card-text">
                                Recent activity content goes here.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
    </main>
    <footer>
        <!-- place footer here -->
    </footer>
    <!-- Bootstrap JavaScript Bundle (includes Popper) -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>

</html>