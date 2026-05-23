<?php
session_start();
require_once 'auth.php';
require_once 'functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$message = "";
$messageType = "";

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        try {
            $fullName = $_POST['fullName'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $role = $_POST['role'] ?? 'staff';

            if (!empty($fullName) && !empty($email) && !empty($password)) {
                $stmt = $pdo->prepare("INSERT INTO tblusers (fullName, email, password, role) VALUES (:fullName, :email, :password, :role)");
                $stmt->execute([
                    ':fullName' => $fullName,
                    ':email' => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':role' => $role
                ]);

                $message = "User '$fullName' added successfully!";
                $messageType = "success";
            } else {
                $message = "Please fill in all required fields.";
                $messageType = "warning";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $message = "Email already exists!";
            } else {
                $message = "Error: " . $e->getMessage();
            }
            $messageType = "danger";
        }
    } elseif ($action === 'edit') {
        try {
            $userID = $_POST['userID'];
            $fullName = $_POST['fullName'];
            $email = $_POST['email'];
            $role = $_POST['role'];

            if (!empty($userID) && !empty($fullName) && !empty($email)) {
                $stmt = $pdo->prepare("UPDATE tblusers SET fullName = :fullName, email = :email, role = :role WHERE userID = :userID");
                $stmt->execute([
                    ':userID' => $userID,
                    ':fullName' => $fullName,
                    ':email' => $email,
                    ':role' => $role
                ]);

                $message = "User updated successfully!";
                $messageType = "success";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    } elseif ($action === 'delete') {
        try {
            $userID = $_POST['userID'];

            if ($userID != $_SESSION['user_id']) {
                $stmt = $pdo->prepare("DELETE FROM tblusers WHERE userID = :userID");
                $stmt->execute([':userID' => $userID]);
                $message = "User deleted successfully!";
                $messageType = "success";
            } else {
                $message = "You cannot delete your own account!";
                $messageType = "warning";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

$users = getAllUsers($pdo);
?>

<!doctype html>
<html lang="en" data-bs-theme="light">

<head>
    <title>FreshTrack - User Management</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <!-- Navbar -->
    <header class="sticky-top">
        <nav class="navbar navbar-expand-sm navbar-dark bg-success">
            <div class="container-fluid w-75">
                <a class="navbar-brand me-auto" href="dashboard.php">
                    <img src="fresh-track.png" alt="FreshTrack" class="img-fluid" />
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="inventory.php">Inventory</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="transactions.php">Transactions</a></li>
                        <li class="nav-item"><a class="nav-link active" href="users.php">Users</a></li>
                        <li class="border-start border-success-subtle ps-3 nav-item dropdown d-flex align-items-center mx-3">
                            <img src="user-icon.svg" alt="user-icon" width="35">
                            <a
                                class="nav-link dropdown-toggle"
                                href="#"
                                id="dropdownId"
                                data-bs-toggle="dropdown"
                                aria-haspopup="true"
                                aria-expanded="false">
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

    <main class="container-fluid mt-5 w-75">
        <div class="row mb-4">
            <div class="col">
                <h2 class="mb-0">User Management</h2>
                <p class="text-muted">Manage system users and permissions</p>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add User Button -->
        <div class="mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                + Add User
            </button>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Users List</h5>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>#<?php echo $user['userID']; ?></td>
                                    <td><?php echo htmlspecialchars($user['fullName']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php
                                        $badgeColor = match ($user['role']) {
                                            'admin' => 'bg-danger',
                                            'staff' => 'bg-info',
                                            default => 'bg-success'
                                        };
                                        ?>
                                        <span class="badge <?php echo $badgeColor; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['createdAt'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            Edit
                                        </button>
                                        <?php if ($user['userID'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['userID']; ?>)">
                                                Delete
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label for="fullName" class="form-label">Full Name</label>
                            <input type="text" id="fullName" name="fullName" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-control" required>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="editUserID" name="userID">

                        <div class="mb-3">
                            <label for="editFullName" class="form-label">Full Name</label>
                            <input type="text" id="editFullName" name="fullName" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" id="editEmail" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="editRole" class="form-label">Role</label>
                            <select id="editRole" name="role" class="form-control" required>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" id="deleteUserID" name="userID">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('editUserID').value = user.userID;
            document.getElementById('editFullName').value = user.fullName;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editRole').value = user.role;
        }

        function deleteUser(userID) {
            if (confirm('Are you sure you want to delete this user?')) {
                document.getElementById('deleteUserID').value = userID;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>

</html>