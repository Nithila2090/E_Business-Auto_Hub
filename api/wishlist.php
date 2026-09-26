<?php
/**
 * AUTO HUB - Wishlist API
 * Wishlist management for authenticated customers
 */

require_once __DIR__ . '/../config/helpers.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = get_db();
$userId = current_user_id();
$method = $_SERVER['REQUEST_METHOD'];

// Parse incoming request payload (JSON or Form POST)
$input = [];
if ($method === 'POST' || $method === 'DELETE') {
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);
    $input = is_array($json) ? array_merge($_POST, $json) : $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? ($method === 'GET' ? 'get' : 'toggle');

try {
    // 1. GET Wishlist
    if ($method === 'GET' || $action === 'get') {
        if (!$userId) {
            json_response([
                'success' => true,
                'logged_in' => false,
                'data' => [
                    'ids' => [],
                    'items' => [],
                    'count' => 0
                ]
            ]);
        }

        $stmt = $pdo->prepare("
            SELECT w.id AS wishlist_id, w.created_at, p.id AS product_id, p.name, p.slug, p.brand,
                   p.price, p.old_price, p.discount, p.image, p.stock_quantity, p.sku, p.rating,
                   c.name AS category_name
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE w.user_id = ?
            ORDER BY w.id DESC
        ");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();

        $ids = array_map(fn($item) => (int)$item['product_id'], $items);

        foreach ($items as &$item) {
            $item['in_stock'] = $item['stock_quantity'] > 0;
            $item['formatted_price'] = format_price($item['price']);
            $item['formatted_old_price'] = $item['old_price'] ? format_price($item['old_price']) : null;
        }

        json_response([
            'success' => true,
            'logged_in' => true,
            'data' => [
                'ids' => $ids,
                'items' => $items,
                'count' => count($items)
            ]
        ]);
    }

    // 2. TOGGLE Wishlist (Add or Remove)
    if ($action === 'toggle') {
        if (!$userId) {
            json_response([
                'success' => false,
                'require_login' => true,
                'message' => 'Please sign in to save items to your wishlist.'
            ], 401);
        }

        $productId = (int)($input['product_id'] ?? 0);
        if ($productId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid Product ID'], 400);
        }

        // Check if already in wishlist
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch();

        // Get product name
        $stmtName = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmtName->execute([$productId]);
        $prod = $stmtName->fetch();
        $prodName = $prod['name'] ?? 'Product';

        if ($existing) {
            $stmtDel = $pdo->prepare("DELETE FROM wishlist WHERE id = ?");
            $stmtDel->execute([$existing['id']]);
            $count = get_wishlist_count();
            json_response([
                'success' => true,
                'in_wishlist' => false,
                'message' => "Removed <strong>" . e($prodName) . "</strong> from your wishlist",
                'count' => $count
            ]);
        } else {
            $stmtAdd = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $stmtAdd->execute([$userId, $productId]);
            $count = get_wishlist_count();
            json_response([
                'success' => true,
                'in_wishlist' => true,
                'message' => "Added <strong>" . e($prodName) . "</strong> to your wishlist!",
                'count' => $count
            ]);
        }
    }

    // 3. REMOVE from Wishlist
    if ($action === 'remove') {
        if (!$userId) {
            json_response(['success' => false, 'require_login' => true, 'message' => 'Please sign in'], 401);
        }

        $productId = (int)($input['product_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);

        json_response([
            'success' => true,
            'in_wishlist' => false,
            'message' => 'Item removed from wishlist',
            'count' => get_wishlist_count()
        ]);
    }

    json_response(['success' => false, 'message' => 'Invalid action'], 400);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
