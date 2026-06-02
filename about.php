<?php
session_start();
include 'db.php';
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== "hotel") {
    header('Location: dashboard.php');
    exit();
}

?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - About</title>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS v5.3.8 -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header class="sticky-top">
        <nav class="navbar navbar-expand-lg navbar-dark bg-success">
            <div class="container-lg">
                <a class="navbar-brand me-auto" href="shop.php">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid" />
                </a>
                <button
                    class="navbar-toggler"
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
                            <a class="nav-link" href="shop.php">Shop</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cart.php">Cart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="hotel_orders.php">Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="recurring_orders.php">Recurring</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bill.php">Transactions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="about.php">About</a>
                            <span class="visually-hidden">(current)</span>
                        </li>
                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle d-flex align-items-center gap-2 border-start border-1 ms-3 px-3"
                                href="#"
                                id="dropdownId"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
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

    <main>
        <div class="container-lg mt-5 mb-5">
            <!-- Header -->
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto">
                    <h1 class="mb-3 text-success fw-bold">About FreshTrack</h1>
                    <p class="lead text-muted">Your trusted fresh produce ordering platform for hotels.</p>
                </div>
            </div>

            <!-- Mission Section -->
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto">
                    <div class="card about-card shadow-sm mb-4">
                        <div class="card-body">
                            <h3 class="card-title text-success mb-3">Our Mission</h3>
                            <p class="card-text">
                                FreshTrack aims to streamline the process of ordering fresh produce for hotels.
                                We provide a seamless platform that connects suppliers with hospitality businesses, ensuring
                                timely delivery of high-quality fresh items.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto">
                    <h3 class="text-success mb-4">Key Features</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card about-card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-success">Inventory Management</h5>
                                    <p class="card-text text-muted small">
                                        Real-time tracking of available stock, expiry dates, and reorder levels.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card about-card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-success">Easy Ordering</h5>
                                    <p class="card-text text-muted small">
                                        Browse, search, and add items to your cart with just a few clicks.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card about-card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-success">Order Tracking</h5>
                                    <p class="card-text text-muted small">
                                        Monitor all your orders in real-time and view order details.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card about-card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-success">Bill Management</h5>
                                    <p class="card-text text-muted small">
                                        View and track your bills with due dates and payment status.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How It Works Section -->
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto">
                    <h3 class="text-success mb-4">How It Works</h3>
                    <div class="card about-card shadow-sm">
                        <div class="card-body">
                            <ol class="mb-0">
                                <li class="mb-3"><strong>Browse</strong> - Explore our catalog of fresh produce and items</li>
                                <li class="mb-3"><strong>Select</strong> - Add items to your cart with your desired quantity</li>
                                <li class="mb-3"><strong>Checkout</strong> - Choose delivery or pickup and confirm your order</li>
                                <li class="mb-3"><strong>Track</strong> - Monitor your order status and upcoming deliveries</li>
                                <li class="mb-3"><strong>Receive Bill</strong> - A bill will be generated and sent to you</li>
                                <li><strong>Pay</strong> - Contact us to arrange payment via bank transfer, check, or cash</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Section -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card about-card shadow-sm bg-success-subtle">
                        <div class="card-body text-center">
                            <h4 class="card-title text-success mb-3">Need Help?</h4>
                            <p class="card-text text-muted">
                                If you have any questions or need assistance, please contact our support team.
                            </p>
                            <p class="mb-0">
                                <strong>Email:</strong> support@freshtrack.com<br>
                                <strong>Phone:</strong> +63 917 677 1234
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-light border-top mt-5 py-4">
        <div class="container-lg text-center text-muted">
            <small>&copy; 2026 FreshTrack. All rights reserved.</small>
        </div>
    </footer>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>

</html>