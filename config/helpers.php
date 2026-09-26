<?php
/**
 * AUTO HUB - Helper Functions & Utilities
 * Security, Auth, Sessions, CSRF, JSON Responses, File Uploads, Cart/Wishlist Utilities
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';

// Configurable Base URL
if (!defined('BASE_URL')) {
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        define('BASE_URL', 'http://localhost/EBusiness_Project');
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $cleanDir = preg_replace('/(\/api|\/admin|\/config|\/libs)$/', '', $scriptDir);
        if ($cleanDir === '/' || $cleanDir === '.') $cleanDir = '';
        $base = rtrim($protocol . $host . $cleanDir, '/');
        define('BASE_URL', $base ?: 'http://localhost/EBusiness_Project');
    }
}

require_once __DIR__ . '/payment.php';

// Safe Session Initialization
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Ensure session is always started when helpers is included
init_session();

/**
 * HTML Escaping shorthand
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Currency Formatter (Sri Lankan Rupee)
 */
function format_price(float|int|string $amount): string {
    return 'Rs. ' . number_format((float)$amount, 0);
}

/**
 * Authentication Checkers
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_user_name(): string {
    return $_SESSION['user_name'] ?? 'Guest';
}

function current_user_email(): string {
    return $_SESSION['user_email'] ?? '';
}

function current_user_role(): string {
    return $_SESSION['role'] ?? 'guest';
}

function require_login(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirect");
        exit;
    }
}

function require_admin(string $redirect = '../admin/login.php'): void {
    if (!is_admin()) {
        $_SESSION['flash_error'] = 'Administrator access required.';
        header("Location: $redirect");
        exit;
    }
}

/**
 * CSRF Protection
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token(): string {
    return generate_csrf_token();
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '">';
}

function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * JSON Response Helper for APIs
 */
function json_response(mixed $data, int $status_code = 200): void {
    if (!headers_sent()) {
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Secure Image File Upload Helper
 * Allowed types: JPEG, PNG, WEBP. Max size: 5MB.
 */
function handle_image_upload(array $file, string $subfolder = 'products'): array {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error occurred.'];
    }

    // Max 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File size exceeds maximum limit of 5MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($mime, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid image format. Only JPG, PNG, and WEBP are supported.'];
    }

    $ext = $allowed_types[$mime];
    $filename = uniqid($subfolder . '_', true) . '.' . $ext;
    
    $target_dir = __DIR__ . '/../images/' . $subfolder . '/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $target_path = $target_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return [
            'success' => true,
            'file_path' => 'images/' . $subfolder . '/' . $filename,
            'filename' => $filename
        ];
    }

    return ['success' => false, 'error' => 'Failed to save uploaded file to destination directory.'];
}

/**
 * Cart Helper Functions
 */
function get_session_id(): string {
    if (empty($_SESSION['guest_cart_id'])) {
        $_SESSION['guest_cart_id'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['guest_cart_id'];
}

function get_or_create_cart_id(): int {
    $pdo = get_db();
    $user_id = current_user_id();
    $session_id = get_session_id();

    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $cart = $stmt->fetch();
        if ($cart) {
            return (int)$cart['id'];
        }

        // Create user cart
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, session_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $session_id]);
        return (int)$pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE session_id = ? AND user_id IS NULL LIMIT 1");
        $stmt->execute([$session_id]);
        $cart = $stmt->fetch();
        if ($cart) {
            return (int)$cart['id'];
        }

        // Create guest cart
        $stmt = $pdo->prepare("INSERT INTO cart (session_id) VALUES (?)");
        $stmt->execute([$session_id]);
        return (int)$pdo->lastInsertId();
    }
}

function sync_guest_cart_to_user(int $user_id): void {
    $pdo = get_db();
    $session_id = get_session_id();

    // Check if there is a guest cart for this session
    $stmt = $pdo->prepare("SELECT id FROM cart WHERE session_id = ? AND user_id IS NULL LIMIT 1");
    $stmt->execute([$session_id]);
    $guest_cart = $stmt->fetch();

    if ($guest_cart) {
        $guest_cart_id = $guest_cart['id'];

        // Get or create user cart
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user_cart = $stmt->fetch();

        if (!$user_cart) {
            // Simply associate guest cart to user
            $stmt = $pdo->prepare("UPDATE cart SET user_id = ? WHERE id = ?");
            $stmt->execute([$user_id, $guest_cart_id]);
        } else {
            $user_cart_id = $user_cart['id'];
            // Transfer items
            $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$guest_cart_id]);
            $guest_items = $stmt->fetchAll();

            foreach ($guest_items as $item) {
                $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) 
                                       VALUES (?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
                $stmt->execute([$user_cart_id, $item['product_id'], $item['quantity']]);
            }

            // Delete guest cart
            $pdo->prepare("DELETE FROM cart WHERE id = ?")->execute([$guest_cart_id]);
        }
    }
}

function get_cart_count(): int {
    $pdo = get_db();
    $cart_id = get_or_create_cart_id();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cart_id]);
    return (int)($stmt->fetch()['total'] ?? 0);
}

function get_wishlist_count(): int {
    if (!is_logged_in()) return 0;
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM wishlist WHERE user_id = ?");
    $stmt->execute([current_user_id()]);
    return (int)($stmt->fetch()['total'] ?? 0);
}

/**
 * Flash Notification Helpers
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
