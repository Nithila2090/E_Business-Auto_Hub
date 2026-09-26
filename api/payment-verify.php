<?php
/**
 * AUTO HUB - Payment Status Verification & Sandbox Simulation Endpoint
 * Queries live payment status for an order and facilitates automated Sandbox test cases
 */

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/payment.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = get_db();
$orderNumber = trim($_GET['order_id'] ?? $_GET['order_number'] ?? $_POST['order_id'] ?? $_POST['order_number'] ?? '');
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'check');

if (empty($orderNumber)) {
    json_response(['success' => false, 'message' => 'Order identifier is required.'], 400);
}

try {
    // 1. Fetch order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? OR id = ? LIMIT 1");
    $stmt->execute([$orderNumber, is_numeric($orderNumber) ? (int)$orderNumber : 0]);
    $order = $stmt->fetch();

    if (!$order) {
        json_response(['success' => false, 'message' => "Order #{$orderNumber} was not found."], 404);
    }

    // 2. Fetch latest payment record
    $stmtPayment = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $stmtPayment->execute([$order['id']]);
    $payment = $stmtPayment->fetch();

    $paymentConfig = get_payment_config();

    // 3. Sandbox Simulator actions (Only permitted in sandbox mode for safe local test automation)
    if (!empty($paymentConfig['sandbox'])) {
        if ($action === 'simulate_success') {
            $txId = 'SANDBOX-' . strtoupper(substr(md5($order['id'] . time()), 0, 10));
            $pdo->beginTransaction();

            if ($payment) {
                $stmtUpd = $pdo->prepare("
                    UPDATE payments 
                    SET payment_status = 'Paid', 
                        transaction_id = ?, 
                        payment_gateway = 'PayHere', 
                        card_type = 'VISA (Sandbox)', 
                        status_message = 'Simulated sandbox payment success',
                        paid_at = NOW() 
                    WHERE id = ?
                ");
                $stmtUpd->execute([$txId, $payment['id']]);
            } else {
                $stmtIns = $pdo->prepare("
                    INSERT INTO payments (
                        order_id, payment_method, payment_gateway, amount, currency,
                        transaction_id, card_type, payment_status, status_message, paid_at, created_at
                    ) VALUES (?, 'Online Card Payment', 'PayHere', ?, 'LKR', ?, 'VISA (Sandbox)', 'Paid', 'Simulated sandbox payment success', NOW(), NOW())
                ");
                $stmtIns->execute([$order['id'], $order['total_amount'], $txId]);
            }

            $pdo->prepare("
                UPDATE orders 
                SET status = 'Processing', 
                    payment_status = 'Paid', 
                    transaction_id = ?, 
                    payment_reference = ?, 
                    paid_at = NOW() 
                WHERE id = ?
            ")->execute([$txId, $txId, $order['id']]);

            // Decrement stock if moving to Paid
            $stmtItems = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$order['id']]);
            $orderItems = $stmtItems->fetchAll();
            $stmtStock = $pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
            foreach ($orderItems as $it) {
                if (!empty($it['product_id']) && !empty($it['quantity'])) {
                    $stmtStock->execute([(int)$it['quantity'], (int)$it['product_id']]);
                }
            }

            $pdo->commit();

            // Refresh
            $stmt->execute([$orderNumber, is_numeric($orderNumber) ? (int)$orderNumber : 0]);
            $order = $stmt->fetch();
            $stmtPayment->execute([$order['id']]);
            $payment = $stmtPayment->fetch();

        } elseif ($action === 'simulate_fail') {
            $txId = 'FAIL-' . strtoupper(substr(md5($order['id'] . time()), 0, 8));
            $pdo->beginTransaction();

            if ($payment) {
                $pdo->prepare("
                    UPDATE payments 
                    SET payment_status = 'Failed', 
                        transaction_id = ?, 
                        status_message = 'Simulated sandbox card decline' 
                    WHERE id = ?
                ")->execute([$txId, $payment['id']]);
            }

            $pdo->prepare("UPDATE orders SET status = 'Payment Failed' WHERE id = ?")->execute([$order['id']]);
            $pdo->commit();

            // Refresh
            $stmt->execute([$orderNumber, is_numeric($orderNumber) ? (int)$orderNumber : 0]);
            $order = $stmt->fetch();
            $stmtPayment->execute([$order['id']]);
            $payment = $stmtPayment->fetch();

        } elseif ($action === 'simulate_cancel') {
            $pdo->beginTransaction();

            if ($payment) {
                $pdo->prepare("
                    UPDATE payments 
                    SET payment_status = 'Cancelled', 
                        status_message = 'Simulated sandbox customer cancellation' 
                    WHERE id = ?
                ")->execute([$payment['id']]);
            }

            $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?")->execute([$order['id']]);
            $pdo->commit();

            // Refresh
            $stmt->execute([$orderNumber, is_numeric($orderNumber) ? (int)$orderNumber : 0]);
            $order = $stmt->fetch();
            $stmtPayment->execute([$order['id']]);
            $payment = $stmtPayment->fetch();
        }
    }

    json_response([
        'success'         => true,
        'order_number'    => $order['order_number'],
        'order_id'        => (int)$order['id'],
        'order_status'    => $order['status'],
        'payment_method'  => $order['payment_method'],
        'payment_status'  => $payment['payment_status'] ?? 'Pending',
        'transaction_id'  => $payment['transaction_id'] ?? null,
        'card_type'       => $payment['card_type'] ?? null,
        'card_masked'     => $payment['card_masked'] ?? null,
        'paid_at'         => $payment['paid_at'] ?? null,
        'total_amount'    => (float)$order['total_amount'],
        'formatted_total' => format_price($order['total_amount']),
        'sandbox_mode'    => !empty($paymentConfig['sandbox']),
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
