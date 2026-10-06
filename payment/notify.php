<?php
/**
 * AUTO HUB - PayHere IPN (Instant Payment Notification) Webhook Endpoint
 * Official endpoint: payment/notify.php
 *
 * Security:
 * - Constant-time signature verification using hash_equals().
 * - Server-side order total validation.
 * - Idempotent processing to prevent duplicate updates / stock decrement.
 * - Never prints merchant secret or PHP stack traces.
 */

// Include system configurations and payment service
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/payhere.php';
require_once __DIR__ . '/../config/payment.php';
require_once __DIR__ . '/../includes/payment/payment_service.php';

// Accept POST payload from PayHere IPN server
$postData = $_POST;

// Also support raw json or php://input if posted as application/json
if (empty($postData)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $postData = $json;
        } else {
            parse_str($rawInput, $postData);
        }
    }
}

// Instantiate PaymentService and process notification
$paymentService = new PaymentService();
$result = $paymentService->processCallback($postData);

log_payhere_event('PAYHERE_NOTIFY_CALLBACK', [
    'order_id'       => $postData['order_id'] ?? 'unknown',
    'payment_id'     => $postData['payment_id'] ?? null,
    'amount'         => $postData['payhere_amount'] ?? null,
    'status_code'    => $postData['status_code'] ?? null,
    'method'         => $postData['method'] ?? null,
    'result_success' => $result['success'] ?? false,
    'result_message' => $result['message'] ?? '',
    'http_code'      => $result['http_code'] ?? 200
]);

// Set appropriate HTTP response header
http_response_code($result['http_code'] ?? 200);
header('Content-Type: text/plain; charset=utf-8');

if (!empty($result['success'])) {
    echo "OK: " . ($result['message'] ?? 'Payment verified successfully.');
} else {
    echo "ERROR: " . ($result['message'] ?? 'Payment verification failed.');
}
