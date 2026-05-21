<?php
session_start();
require_once 'auth.php';

// login logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $result = login($email, $password);

    if ($result['success']) {
        // Redirect based on user role
        if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff')) {
            header('Location: dashboard.php');
        } else {
            header('Location: shop.php');
        }
        exit();
    } else {
        // Store error message in session to display
        $_SESSION['login_error'] = $result['message'];
    }
}
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>Freshtrack - Login</title>
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
                            <h1 class="login-title">Welcome Back!</h1>
                            <p class="login-subtitle">Sign in to continue to FreshTrack</p>
                        </div>

                        <!-- Error Message -->
                        <?php if (isset($_SESSION['login_error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle-fill me-2"></i>
                                <?php echo $_SESSION['login_error']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php unset($_SESSION['login_error']);
                        endif; ?>

                        <!-- Login Form -->
                        <form method="POST" action="">
                            <div class="form-floating mb-3">
                                <input
                                    type="text"
                                    class="form-control login-input"
                                    id="email"
                                    name="email"
                                    placeholder="Enter your email"
                                    required />
                                <label for="email">Email</label>
                            </div>

                            <div class="form-floating mb-4">
                                <input
                                    type="password"
                                    class="form-control login-input"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required />
                                <label for="password">Password</label>
                            </div>

                            <button
                                type="submit"
                                class="btn btn-login w-100 py-2 fw-6 mb-3">
                                Sign In
                            </button>
                        </form>

                        <!-- Divider -->
                        <div class="divider-text mb-3">
                            <span>New to FreshTrack?</span>
                        </div>

                        <!-- Sign Up Link -->
                        <a href="register.php" class="btn btn-outline-login w-100 py-2 fw-5">
                            Create Account
                        </a>

                        <!-- Footer Links -->
                        <div class="text-center mt-4">
                            <a href="#" class="text-muted text-decoration-none footer-link">Forgot password?</a>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Image -->
                <div class="col-lg-6 d-none opacity-75 d-lg-block login-image-side">
                    <img
                        src="login.avif"
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