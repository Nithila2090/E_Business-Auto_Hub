<?php
/**
 * AUTO HUB - Orders API
 * Handles checkout order creation (COD & Online Card Payment via PayHere) and order tracking
 */

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/payment.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = get_db();
$userId = current_user_id();
$cart_id = get_or_create_cart_id();
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);
    $input = is_array($json) ? array_merge($_POST, $json) : $_POST;
}

$action = $input['action'] ?? $_GET['action'] ?? ($method === 'POST' ? 'create' : 'track');

try {
    // -------------------------------------------------------------------------
    // 1. PLACE ORDER (Create Order & Setup Payment)
    // -------------------------------------------------------------------------
    if ($action === 'create') {
        // Fetch cart items
        $stmt = $pdo->prepare("
            SELECT ci.quantity, p.id AS product_id, p.name, p.price, p.stock_quantity
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.cart_id = ?
        ");
        $stmt->execute([$cart_id]);
        $cartItems = $stmt->fetchAll();

        if (empty($cartItems)) {
            json_response(['success' => false, 'message' => 'Your shopping cart is empty.'], 400);
        }

        // Validate customer shipping details
        $name        = trim($input['shipping_name'] ?? $input['name'] ?? '');
        $phone       = trim($input['shipping_phone'] ?? $input['phone'] ?? '');
        $email       = trim($input['shipping_email'] ?? $input['email'] ?? '');
        $address     = trim($input['shipping_address'] ?? $input['address'] ?? '');
        $city        = trim($input['shipping_city'] ?? $input['city'] ?? '');
        $postalCode  = trim($input['shipping_postal_code'] ?? $input['postal_code'] ?? $input['zip'] ?? '');
        $province    = trim($input['shipping_province'] ?? $input['district'] ?? 'Western Province');
        $rawMethod   = trim($input['payment_method'] ?? 'cod');
        $instructions= trim($input['delivery_instructions'] ?? '');

        if (empty($name) || empty($phone) || empty($email) || empty($address) || empty($city)) {
            json_response(['success' => false, 'message' => 'Please fill in all required delivery information fields.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['success' => false, 'message' => 'Please provide a valid email address.'], 400);
        }

        // Normalize payment method
        $isOnlinePayment = in_array(strtolower($rawMethod), ['card', 'online card payment', 'payhere', 'online_card', 'credit_card']);
        if ($isOnlinePayment) {
            $paymentMethod = 'Online Card Payment';
        } elseif (in_array(strtolower($rawMethod), ['cod', 'cash on delivery', 'cash_on_delivery'])) {
            $paymentMethod = 'Cash on Delivery';
        } else {
            $paymentMethod = $rawMethod;
        }

        // Check stock & calculate subtotal
        $subtotal = 0;
        foreach ($cartItems as $item) {
            if ($item['stock_quantity'] < $item['quantity']) {
                json_response([
                    'success' => false, 
                    'message' => "Insufficient stock for product '{$item['name']}'. Only {$item['stock_quantity']} available."
                ], 400);
            }
            $subtotal += (float)$item['price'] * (int)$item['quantity'];
        }

        // Coupon calculation
        $discount = 0;
        $coupon = $_SESSION['applied_coupon'] ?? null;
        if ($coupon && !empty($coupon['discountPercent'])) {
            $discount = ($subtotal * $coupon['discountPercent']) / 100;
        }

        // Delivery Fee calculation
        $deliveryFee = 350;
        if (str_contains($province, 'Eastern') || str_contains($province, 'Northern') || str_contains($province, 'North Central') || str_contains($province, 'Uva')) {
            $deliveryFee = 500;
        } elseif (str_contains($province, 'Central') || str_contains($province, 'Southern') || str_contains($province, 'North Western') || str_contains($province, 'Sabaragamuwa')) {
            $deliveryFee = 450;
        }

        if ($coupon && !empty($coupon['freeShipping'])) {
            $deliveryFee = 0;
        }

        $totalAmount = max(0, $subtotal - $discount + $deliveryFee);

        // Generate clean standardized order number: AUTO-2026-XXXXX
        $uniqueSuffix = str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $orderNumber = 'AUTO-2026-' . $uniqueSuffix;

        // Ensure unique order_number in database
        $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
        $checkStmt->execute([$orderNumber]);
        if ($checkStmt->fetch()) {
            $orderNumber = 'AUTO-2026-' . strtoupper(dechex(time())) . mt_rand(10, 99);
        }

        // Initial order status
        $initialOrderStatus = $isOnlinePayment ? 'Pending Payment' : 'Pending';

        // Start Transaction
        $pdo->beginTransaction();

        // 1. Insert into orders table
        $stmtOrder = $pdo->prepare("
            INSERT INTO orders (
                order_number, user_id, total_amount, delivery_fee, discount_amount,
                payment_method, payment_status, status, shipping_name, shipping_phone, shipping_email,
                shipping_address, shipping_city, shipping_postal_code, shipping_province, delivery_instructions
            ) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtOrder->execute([
            $orderNumber,
            $userId,
            $totalAmount,
            $deliveryFee,
            $discount,
            $paymentMethod,
            $initialOrderStatus,
            $name,
            $phone,
            $email,
            $address,
            $city,
            $postalCode,
            $province,
            $instructions
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // 2. Insert into order_items table & decrement product stock for COD
        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmtStock = $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");

        foreach ($cartItems as $item) {
            $stmtItem->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
            if (!$isOnlinePayment) {
                $stmtStock->execute([$item['quantity'], $item['product_id']]);
            }
        }

        // 3. Create initial Pending record in payments table
        $stmtPayment = $pdo->prepare("
            INSERT INTO payments (
                order_id, payment_method, payment_gateway, amount, currency, transaction_id, payment_status, status_message, paid_at, created_at
            ) VALUES (?, ?, ?, ?, 'LKR', NULL, 'Pending', 'Awaiting payment from gateway', NULL, NOW())
        ");
        $stmtPayment->execute([
            $orderId, 
            $paymentMethod, 
            $isOnlinePayment ? 'PayHere' : 'Cash on Delivery', 
            $totalAmount
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        // 4. Clear cart items and coupon
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cart_id]);
        unset($_SESSION['applied_coupon']);

        $pdo->commit();

        if ($isOnlinePayment) {
            // Build PayHere Payload
            $paymentConfig = get_payment_config();
            $orderRecord = [
                'id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount
            ];
            $customerRecord = [
                'shipping_name' => $name,
                'shipping_email' => $email,
                'shipping_phone' => $phone,
                'shipping_address' => $address,
                'shipping_city' => $city
            ];

            $paymentData = build_payhere_checkout_payload($orderRecord, $customerRecord);

            // Safe request logging (No secrets or card numbers logged)
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $safeLogEntry = [
                'timestamp'   => date('Y-m-d H:i:s'),
                'event'       => 'PAYMENT_REQUEST_GENERATED',
                'merchant_id' => $paymentConfig['merchant_id'],
                'order_id'    => $orderNumber,
                'amount'      => number_format((float)$totalAmount, 2, '.', ''),
                'currency'    => $paymentConfig['currency'],
                'sandbox'     => $paymentConfig['sandbox'],
                'notify_url'  => $paymentConfig['notify_url'],
                'client_ip'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ];
            @file_put_contents($logDir . '/payment_requests.log', json_encode($safeLogEntry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);

            json_response([
                'success'          => true,
                'payment_required' => true,
                'payment_method'   => 'Online Card Payment',
                'payment_status'   => 'Pending',
                'order_status'     => $initialOrderStatus,
                'order_number'     => $orderNumber,
                'order_id'         => $orderId,
                'total_amount'     => $totalAmount,
                'formatted_total'  => format_price($totalAmount),
                'gateway_url'      => $paymentConfig['gateway_url'],
                'payment_data'     => $paymentData,
                'payment_page_url' => 'payment.php?order_id=' . urlencode($orderNumber),
                'message'          => 'Order created. Proceeding to secure online payment...',
                'redirect'         => 'payment.php?order_id=' . urlencode($orderNumber)
            ]);
        } else {
            // Cash on Delivery
            json_response([
                'success'          => true,
                'payment_required' => false,
                'payment_method'   => 'Cash on Delivery',
                'payment_status'   => 'Pending',
                'order_status'     => 'Pending',
                'order_number'     => $orderNumber,
                'order_id'         => $orderId,
                'total_amount'     => $totalAmount,
                'formatted_total'  => format_price($totalAmount),
                'message'          => 'Your order has been placed successfully with Cash on Delivery!',
                'redirect'         => 'order-confirmation.php?order_id=' . urlencode($orderNumber)
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // 2. TRACK ORDER (Lookup by order number or ID)
    // -------------------------------------------------------------------------
    if ($action === 'track' || $action === 'get') {
        $orderQuery = trim($_GET['order_number'] ?? $_GET['order_id'] ?? $input['order_number'] ?? $input['order_id'] ?? '');

        if (empty($orderQuery)) {
            json_response(['success' => false, 'message' => 'Please provide an Order ID or Order Number.'], 400);
        }

        $stmt = $pdo->prepare("
            SELECT * FROM orders 
            WHERE order_number = ? OR id = ? 
            LIMIT 1
        ");
        $stmt->execute([$orderQuery, is_numeric($orderQuery) ? (int)$orderQuery : 0]);
        $order = $stmt->fetch();

        if (!$order) {
            json_response(['success' => false, 'message' => "Order \"$orderQuery\" was not found in our records."], 404);
        }

        // Fetch items
        $stmtItems = $pdo->prepare("
            SELECT oi.*, p.name, p.image, p.sku, p.brand
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$order['id']]);
        $items = $stmtItems->fetchAll();

        foreach ($items as &$it) {
            $it['formatted_price'] = format_price($it['price']);
            $it['formatted_total'] = format_price($it['price'] * $it['quantity']);
        }

        // Fetch payment record
        $stmtPayment = $pdo->prepare("
            SELECT * FROM payments 
            WHERE order_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmtPayment->execute([$order['id']]);
        $payment = $stmtPayment->fetch();

        $order['payment'] = $payment ?: null;
        $order['payment_status'] = $payment['payment_status'] ?? 'Pending';
        $order['transaction_id'] = $payment['transaction_id'] ?? null;
        $order['paid_at'] = $payment['paid_at'] ?? null;

        $order['items'] = $items;
        $order['formatted_total'] = format_price($order['total_amount']);
        $order['formatted_delivery_fee'] = format_price($order['delivery_fee']);
        $order['formatted_discount'] = format_price($order['discount_amount']);
        $order['formatted_date'] = date('d M Y, h:i A', strtotime($order['created_at']));

        // Timeline status indexing
        $statuses = ['pending payment', 'pending', 'processing', 'confirmed', 'shipped', 'delivered'];
        $currentStatus = strtolower($order['status']);
        $statusIndex = array_search($currentStatus, $statuses);
        $order['status_step'] = ($statusIndex !== false) ? min(4, max(1, $statusIndex)) : 1;

        json_response([
            'success' => true,
            'data' => $order
        ]);
    }

    json_response(['success' => false, 'message' => 'Invalid action requested.'], 400);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
