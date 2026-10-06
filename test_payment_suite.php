<?php
/**
 * AUTO HUB - Comprehensive PayHere Online Card Payment & Checkout Test Suite
 * Validates all 11 core testing criteria required by prompt
 */

require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/payment.php';

$pdo = get_db();
$passed = 0;
$failed = 0;

function assert_test($description, $condition, $details = '') {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $description\n";
        $passed++;
    } else {
        echo "[FAIL] $description" . ($details ? " -> $details" : "") . "\n";
        $failed++;
    }
}

function run_script_api($script, $query = [], $post = null, $session = []) {
    $sessionCode = '';
    if (!empty($session)) {
        foreach ($session as $k => $v) {
            $sessionCode .= '$_SESSION[' . var_export($k, true) . '] = ' . var_export($v, true) . ";\n";
        }
    }

    $code = '<?php
    $_GET = ' . var_export($query, true) . ';
    $_POST = ' . var_export($post ?: [], true) . ';
    $_SERVER["REQUEST_METHOD"] = ' . var_export($post !== null ? 'POST' : 'GET', true) . ';
    $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
    session_start();
    ' . $sessionCode . '
    require "' . addslashes(__DIR__ . '/' . $script) . '";
    ';

    $tempFile = __DIR__ . '/scratch/test_api_runner.php';
    if (!is_dir(__DIR__ . '/scratch')) {
        mkdir(__DIR__ . '/scratch', 0777, true);
    }
    file_put_contents($tempFile, $code);
    $output = shell_exec("php " . escapeshellarg($tempFile));
    @unlink($tempFile);
    return $output;
}

echo "======================================================================\n";
echo "   AUTO HUB - PayHere Online Card Payment & Security Test Suite\n";
echo "======================================================================\n\n";

$paymentConfig = get_payment_config();
$merchantId = $paymentConfig['merchant_id'];
$merchantSecret = $paymentConfig['merchant_secret'];

// Ensure test product has sufficient stock
$pdo->exec("UPDATE products SET stock_quantity = 50 WHERE id = 1");

// Helper to set up a cart with 1 item
function setup_cart_for_user($userId = null, $sessionId = 'test_session_default') {
    $pdo = get_db();
    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();
        if (!$cart) {
            $pdo->prepare("INSERT INTO cart (user_id, session_id) VALUES (?, ?)")->execute([$userId, $sessionId]);
            $cartId = (int)$pdo->lastInsertId();
        } else {
            $cartId = (int)$cart['id'];
        }
    } else {
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE session_id = ? AND user_id IS NULL LIMIT 1");
        $stmt->execute([$sessionId]);
        $cart = $stmt->fetch();
        if (!$cart) {
            $pdo->prepare("INSERT INTO cart (user_id, session_id) VALUES (NULL, ?)")->execute([$sessionId]);
            $cartId = (int)$pdo->lastInsertId();
        } else {
            $cartId = (int)$cart['id'];
        }
    }
    $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, 1, 1)")->execute([$cartId]);
    return $cartId;
}

// -----------------------------------------------------------------------------
// Test 1: Hash Calculation Formula Verification
// -----------------------------------------------------------------------------
$testOrderId = "AUTO-2026-99999";
$testAmount = 14500.00;
$testCurrency = "LKR";
$expectedHash = strtoupper(md5($merchantId . $testOrderId . number_format($testAmount, 2, '.', '') . $testCurrency . strtoupper(md5($merchantSecret))));
$calculatedHash = generate_payhere_hash($testOrderId, $testAmount, $testCurrency);
assert_test("1. PayHere Hash Generation matches official formula", $calculatedHash === $expectedHash);

// -----------------------------------------------------------------------------
// Test A: Cash on Delivery (COD) Flow
// -----------------------------------------------------------------------------
setup_cart_for_user(2);
$rawRes = run_script_api('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '0707275599',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No 100, Galle Road',
    'shipping_city' => 'Colombo 03',
    'shipping_province' => 'Western Province',
    'payment_method' => 'cod'
], ['user_id' => 2]);
$codRes = json_decode($rawRes, true);

assert_test("A. COD Order Creation places order without PayHere payload", 
    ($codRes['success'] ?? false) && 
    ($codRes['payment_method'] ?? '') === 'Cash on Delivery' && 
    ($codRes['payment_required'] ?? true) === false &&
    str_contains($codRes['redirect'] ?? '', 'order-confirmation.php')
);

// -----------------------------------------------------------------------------
// Test B: Online Card Payment - Order Creation & Successful IPN Callback Flow
// -----------------------------------------------------------------------------
$initialStock = (int)$pdo->query("SELECT stock_quantity FROM products WHERE id = 1")->fetchColumn();
setup_cart_for_user(2);
$rawRes = run_script_api('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '0707275599',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No 100, Galle Road',
    'shipping_city' => 'Colombo 03',
    'shipping_province' => 'Western Province',
    'payment_method' => 'card'
], ['user_id' => 2]);
$cardRes = json_decode($rawRes, true);

$cardOrderNumber = $cardRes['order_number'] ?? '';
$cardTotal = $cardRes['total_amount'] ?? 0;
$cardPaymentData = $cardRes['payment_data'] ?? [];

$stockAfterCreation = (int)$pdo->query("SELECT stock_quantity FROM products WHERE id = 1")->fetchColumn();

assert_test("B1. Online Card Order creation keeps stock intact before payment & generates valid PayHere payload", 
    ($cardRes['success'] ?? false) &&
    ($cardRes['payment_required'] ?? false) === true &&
    $stockAfterCreation === $initialStock &&
    !empty($cardPaymentData['hash']) &&
    $cardPaymentData['merchant_id'] === $merchantId &&
    $cardPaymentData['order_id'] === $cardOrderNumber
);

// Simulate PayHere IPN Success (status_code = 2)
$formattedCardTotal = number_format((float)$cardTotal, 2, '.', '');
$successSig = strtoupper(md5($merchantId . $cardOrderNumber . $formattedCardTotal . 'LKR' . '2' . strtoupper(md5($merchantSecret))));
$paymentId = "PAYHERE_TX_" . mt_rand(100000, 999999);

$cbOutput = run_script_api('api/payment-callback.php', [], [
    'merchant_id'      => $merchantId,
    'order_id'         => $cardOrderNumber,
    'payment_id'       => $paymentId,
    'payhere_amount'   => $formattedCardTotal,
    'payhere_currency' => 'LKR',
    'status_code'      => '2',
    'md5sig'           => $successSig,
    'method'           => 'VISA',
    'status_message'   => 'Authorized & Captured',
    'card_no'          => '4111********1111'
]);

// Check database updates & stock reduction
$stmt = $pdo->prepare("
    SELECT o.status AS order_status, p.payment_status, p.transaction_id, p.card_type, p.paid_at 
    FROM orders o 
    JOIN payments p ON p.order_id = o.id 
    WHERE o.order_number = ?
");
$stmt->execute([$cardOrderNumber]);
$dbRecord = $stmt->fetch();
$stockAfterPayment = (int)$pdo->query("SELECT stock_quantity FROM products WHERE id = 1")->fetchColumn();

assert_test("B2. PayHere IPN Webhook verifies checksum, marks payment 'Paid' and decrements product stock by 1", 
    str_contains($cbOutput, 'OK') &&
    $dbRecord['payment_status'] === 'Paid' &&
    $dbRecord['transaction_id'] === $paymentId &&
    !empty($dbRecord['paid_at']) &&
    $stockAfterPayment === ($initialStock - 1) &&
    in_array($dbRecord['order_status'], ['Processing', 'Confirmed'])
);

// -----------------------------------------------------------------------------
// Test C: Online Card Payment - Failed Payment Notification (status_code = -2)
// -----------------------------------------------------------------------------
setup_cart_for_user(2);
$rawRes = run_script_api('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '0707275599',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No 100, Galle Road',
    'shipping_city' => 'Colombo 03',
    'shipping_province' => 'Western Province',
    'payment_method' => 'card'
], ['user_id' => 2]);
$failOrderRes = json_decode($rawRes, true);
$failOrderNum = $failOrderRes['order_number'] ?? '';
$failTotalFormatted = number_format((float)$failOrderRes['total_amount'], 2, '.', '');

$failSig = strtoupper(md5($merchantId . $failOrderNum . $failTotalFormatted . 'LKR' . '-2' . strtoupper(md5($merchantSecret))));
$failTxId = "PAYHERE_FAIL_" . mt_rand(100000, 999999);

$cbOutput = run_script_api('api/payment-callback.php', [], [
    'merchant_id'      => $merchantId,
    'order_id'         => $failOrderNum,
    'payment_id'       => $failTxId,
    'payhere_amount'   => $failTotalFormatted,
    'payhere_currency' => 'LKR',
    'status_code'      => '-2',
    'md5sig'           => $failSig,
    'method'           => 'VISA',
    'status_message'   => 'Insufficient Funds'
]);

$stmt = $pdo->prepare("SELECT o.status AS order_status, p.payment_status FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.order_number = ?");
$stmt->execute([$failOrderNum]);
$dbFail = $stmt->fetch();

assert_test("C. Failed payment notification updates status to 'Payment Failed' and payment to 'Failed'",
    $dbFail['payment_status'] === 'Failed' &&
    $dbFail['order_status'] === 'Payment Failed'
);

// -----------------------------------------------------------------------------
// Test D: Online Card Payment - Cancelled Notification (status_code = -1)
// -----------------------------------------------------------------------------
setup_cart_for_user(2);
$rawRes = run_script_api('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '0707275599',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No 100, Galle Road',
    'shipping_city' => 'Colombo 03',
    'shipping_province' => 'Western Province',
    'payment_method' => 'card'
], ['user_id' => 2]);
$cancelOrderRes = json_decode($rawRes, true);
$cancelOrderNum = $cancelOrderRes['order_number'] ?? '';
$cancelTotalFormatted = number_format((float)$cancelOrderRes['total_amount'], 2, '.', '');

$cancelSig = strtoupper(md5($merchantId . $cancelOrderNum . $cancelTotalFormatted . 'LKR' . '-1' . strtoupper(md5($merchantSecret))));
$cancelTxId = "PAYHERE_CANCEL_" . mt_rand(100000, 999999);

// Test via dedicated endpoint: payment/notify.php
$cbOutput = run_script_api('payment/notify.php', [], [
    'merchant_id'      => $merchantId,
    'order_id'         => $cancelOrderNum,
    'payment_id'       => $cancelTxId,
    'payhere_amount'   => $cancelTotalFormatted,
    'payhere_currency' => 'LKR',
    'status_code'      => '-1',
    'md5sig'           => $cancelSig,
    'method'           => 'MASTER',
    'status_message'   => 'User Cancelled on Gateway'
]);

$stmt = $pdo->prepare("SELECT o.status AS order_status, p.payment_status FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.order_number = ?");
$stmt->execute([$cancelOrderNum]);
$dbCancel = $stmt->fetch();

assert_test("D. Cancelled payment via payment/notify.php updates status to 'Cancelled'",
    str_contains($cbOutput, 'OK') &&
    $dbCancel['payment_status'] === 'Cancelled' &&
    $dbCancel['order_status'] === 'Cancelled'
);

// -----------------------------------------------------------------------------
// Test D2: Online Card Payment - Chargedback Notification (status_code = -3)
// -----------------------------------------------------------------------------
setup_cart_for_user(2);
$rawRes = run_script_api('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '0707275599',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No 100, Galle Road',
    'shipping_city' => 'Colombo 03',
    'shipping_province' => 'Western Province',
    'payment_method' => 'card'
], ['user_id' => 2]);
$chargebackOrderRes = json_decode($rawRes, true);
$chargebackOrderNum = $chargebackOrderRes['order_number'] ?? '';
$chargebackTotalFormatted = number_format((float)$chargebackOrderRes['total_amount'], 2, '.', '');

$cbSig = strtoupper(md5($merchantId . $chargebackOrderNum . $chargebackTotalFormatted . 'LKR' . '-3' . strtoupper(md5($merchantSecret))));
$cbTxId = "PAYHERE_CB_" . mt_rand(100000, 999999);

$cbOutput = run_script_api('payment/notify.php', [], [
    'merchant_id'      => $merchantId,
    'order_id'         => $chargebackOrderNum,
    'payment_id'       => $cbTxId,
    'payhere_amount'   => $chargebackTotalFormatted,
    'payhere_currency' => 'LKR',
    'status_code'      => '-3',
    'md5sig'           => $cbSig,
    'method'           => 'VISA',
    'status_message'   => 'Chargeback Dispute Filed'
]);

$stmt = $pdo->prepare("SELECT o.status AS order_status, p.payment_status FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.order_number = ?");
$stmt->execute([$chargebackOrderNum]);
$dbCb = $stmt->fetch();

assert_test("D2. Chargedback payment (status_code = -3) updates payment to 'Chargedback'",
    str_contains($cbOutput, 'OK') &&
    $dbCb['payment_status'] === 'Chargedback' &&
    $dbCb['order_status'] === 'Cancelled'
);

// -----------------------------------------------------------------------------
// Test E: Payment Pending Initial State
// -----------------------------------------------------------------------------
setup_cart_for_user(2);
$rawRes = run_script_api('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '0707275599',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No 100, Galle Road',
    'shipping_city' => 'Colombo 03',
    'shipping_province' => 'Western Province',
    'payment_method' => 'card'
], ['user_id' => 2]);
$pendingOrderRes = json_decode($rawRes, true);
$pendingOrderNum = $pendingOrderRes['order_number'] ?? '';

$stmt = $pdo->prepare("SELECT o.status AS order_status, p.payment_status FROM orders o JOIN payments p ON p.order_id = o.id WHERE o.order_number = ?");
$stmt->execute([$pendingOrderNum]);
$dbPending = $stmt->fetch();

assert_test("E. Newly created card order remains 'Pending Payment' prior to notification",
    $dbPending['payment_status'] === 'Pending' &&
    $dbPending['order_status'] === 'Pending Payment'
);

// -----------------------------------------------------------------------------
// Test F: Invalid Payment Notification (Missing Parameters)
// -----------------------------------------------------------------------------
$cbMissing = run_script_api('api/payment-callback.php', [], [
    'merchant_id' => $merchantId,
    'order_id'    => $pendingOrderNum
    // missing amount, status_code, md5sig
]);
assert_test("F. Callback rejects incomplete parameters with error", str_contains($cbMissing, 'ERROR:') && str_contains($cbMissing, 'Missing'));

// -----------------------------------------------------------------------------
// Test G: Invalid Checksum Signature Rejection (Tamper Detection)
// -----------------------------------------------------------------------------
$cbTampered = run_script_api('api/payment-callback.php', [], [
    'merchant_id'      => $merchantId,
    'order_id'         => $pendingOrderNum,
    'payment_id'       => 'TAMPER_TX_123',
    'payhere_amount'   => '100.00',
    'payhere_currency' => 'LKR',
    'status_code'      => '2',
    'md5sig'           => 'INVALID_FAKE_MD5_SIGNATURE_99999'
]);
assert_test("G. Callback rejects tampered/invalid signature checksum with error", str_contains($cbTampered, 'ERROR: Invalid payment signature'));

// -----------------------------------------------------------------------------
// Test H: Duplicate Notification Idempotency
// -----------------------------------------------------------------------------
// Re-send success IPN for the already paid order from Test B
$cbDuplicate = run_script_api('api/payment-callback.php', [], [
    'merchant_id'      => $merchantId,
    'order_id'         => $cardOrderNumber,
    'payment_id'       => $paymentId,
    'payhere_amount'   => $formattedCardTotal,
    'payhere_currency' => 'LKR',
    'status_code'      => '2',
    'md5sig'           => $successSig,
    'method'           => 'VISA'
]);
assert_test("H. Duplicate notification is handled idempotently without error", str_contains($cbDuplicate, 'OK: Payment already verified'));

// -----------------------------------------------------------------------------
// Test I: Refreshing Return Page Reads Verified DB State
// -----------------------------------------------------------------------------
$rawVerify = run_script_api('api/payment-verify.php', ['order_id' => $cardOrderNumber]);
$verifyData = json_decode($rawVerify, true);
assert_test("I. Verification endpoint reads verified 'Paid' status from DB on page refresh",
    ($verifyData['success'] ?? false) &&
    ($verifyData['payment_status'] ?? '') === 'Paid' &&
    $verifyData['transaction_id'] === $paymentId
);

// -----------------------------------------------------------------------------
// Test J: Order Total Mismatch Detection
// -----------------------------------------------------------------------------
// Create another order of Rs. 14,500, but try sending a callback claiming Rs. 500
$mismatchSig = strtoupper(md5($merchantId . $pendingOrderNum . '500.00' . 'LKR' . '2' . strtoupper(md5($merchantSecret))));
$cbMismatch = run_script_api('api/payment-callback.php', [], [
    'merchant_id'      => $merchantId,
    'order_id'         => $pendingOrderNum,
    'payment_id'       => 'UNDERPAY_TX',
    'payhere_amount'   => '500.00',
    'payhere_currency' => 'LKR',
    'status_code'      => '2',
    'md5sig'           => $mismatchSig,
    'method'           => 'VISA'
]);
assert_test("J. Callback detects and rejects order total amount mismatch", str_contains($cbMismatch, 'ERROR: Order total mismatch'));

// -----------------------------------------------------------------------------
// Test K: Guest (Unauthenticated) Checkout Allowed for Guest Cart
// -----------------------------------------------------------------------------
// Create guest cart
$guestCartId = setup_cart_for_user(null, 'guest_session_token_123');
$rawGuest = run_script_api('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Guest Driver',
    'shipping_phone' => '0712345678',
    'shipping_email' => 'guest@autohub.lk',
    'shipping_address' => 'No 5, Kandy Road',
    'shipping_city' => 'Kandy',
    'shipping_province' => 'Central Province',
    'payment_method' => 'card'
], ['guest_cart_id' => 'guest_session_token_123']);
$guestRes = json_decode($rawGuest, true);
assert_test("K. Guest checkout without login creates order and generates PayHere payload",
    ($guestRes['success'] ?? false) &&
    !empty($guestRes['order_number']) &&
    ($guestRes['payment_required'] ?? false) === true
);

// -----------------------------------------------------------------------------
// Security Checks: No Card Details Stored in Database
// -----------------------------------------------------------------------------
$cols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
$forbiddenCols = ['card_number', 'card_num', 'cvv', 'cvc', 'pin', 'expiry_year', 'expiry_month'];
$foundForbidden = array_intersect($forbiddenCols, $cols);
assert_test("SECURITY: Zero sensitive card fields (card_number, cvv, pin) exist in MySQL schema", empty($foundForbidden));

echo "\n======================================================================\n";
echo "Payment Test Results: $passed Passed, $failed Failed\n";
echo "======================================================================\n";

if ($failed === 0) {
    echo ">>> ALL PAYHERE PAYMENT & SECURITY TESTS PASSED PERFECTLY! <<<\n";
}
