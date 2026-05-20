<?php
session_start();
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email            = trim($_POST['email'] ?? '');
    $fullName         = trim($_POST['fullName'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Check passwords first before calling register
    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match.";
    } else {
        $result = register($email, $fullName, $password); // only called once

        if ($result['success']) {
            if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
                header('Location: dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit();
        } else {
            $_SESSION['register_error'] = $result['message'];
        }
    }
}
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>Freshtrack - Sign Up</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link
        href="bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css" />
</head>

<body class="login-body">
    <div class="login-container">
        <div class="login-card-wrapper">
            <div class="row g-0">
                <!-- Left Side - Login Form -->
                <div class="col-lg-6 col-md-12">
                    <div class="login-form-content">
                        <!-- Logo -->
                        <div class="text-center mb-4">
                            <img
                                src="fresh-track.png"
                                alt="FreshTrack Logo"
                                class="login-logo mb-3" />
                            <h1 class="login-title">Create Account</h1>
                            <p class="login-subtitle">Sign up to get started with FreshTrack</p>
                        </div>

                        <!-- Error Message -->
                        <?php if (isset($_SESSION['register_error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-2"></i>
                                <?php echo $_SESSION['register_error']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php unset($_SESSION['register_error']);
                        endif; ?>

                        <!-- Login Form -->
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label fw-5">Email</label>
                                <input
                                    type="text"
                                    class="form-control login-input"
                                    id="email"
                                    name="email"
                                    placeholder="Enter your email"
                                    required />
                            </div>

                            <div class="mb-3">
                                <label for="fullName" class="form-label fw-5">Hotel Name</label>
                                <input
                                    type="text"
                                    class="form-control login-input"
                                    id="fullName"
                                    name="fullName"
                                    placeholder="Enter your hotel name"
                                    required />
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label fw-5">Password</label>
                                <input
                                    type="password"
                                    class="form-control login-input"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required />
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-5">Confirm Password</label>
                                <input
                                    type="password"
                                    class="form-control login-input"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Confirm your password"
                                    required />
                            </div>

                            <button
                                type="submit"
                                class="btn btn-login w-100 py-2 fw-6 mb-3">
                                Create Account
                            </button>
                        </form>

                        <!-- Divider -->
                        <div class="divider-text mb-3">
                            <span>Already have an account?</span>
                        </div>

                        <!-- Sign Up Link -->
                        <a href="index.php" class="btn btn-outline-login w-100 py-2 fw-5">
                            Sign In
                        </a>
                    </div>
                </div>

                <!-- Right Side - Image -->
                <div class="col-lg-6 d-none d-lg-block login-image-side">
                    <img
                        src="login.webp"
                        alt="Login Background"
                        class="w-100 h-100 object-fit-cover" />
                </div>
            </div>
        </div>
    </div>
    <script
        src="bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>

</html>