<?php
/**
 * AUTO HUB - Payment Gateway Return / Success Handler
 *
 * Security:
 * - Does NOT mark orders as paid based on client GET parameters.
 * - Redirects to Order Confirmation page where the database-verified payment status is displayed.
 */

require_once __DIR__ . '/payment_service.php';

$orderNumber = trim($_GET['order_id'] ?? $_GET['order_number'] ?? '');

if (empty($orderNumber)) {
    header('Location: ../../checkout.php');
    exit;
}

$service = new PaymentService();
$order = $service->getOrderStatus($orderNumber);

if (!$order) {
    header('Location: ../../order-confirmation.php?error=not_found');
    exit;
}

// Redirect to order confirmation with verified order
header('Location: ../../order-confirmation.php?order_id=' . urlencode($order['order_number']));
exit;
