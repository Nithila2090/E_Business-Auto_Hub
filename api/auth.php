<?php
/**
 * AUTO HUB - Authentication API
 * Login, Register, Status Check, and Logout
 */

require_once __DIR__ . '/../config/helpers.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = get_db();
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);
    $input = is_array($json) ? array_merge($_POST, $json) : $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? 'status';

try {
    // 1. Status Check
    if ($action === 'status') {
        json_response([
            'success' => true,
            'logged_in' => is_logged_in(),
            'user' => is_logged_in() ? [
                'id' => current_user_id(),
                'name' => current_user_name(),
                'email' => current_user_email(),
                'role' => current_user_role()
            ] : null,
            'cart_count' => get_cart_count(),
            'wishlist_count' => get_wishlist_count()
        ]);
    }

    // 2. Login
    if ($action === 'login') {
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if (empty($email) || empty($password)) {
            json_response(['success' => false, 'message' => 'Please provide both email and password.'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            json_response(['success' => false, 'message' => 'Invalid email or password. Please try again.'], 401);
        }

        // Set session
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Transfer guest cart to logged-in user
        sync_guest_cart_to_user($user['id']);

        $redirectUrl = ($user['role'] === 'admin') 
            ? 'admin/dashboard.php' 
            : ($_SESSION['redirect_after_login'] ?? 'index.php');
        unset($_SESSION['redirect_after_login']);

        json_response([
            'success' => true,
            'message' => 'Login successful! Redirecting...',
            'role' => $user['role'],
            'redirect' => $redirectUrl,
            'user' => [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    }

    // 3. Register
    if ($action === 'register') {
        $fullName = trim($input['full_name'] ?? $input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $phone = trim($input['phone'] ?? '');
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (empty($fullName) || empty($email) || empty($phone) || empty($password)) {
            json_response(['success' => false, 'message' => 'All required fields must be filled.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
        }

        if (strlen($password) < 6) {
            json_response(['success' => false, 'message' => 'Password must be at least 6 characters long.'], 400);
        }

        if (!empty($confirmPassword) && $password !== $confirmPassword) {
            json_response(['success' => false, 'message' => 'Passwords do not match.'], 400);
        }

        // Check existing email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            json_response(['success' => false, 'message' => 'This email address is already registered. Please log in.'], 400);
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
        $stmt->execute([$fullName, $email, $phone, $hashedPassword]);
        $newUserId = (int)$pdo->lastInsertId();

        // Automatically log in newly registered customer
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;
        $_SESSION['role'] = 'customer';

        sync_guest_cart_to_user($newUserId);

        json_response([
            'success' => true,
            'message' => "Welcome to AUTO HUB, <strong>" . e($fullName) . "</strong>! Your account was created successfully.",
            'redirect' => 'index.php'
        ]);
    }

    // 4. Logout
    if ($action === 'logout') {
        session_unset();
        session_destroy();
        json_response(['success' => true, 'message' => 'Logged out successfully', 'redirect' => 'login.php']);
    }

    json_response(['success' => false, 'message' => 'Invalid action'], 400);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
