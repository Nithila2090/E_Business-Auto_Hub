<?php
/**
 * AUTO HUB - Categories API
 * Returns active categories with product counts
 */

require_once __DIR__ . '/../config/helpers.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = get_db();

try {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(p.id) AS product_count 
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY c.id ASC
    ");
    $categories = $stmt->fetchAll();

    json_response(['success' => true, 'data' => $categories]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
