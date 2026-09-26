<?php
/**
 * AUTO HUB - Payment Gateway Configuration (PayHere Sri Lanka)
 * Official PayHere Checkout API v1 Integration
 *
 * =============================================================================
 * IMPORTANT SECURITY RULES:
 * 1. Never expose PAYHERE_MERCHANT_SECRET in client-side HTML or JavaScript.
 * 2. Never store raw card numbers, CVVs, or PINs in the database.
 * 3. Calculate all payment hashes and verify all notification checksums on the server.
 * =============================================================================
 */

// Base URL definition
if (!defined('BASE_URL')) {
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        define('BASE_URL', 'http://localhost/EBusiness_Project');
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $cleanDir = preg_replace('/(\/api|\/admin|\/config|\/libs|\/scratch)$/', '', $scriptDir);
        if ($cleanDir === '/' || $cleanDir === '.') $cleanDir = '';
        $base = rtrim($protocol . $host . $cleanDir, '/');
        define('BASE_URL', $base ?: 'http://localhost/EBusiness_Project');
    }
}

/**
 * -----------------------------------------------------------------------------
 * PAYHERE MERCHANT SETTINGS
 * -----------------------------------------------------------------------------
 * Switch between 'sandbox' and 'live' without changing application code.
 *
 * Sandbox Merchant ID & Secret:
 * - Obtain from https://sandbox.payhere.lk (Merchant Portal -> Settings -> Domains & Credentials)
 * - PayHere official sandbox test merchant ID: '1211149' or your own Sandbox Merchant ID
 *
 * Production Merchant ID & Secret:
 * - Obtain from https://www.payhere.lk
 */

// Environment Mode: 'sandbox' or 'live'
if (!defined('PAYHERE_MODE')) {
    $envMode = getenv('PAYHERE_MODE') ?: (getenv('PAYHERE_SANDBOX') === 'false' ? 'live' : 'sandbox');
    define('PAYHERE_MODE', strtolower($envMode) === 'live' ? 'live' : 'sandbox');
}

// Merchant ID & Merchant Secret
if (!defined('PAYHERE_MERCHANT_ID')) {
    define('PAYHERE_MERCHANT_ID', getenv('PAYHERE_MERCHANT_ID') ?: '1211149');
}

if (!defined('PAYHERE_MERCHANT_SECRET')) {
    // Sandbox default or your PayHere Merchant Secret from sandbox.payhere.lk
    define('PAYHERE_MERCHANT_SECRET', getenv('PAYHERE_MERCHANT_SECRET') ?: '4TXi14jUf1G4VzC1l5e4P521r5X0Y93k23v5K87p1q4=');
}

// Currency (LKR)
if (!defined('PAYHERE_CURRENCY')) {
    define('PAYHERE_CURRENCY', getenv('PAYHERE_CURRENCY') ?: 'LKR');
}

/**
 * Retrieve active payment configuration
 *
 * @return array Payment settings array
 */
function get_payment_config(): array {
    $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/EBusiness_Project';
    $isSandbox = (PAYHERE_MODE === 'sandbox');

    // PayHere Checkout Endpoints
    $gatewayUrl = $isSandbox 
        ? 'https://sandbox.payhere.lk/pay/checkout' 
        : 'https://www.payhere.lk/pay/checkout';

    $notifyUrl = getenv('PAYHERE_NOTIFY_URL') ?: ($baseUrl . '/api/payment-callback.php');
    $returnUrl = getenv('PAYHERE_RETURN_URL') ?: ($baseUrl . '/order-confirmation.php');
    $cancelUrl = getenv('PAYHERE_CANCEL_URL') ?: ($baseUrl . '/order-confirmation.php?payment_status=cancelled');

    return [
        'mode'            => PAYHERE_MODE,
        'sandbox'         => $isSandbox,
        'merchant_id'     => (string)PAYHERE_MERCHANT_ID,
        'merchant_secret' => (string)PAYHERE_MERCHANT_SECRET,
        'gateway_url'     => $gatewayUrl,
        'js_sdk_url'      => 'https://www.payhere.lk/lib/payhere.js',
        'currency'        => PAYHERE_CURRENCY,
        'store_name'      => 'AUTO HUB – Spare Parts & Accessories',
        'return_url'      => $returnUrl,
        'cancel_url'      => $cancelUrl,
        'notify_url'      => $notifyUrl,
    ];
}

/**
 * Generate official PayHere MD5 security hash for payment request
 * Formula: UPPERCASE(MD5(merchant_id + order_id + amount_formatted + currency + UPPERCASE(MD5(merchant_secret))))
 *
 * @param string|int $orderId Unique order identifier (e.g. AUTO-2026-00001)
 * @param float|int|string $amount Total order amount (e.g. 17350.00)
 * @param string $currency Currency code (e.g. LKR)
 * @return string 32-character hexadecimal MD5 hash in uppercase
 */
function generate_payhere_hash(string|int $orderId, float|int|string $amount, string $currency = 'LKR'): string {
    $config = get_payment_config();
    $merchantId = $config['merchant_id'];
    $merchantSecret = $config['merchant_secret'];

    $formattedAmount = number_format((float)$amount, 2, '.', '');
    $hashedSecret = strtoupper(md5($merchantSecret));
    
    return strtoupper(md5($merchantId . (string)$orderId . $formattedAmount . $currency . $hashedSecret));
}

/**
 * Verify incoming server-to-server payment notification (IPN) signature
 * Formula: UPPERCASE(MD5(merchant_id + order_id + payhere_amount + payhere_currency + status_code + UPPERCASE(MD5(merchant_secret))))
 *
 * @param string $merchantId Received merchant ID
 * @param string $orderId Received order ID / order number
 * @param string|float $amount Received payment amount (e.g. "17350.00")
 * @param string $currency Received currency (e.g. "LKR")
 * @param string|int $statusCode Received payment status code (2 = Success, 0 = Pending, -1 = Cancelled, -2 = Failed, -3 = Chargedback)
 * @param string $receivedSignature Received md5sig checksum from PayHere
 * @return bool True if signature matches exactly
 */
function verify_payhere_hash(string $merchantId, string $orderId, string|float $amount, string $currency, string|int $statusCode, string $receivedSignature): bool {
    $config = get_payment_config();

    // Verify merchant ID matches configured merchant ID
    if ((string)$merchantId !== (string)$config['merchant_id']) {
        return false;
    }

    $merchantSecret = $config['merchant_secret'];
    $formattedAmount = number_format((float)$amount, 2, '.', '');
    $hashedSecret = strtoupper(md5($merchantSecret));

    $expectedSignature = strtoupper(md5($merchantId . (string)$orderId . $formattedAmount . $currency . (string)$statusCode . $hashedSecret));

    return hash_equals($expectedSignature, strtoupper(trim($receivedSignature)));
}

/**
 * Build complete PayHere Checkout form payload
 *
 * @param array $order Order database record
 * @param array $customer Customer/shipping details
 * @return array Sanitized and signed PayHere request fields
 */
function build_payhere_checkout_payload(array $order, array $customer): array {
    $config = get_payment_config();
    $orderNumber = $order['order_number'] ?? (string)$order['id'];
    $amount = number_format((float)$order['total_amount'], 2, '.', '');
    $currency = $config['currency'];

    $hash = generate_payhere_hash($orderNumber, $amount, $currency);

    // Split name into first and last name
    $fullName = trim($customer['shipping_name'] ?? $customer['full_name'] ?? 'Valued Customer');
    $nameParts = explode(' ', $fullName, 2);
    $firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Valued';
    $lastName  = !empty($nameParts[1]) ? $nameParts[1] : 'Customer';

    $email = trim($customer['shipping_email'] ?? $customer['email'] ?? 'customer@autohub.lk');
    $phone = trim($customer['shipping_phone'] ?? $customer['phone'] ?? '0707275599');
    $address = trim($customer['shipping_address'] ?? $customer['address'] ?? 'AutoHub Delivery Address');
    $city = trim($customer['shipping_city'] ?? $customer['city'] ?? 'Colombo');
    $country = 'Sri Lanka';

    $returnUrlWithOrder = $config['return_url'] . (str_contains($config['return_url'], '?') ? '&' : '?') . 'order_id=' . urlencode($orderNumber);
    $cancelUrlWithOrder = $config['cancel_url'] . (str_contains($config['cancel_url'], '?') ? '&' : '?') . 'order_id=' . urlencode($orderNumber);

    return [
        'merchant_id'      => $config['merchant_id'],
        'return_url'       => $returnUrlWithOrder,
        'cancel_url'       => $cancelUrlWithOrder,
        'notify_url'       => $config['notify_url'],
        'order_id'         => $orderNumber,
        'items'            => 'AutoHub Order #' . $orderNumber,
        'currency'         => $currency,
        'amount'           => $amount,
        'first_name'       => $firstName,
        'last_name'        => $lastName,
        'email'            => $email,
        'phone'            => $phone,
        'address'          => $address,
        'city'             => $city,
        'country'          => $country,
        'hash'             => $hash,
        'delivery_address' => $address,
        'delivery_city'    => $city,
        'delivery_country' => $country,
    ];
}

/**
 * Diagnostic helper to safely inspect PayHere settings without exposing secrets
 *
 * @return array Diagnostic information
 */
function get_payhere_diagnostics(): array {
    $config = get_payment_config();
    $isLocalhost = str_contains($config['return_url'], 'localhost') || str_contains($config['return_url'], '127.0.0.1');

    $warnings = [];
    if ($isLocalhost && str_contains($config['notify_url'], 'localhost')) {
        $warnings[] = "Localhost notification URL: Real external PayHere server webhooks cannot reach http://localhost without a public tunnel (e.g. ngrok). For live webhook callbacks, configure PAYHERE_NOTIFY_URL with a public URL.";
    }

    return [
        'mode'                 => strtoupper($config['mode']),
        'sandbox'              => $config['sandbox'],
        'merchant_id'          => $config['merchant_id'],
        'merchant_secret_set'  => !empty($config['merchant_secret']),
        'merchant_secret_mask' => substr($config['merchant_secret'], 0, 4) . '...' . substr($config['merchant_secret'], -4),
        'currency'             => $config['currency'],
        'gateway_url'          => $config['gateway_url'],
        'return_url'           => $config['return_url'],
        'cancel_url'           => $config['cancel_url'],
        'notify_url'           => $config['notify_url'],
        'is_localhost'         => $isLocalhost,
        'warnings'             => $warnings,
    ];
}
