<?php
require_once 'db.php';

/**
 * Login function to authenticate user with email and password
 * 
 * @param string $email - User's email
 * @param string $password - User's password
 * @return array - Array with 'success' (bool) and 'message' (string)
 */
function login($email, $password)
{
    global $pdo;

    // Validate input
    if (empty($email) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Email and password are required.'
        ];
    }

    try {
        // Query to find user by email
        $stmt = $pdo->prepare("SELECT userID, fullName, email, password, role FROM tblusers WHERE email = :email");
        $stmt->execute([
            ':email' => $email,
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }

        // Verify password (hashed)
        if (!password_verify($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }

        // Password is correct - set session variables
        $_SESSION['user_id'] = $user['userID'];
        $_SESSION['username'] = $user['fullName'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        if ($user['role'] === 'admin') {
            $_SESSION['is_admin'] = true;
        }
        $_SESSION['logged_in'] = true;

        return [
            'success' => true,
            'message' => 'Login successful!'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Login Error: ' . $e->getMessage()
        ];
    }
}

function register($email, $hotelName, $password)
{
    global $pdo;

    // Validate input
    if (empty($email) || empty($hotelName) || empty($password)) {
        return [
            'success' => false,
            'message' => 'Email, hotel name, and password are required.'
        ];
    }

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT userID FROM tblusers WHERE email = :email");
        $stmt->execute([
            ':email' => $email,
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return [
                'success' => false,
                'message' => 'Email is already registered.'
            ];
        }

        // Insert new user into database
        $stmt = $pdo->prepare("INSERT INTO tblusers (fullName, email, password, role) VALUES (:fullName, :email, :password, 'user')");
        $result = $stmt->execute([
            ':fullName' => $hotelName,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT) // Hash the password
        ]);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Registration successful! You can now log in.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Registration Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Logout function - destroy session
 */
function logout()
{
    $_SESSION = [];
    session_destroy();
    return [
        'success' => true,
        'message' => 'Logged out successfully.'
    ];
}

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function isLoggedIn()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get current logged in user
 * 
 * @return array|null - User data or null if not logged in
 */
function getCurrentUser()
{
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}
