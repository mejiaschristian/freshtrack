<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>Freshtrack - Login</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css" />
</head>

<body class="d-flex mh-100 align-items-center justify-content-center">
    <div class="container">
        <div class="justify-content-center align-items-center">
            <div class="container">
                <img
                    src="fresh-track.png"
                    alt="FreshTrack Logo"
                    class="img-fluid d-block w-auto z-1 mx-auto" />
                <p class="text-center">Welcome Back!</p>
                <form class="mt-4 text-center" action="dashboard.php">
                    <div class="form-floating mb-3">
                        <input
                            type="email"
                            class="form-control"
                            id="floatingInput"
                            placeholder="name@example.com" />
                        <label for="floatingInput">Email address</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input
                            type="password"
                            class="form-control"
                            id="floatingPassword"
                            placeholder="Password" />
                        <label for="floatingPassword">Password</label>
                    </div>
                    <button
                        type="submit"
                        class="btn btn-success w-100 py-3 rounded-2">
                        Login
                    </button>
                    <a href="#" class="text-decoration-none">Don't have an account? Sign up</a>
                </form>
            </div>
        </div>
    </div>
    <div class="background vh-100">
        <img
            src="login.webp"
            alt=""
            srcset=""
            class="w-100 h-100 object-fit-cover opacity-50" />
    </div>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
</body>

</html>