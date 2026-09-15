<?php
/**
 * AUTO HUB - Database Configuration & Connection
 * Compatible with XAMPP / Apache / MySQL / phpMyAdmin
 */

$host = 'localhost';
$dbname = 'auto_hub';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // If running as API, return JSON error; otherwise display readable message
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
        exit;
    }
    die('Database connection failed. Please ensure MySQL is running in XAMPP: ' . $e->getMessage());
}

/**
 * Helper to get PDO instance
 */
function get_db(): PDO {
    global $pdo;
    return $pdo;
}
