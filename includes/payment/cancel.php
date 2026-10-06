<?php
/**
 * AUTO HUB - Payment Gateway Cancellation Handler
 * Handles customer cancellation events from the payment gateway
 */

require_once __DIR__ . '/payment_service.php';

$orderNumber = trim($_GET['order_id'] ?? $_GET['order_number'] ?? '');

if (!empty($orderNumber)) {
    $pdo = get_db();
    // Update to Cancelled only if order was in Pending Payment state
    $stmt = $pdo->prepare("SELECT id, status, payment_status FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order && ($order['payment_status'] === 'Pending' || $order['status'] === 'Pending Payment')) {
        $pdo->prepare("UPDATE orders SET status = 'Cancelled', payment_status = 'Cancelled' WHERE id = ?")->execute([$order['id']]);
        $pdo->prepare("UPDATE payments SET payment_status = 'Cancelled', status_message = 'Customer cancelled at payment gateway' WHERE order_id = ? AND payment_status = 'Pending'")->execute([$order['id']]);
    }

    header('Location: ../../order-confirmation.php?payment_status=cancelled&order_id=' . urlencode($orderNumber));
    exit;
}

header('Location: ../../checkout.php');
exit;
