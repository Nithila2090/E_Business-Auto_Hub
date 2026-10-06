<?php
/**
 * AUTO HUB - Dedicated Payment Gateway Webhook / Callback Handler
 * Receives and processes server-to-server notifications from configured payment gateways
 */

require_once __DIR__ . '/payment_service.php';

$service = new PaymentService();
$result = $service->processCallback($_POST);

http_response_code($result['http_code'] ?? 200);

if (!empty($result['success'])) {
    echo "OK: " . ($result['message'] ?? 'Payment verified successfully.');
} else {
    echo "ERROR: " . ($result['message'] ?? 'Payment verification failed.');
}
