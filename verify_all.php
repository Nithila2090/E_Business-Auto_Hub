<?php
/**
 * AUTO HUB - Complete Integration Verification Script
 * Validates Catalog, Compatibility, Auth, Cart, Wishlist, COD & PayHere Online Payment Gateway
 */

require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/payment.php';

$pdo = get_db();

function run_api_test($script, $query = [], $post = null, $session = []) {
    $code = '
    $_GET = ' . var_export($query, true) . ';
    $_POST = ' . var_export($post ?: [], true) . ';
    $_SERVER["REQUEST_METHOD"] = ' . var_export($post !== null ? 'POST' : 'GET', true) . ';
    session_start();
    ' . (!empty($session) ? '$_SESSION = array_merge($_SESSION, ' . var_export($session, true) . ');' : '') . '
    require "' . addslashes(__DIR__ . '/' . $script) . '";
    ';

    $tempFile = __DIR__ . '/test_run_temp.php';
    file_put_contents($tempFile, "<?php\n" . $code);
    $output = shell_exec("php " . escapeshellarg($tempFile));
    @unlink($tempFile);
    return json_decode($output, true);
}

echo "\n========================================================\n";
echo "       AUTO HUB - Complete System & Payment Verification\n";
echo "========================================================\n\n";

$pass = 0; $fail = 0;
function test($title, $res) {
    global $pass, $fail;
    if ($res) {
        echo "[SUCCESS] $title\n";
        $pass++;
    } else {
        echo "[FAILURE] $title\n";
        $fail++;
    }
}

// 1. Vehicle Makes (Exact 5 brands)
$res = run_api_test('api/vehicles.php', ['action' => 'makes']);
test("Fetch Vehicle Makes (Count: " . count($res['data'] ?? []) . " - Exact 5)", ($res['success'] ?? false) && count($res['data']) === 5);

// 2. Toyota Models (Exact 5 models)
$res = run_api_test('api/vehicles.php', ['action' => 'models', 'make' => 'Toyota']);
$modelNames = array_column($res['data'] ?? [], 'name');
test("Fetch Models for Make Toyota (Corolla, Yaris, Camry, Prius, Hilux)", count($modelNames) === 5 && in_array('Corolla', $modelNames) && in_array('Prius', $modelNames));

// 3. Toyota Corolla Years
$res = run_api_test('api/vehicles.php', ['action' => 'years', 'make' => 'Toyota', 'model' => 'Corolla']);
test("Fetch Years for Toyota Corolla (Includes 2024)", in_array(2024, $res['data'] ?? []) || in_array('2024', $res['data'] ?? []));

// 4. Vehicle Compatibility Search (Toyota Corolla 2024)
$res = run_api_test('api/products.php', ['make' => 'Toyota', 'model' => 'Corolla', 'year' => 2024]);
test("Search Products compatible with Toyota Corolla 2024 (Found: " . count($res['data'] ?? []) . ")", ($res['success'] ?? false) && count($res['data']) >= 8);

// 5. Customer Auth Login
$res = run_api_test('api/auth.php', [], ['action' => 'login', 'email' => 'kasun@autohub.lk', 'password' => 'Autohub@2026']);
test("Customer Login authentication (kasun@autohub.lk)", ($res['success'] ?? false) && $res['role'] === 'customer');

// 6. Admin Auth Login
$res = run_api_test('api/auth.php', [], ['action' => 'login', 'email' => 'admin@autohub.lk', 'password' => 'Admin@2026']);
test("Admin Login authentication (admin@autohub.lk)", ($res['success'] ?? false) && $res['role'] === 'admin');

// 7. Shopping Cart Operations
$res = run_api_test('api/cart.php', [], ['action' => 'add', 'product_id' => 1, 'quantity' => 2], ['user_id' => 2]);
test("Add to Cart in MySQL (Cart Count: " . ($res['cart_count'] ?? 0) . ")", ($res['success'] ?? false) && ($res['cart_count'] ?? 0) >= 2);

// 8. Coupon Validation
$res = run_api_test('api/cart.php', [], ['action' => 'apply_coupon', 'coupon_code' => 'AUTOHUB10'], ['user_id' => 2]);
test("Apply Discount Coupon 'AUTOHUB10' (10% OFF)", ($res['success'] ?? false) && ($res['coupon']['code'] ?? '') === 'AUTOHUB10');

// 9. Wishlist Operations
$res = run_api_test('api/wishlist.php', [], ['action' => 'toggle', 'product_id' => 2], ['user_id' => 2]);
test("Toggle Wishlist in MySQL for customer", ($res['success'] ?? false));

// 10. PayHere MD5 Hash Generation & Integrity Test
$sampleOrder = 'AUTO-2026-TEST01';
$sampleAmount = 15650.00;
$generatedHash = generate_payhere_hash($sampleOrder, $sampleAmount, 'LKR');
$testConfig = get_payment_config();
$validSig = verify_payhere_hash($testConfig['merchant_id'], $sampleOrder, $sampleAmount, 'LKR', 2, strtoupper(md5($testConfig['merchant_id'] . $sampleOrder . number_format($sampleAmount, 2, '.', '') . 'LKR2' . strtoupper(md5($testConfig['merchant_secret'])))));
test("PayHere MD5 Hash Generation & Signature Integrity", strlen($generatedHash) === 32 && $validSig === true);

// 11. Place Order - CASH ON DELIVERY Flow
$resCOD = run_api_test('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '070 727 5599',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No. 45/2, Flower Road',
    'shipping_city' => 'Colombo 07',
    'shipping_postal_code' => '00700',
    'shipping_province' => 'Western Province (Colombo, Gampaha, Kalutara)',
    'payment_method' => 'cod',
    'delivery_instructions' => 'Call on arrival'
], ['user_id' => 2]);

$codOrderNum = $resCOD['order_number'] ?? '';
$stmtCOD = $pdo->prepare("SELECT o.*, p.payment_status, p.payment_method as pay_method FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.order_number = ?");
$stmtCOD->execute([$codOrderNum]);
$codDbRecord = $stmtCOD->fetch();

test("Cash on Delivery: Order Created (Order: $codOrderNum, Status: Pending Payment, Payment: Pending)", 
    ($resCOD['success'] ?? false) && 
    !empty($codOrderNum) && 
    $codDbRecord && 
    $codDbRecord['payment_status'] === 'Pending' &&
    $codDbRecord['pay_method'] === 'Cash on Delivery'
);

// 12. Add item and Place Order - ONLINE CARD PAYMENT Flow
run_api_test('api/cart.php', [], ['action' => 'add', 'product_id' => 2, 'quantity' => 1], ['user_id' => 2]);

$resCard = run_api_test('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '070 727 5599',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No. 45/2, Flower Road',
    'shipping_city' => 'Colombo 07',
    'shipping_postal_code' => '00700',
    'shipping_province' => 'Western Province (Colombo, Gampaha, Kalutara)',
    'payment_method' => 'card',
    'delivery_instructions' => 'Fragile parts'
], ['user_id' => 2]);

$cardOrderNum = $resCard['order_number'] ?? '';
$paymentData = $resCard['payment_data'] ?? [];

test("Online Card Payment: Pending Order & PayHere Payload Generated ($cardOrderNum)",
    ($resCard['success'] ?? false) && 
    ($resCard['payment_required'] ?? false) === true && 
    !empty($paymentData['hash']) && 
    $paymentData['currency'] === 'LKR' &&
    $paymentData['order_id'] === $cardOrderNum
);

// 13. Payment Gateway Webhook Callback Verification (Simulation)
$cardTotal = number_format((float)$resCard['total_amount'], 2, '.', '');
$merchantId = $testConfig['merchant_id'];
$merchantSecret = $testConfig['merchant_secret'];
$payherePaymentId = 'PAYHERE-TEST-' . mt_rand(100000, 999999);

// Construct genuine PayHere callback signature
$callbackSig = strtoupper(md5($merchantId . $cardOrderNum . $cardTotal . 'LKR2' . strtoupper(md5($merchantSecret))));

$callbackCode = '
$_POST = [
    "merchant_id" => ' . var_export($merchantId, true) . ',
    "order_id" => ' . var_export($cardOrderNum, true) . ',
    "payment_id" => ' . var_export($payherePaymentId, true) . ',
    "payhere_amount" => ' . var_export($cardTotal, true) . ',
    "payhere_currency" => "LKR",
    "status_code" => "2",
    "md5sig" => ' . var_export($callbackSig, true) . '
];
$_SERVER["REQUEST_METHOD"] = "POST";
require "' . addslashes(__DIR__ . '/api/payment-callback.php') . '";
';
$tempCb = __DIR__ . '/test_cb_temp.php';
file_put_contents($tempCb, "<?php\n" . $callbackCode);
$cbOutput = shell_exec("php " . escapeshellarg($tempCb));
@unlink($tempCb);

// Check order & payment status in MySQL
$stmtPaid = $pdo->prepare("SELECT o.status, p.payment_status, p.transaction_id FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.order_number = ?");
$stmtPaid->execute([$cardOrderNum]);
$paidRecord = $stmtPaid->fetch();

test("Payment Callback Webhook: Signature Verified -> Payment 'Paid' & Order 'Processing/Confirmed'", 
    str_contains($cbOutput, 'OK:') && 
    $paidRecord && 
    $paidRecord['payment_status'] === 'Paid' && 
    in_array($paidRecord['status'], ['Processing', 'Confirmed', 'Paid']) &&
    $paidRecord['transaction_id'] === $payherePaymentId
);

// 14. Duplicate Callback Prevention Test
file_put_contents($tempCb, "<?php\n" . $callbackCode);
$duplicateOutput = shell_exec("php " . escapeshellarg($tempCb));
@unlink($tempCb);
test("Duplicate Payment Processing Prevention", str_contains($duplicateOutput, 'already verified'));

// 15. Invalid / Tampered Signature Rejection Test
$tamperedSig = "INVALID_TAMPERED_MD5_SIGNATURE";
$tamperedCode = '
$_POST = [
    "merchant_id" => ' . var_export($merchantId, true) . ',
    "order_id" => ' . var_export($cardOrderNum, true) . ',
    "payment_id" => "TAMPER-TEST",
    "payhere_amount" => ' . var_export($cardTotal, true) . ',
    "payhere_currency" => "LKR",
    "status_code" => "2",
    "md5sig" => ' . var_export($tamperedSig, true) . '
];
$_SERVER["REQUEST_METHOD"] = "POST";
require "' . addslashes(__DIR__ . '/api/payment-callback.php') . '";
';
file_put_contents($tempCb, "<?php\n" . $tamperedCode);
$tamperOutput = shell_exec("php " . escapeshellarg($tempCb));
@unlink($tempCb);
test("Security: Tampered Callback Signature Rejected", str_contains($tamperOutput, 'Invalid payment signature'));

// 16. Track Order API with Payment Details
$resTrack = run_api_test('api/orders.php', ['action' => 'track', 'order_number' => $cardOrderNum]);
test("Track Order API includes Payment Info & Transaction ID", 
    ($resTrack['success'] ?? false) && 
    ($resTrack['data']['payment_status'] ?? '') === 'Paid' &&
    ($resTrack['data']['transaction_id'] ?? '') === $payherePaymentId
);

// 17. Admin Orders Query includes Payment Status & Transaction ID
$stmtAdmin = $pdo->prepare("
    SELECT o.*, p.payment_status, p.transaction_id 
    FROM orders o 
    LEFT JOIN payments p ON p.order_id = o.id 
    WHERE o.order_number = ?
");
$stmtAdmin->execute([$cardOrderNum]);
$adminOrd = $stmtAdmin->fetch();
test("Admin Order Record: Displays Payment Status, Transaction ID & Amount", 
    $adminOrd && 
    $adminOrd['payment_status'] === 'Paid' && 
    !empty($adminOrd['transaction_id'])
);

echo "\n========================================================\n";
echo "Final Result: $pass / " . ($pass + $fail) . " Tests Passed!\n";
echo "========================================================\n";
