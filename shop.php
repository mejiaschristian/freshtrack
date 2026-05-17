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
                        <button class="btn bg-white type="button" id="button-search">
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
            <h2>Shop</h2>
            <p>Browse and purchase fresh items for your hotel!</p>
        </div>
        <div class="container">

            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php
                include 'db.php';
                try {
                    $stmt = $pdo->query("SELECT * FROM tblItems");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo '
                        <div class="col">
                            <div class="item-card card h-100">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">' . htmlspecialchars($row['itemName']) . '</h5>
                                    <p class="card-text">' . htmlspecialchars($row['itemDescription']) . '</p>
                                    <p class="card-text mt-auto"><strong>₱' . htmlspecialchars($row['itemPrice']) . ' per ' . htmlspecialchars($row['itemUnit']) . '</strong></p>
                                    <a href="cart.php?add=' . htmlspecialchars($row['itemID']) . '" class="btn btn-success">Add to Cart</a>
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