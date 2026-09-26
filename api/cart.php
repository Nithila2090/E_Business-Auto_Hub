<?php
/**
 * AUTO HUB - Cart API
 * Persistent Database & Session Cart Management
 */

require_once __DIR__ . '/../config/helpers.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = get_db();
$cart_id = get_or_create_cart_id();
$method = $_SERVER['REQUEST_METHOD'];

// Parse incoming request payload (JSON or Form POST)
$input = [];
if ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);
    $input = is_array($json) ? array_merge($_POST, $json) : $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? ($method === 'GET' ? 'get' : 'add');

try {
    // 1. GET Cart
    if ($method === 'GET' || $action === 'get') {
        $stmt = $pdo->prepare("
            SELECT ci.id AS item_id, ci.quantity, p.id AS product_id, p.name, p.slug, p.brand, 
                   p.price, p.old_price, p.discount, p.image, p.stock_quantity, p.sku,
                   c.name AS category_name
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE ci.cart_id = ?
            ORDER BY ci.id DESC
        ");
        $stmt->execute([$cart_id]);
        $items = $stmt->fetchAll();

        $subtotal = 0;
        $totalItems = 0;
        foreach ($items as &$item) {
            $itemTotal = (float)$item['price'] * (int)$item['quantity'];
            $item['item_total'] = $itemTotal;
            $item['formatted_price'] = format_price($item['price']);
            $item['formatted_item_total'] = format_price($itemTotal);
            $subtotal += $itemTotal;
            $totalItems += (int)$item['quantity'];
        }

        // Coupon calculation
        $coupon = $_SESSION['applied_coupon'] ?? null;
        $discount = 0;
        if ($coupon) {
            if (!empty($coupon['discountPercent'])) {
                $discount = ($subtotal * $coupon['discountPercent']) / 100;
            }
        }

        // Shipping calculation
        $province = $_GET['province'] ?? $_SESSION['shipping_province'] ?? 'Western Province (Colombo, Gampaha, Kalutara)';
        $shipping = 350; // Default Western Province
        if (str_contains($province, 'Eastern') || str_contains($province, 'Northern') || str_contains($province, 'North Central') || str_contains($province, 'Uva')) {
            $shipping = 500;
        } elseif (str_contains($province, 'Central') || str_contains($province, 'Southern') || str_contains($province, 'North Western') || str_contains($province, 'Sabaragamuwa')) {
            $shipping = 450;
        }

        if ($coupon && !empty($coupon['freeShipping'])) {
            $shipping = 0;
        }

        $grandTotal = max(0, $subtotal - $discount + $shipping);

        json_response([
            'success' => true,
            'data' => [
                'items' => $items,
                'subtotal' => $subtotal,
                'formatted_subtotal' => format_price($subtotal),
                'discount' => $discount,
                'formatted_discount' => format_price($discount),
                'shipping' => $shipping,
                'formatted_shipping' => format_price($shipping),
                'total' => $grandTotal,
                'formatted_total' => format_price($grandTotal),
                'count' => $totalItems,
                'coupon' => $coupon
            ]
        ]);
    }

    // 2. ADD Item to Cart
    if ($action === 'add') {
        $productId = (int)($input['product_id'] ?? 0);
        $quantity = max(1, (int)($input['quantity'] ?? 1));

        if ($productId <= 0) {
            json_response(['success' => false, 'message' => 'Valid Product ID is required'], 400);
        }

        // Check product and stock
        $stmt = $pdo->prepare("SELECT id, name, price, stock_quantity, status FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product || $product['status'] !== 'active') {
            json_response(['success' => false, 'message' => 'Product not found or unavailable'], 404);
        }

        if ($product['stock_quantity'] < 1) {
            json_response(['success' => false, 'message' => 'Sorry, this product is currently out of stock.'], 400);
        }

        // Check existing in cart
        $stmt = $pdo->prepare("SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cart_id, $productId]);
        $existing = $stmt->fetch();

        $newQty = $quantity;
        if ($existing) {
            $newQty += (int)$existing['quantity'];
        }

        if ($newQty > $product['stock_quantity']) {
            json_response([
                'success' => false, 
                'message' => "Only {$product['stock_quantity']} units available in stock."
            ], 400);
        }

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
            $stmt->execute([$newQty, $cart_id, $productId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$cart_id, $productId, $quantity]);
        }

        $cartCount = get_cart_count();

        json_response([
            'success' => true,
            'message' => "<strong>" . e($product['name']) . "</strong> added to cart! ($quantity item" . ($quantity > 1 ? 's' : '') . ")",
            'cart_count' => $cartCount,
            'product_name' => $product['name']
        ]);
    }

    // 3. UPDATE Item Quantity
    if ($action === 'update') {
        $productId = (int)($input['product_id'] ?? 0);
        $quantity = (int)($input['quantity'] ?? 1);

        if ($productId <= 0) {
            json_response(['success' => false, 'message' => 'Invalid Product ID'], 400);
        }

        if ($quantity <= 0) {
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
            $stmt->execute([$cart_id, $productId]);
            json_response([
                'success' => true, 
                'message' => 'Item removed from cart', 
                'cart_count' => get_cart_count()
            ]);
        }

        // Validate stock
        $stmt = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $prod = $stmt->fetch();

        if ($quantity > $prod['stock_quantity']) {
            json_response([
                'success' => false, 
                'message' => "Only {$prod['stock_quantity']} units available in stock."
            ], 400);
        }

        $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$quantity, $cart_id, $productId]);

        json_response([
            'success' => true,
            'message' => 'Cart updated',
            'cart_count' => get_cart_count()
        ]);
    }

    // 4. REMOVE Item
    if ($action === 'remove') {
        $productId = (int)($input['product_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cart_id, $productId]);

        json_response([
            'success' => true,
            'message' => 'Item removed from cart',
            'cart_count' => get_cart_count()
        ]);
    }

    // 5. CLEAR Cart
    if ($action === 'clear') {
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
        $stmt->execute([$cart_id]);

        json_response([
            'success' => true,
            'message' => 'Cart cleared',
            'cart_count' => 0
        ]);
    }

    // 6. APPLY Coupon
    if ($action === 'apply_coupon') {
        $code = strtoupper(trim($input['coupon_code'] ?? ''));

        if ($code === 'AUTOHUB10') {
            $_SESSION['applied_coupon'] = [
                'code' => 'AUTOHUB10',
                'discountPercent' => 10,
                'description' => '10% Launch Discount'
            ];
            json_response([
                'success' => true,
                'message' => 'Coupon <strong>AUTOHUB10</strong> applied! 10% discount added.',
                'coupon' => $_SESSION['applied_coupon']
            ]);
        } elseif ($code === 'FREESHIP') {
            $_SESSION['applied_coupon'] = [
                'code' => 'FREESHIP',
                'freeShipping' => true,
                'description' => 'Free Islandwide Delivery'
            ];
            json_response([
                'success' => true,
                'message' => 'Coupon <strong>FREESHIP</strong> applied! Free shipping active.',
                'coupon' => $_SESSION['applied_coupon']
            ]);
        } else {
            json_response([
                'success' => false,
                'message' => 'Invalid coupon code. Try <strong>AUTOHUB10</strong> or <strong>FREESHIP</strong>.'
            ], 400);
        }
    }

    // 7. REMOVE Coupon
    if ($action === 'remove_coupon') {
        unset($_SESSION['applied_coupon']);
        json_response(['success' => true, 'message' => 'Coupon removed']);
    }

    json_response(['success' => false, 'message' => 'Unknown cart action'], 400);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
