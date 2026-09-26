<?php
/**
 * AUTO HUB - Payment Gateway Callback / Webhook Endpoint (API Bridge)
 * Delegated to the central PaymentService architecture in includes/payment/
 */

require_once __DIR__ . '/../includes/payment/payment_service.php';

$service = new PaymentService();
$result = $service->processCallback($_POST);

http_response_code($result['http_code'] ?? 200);

if (!empty($result['success'])) {
    echo "OK: " . ($result['message'] ?? 'Payment verified successfully.');
} else {
    echo "ERROR: " . ($result['message'] ?? 'Payment verification failed.');
}
