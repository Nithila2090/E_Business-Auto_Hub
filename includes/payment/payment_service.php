<?php
/**
 * AUTO HUB - Payment Service Layer
 * Central payment orchestrator for Cash on Delivery and Online Card Payment Gateway
 *
 * Security:
 * - Server-side validation of order amounts and signatures.
 * - Atomicity using PDO transactions.
 * - Idempotency to prevent duplicate webhook updates.
 * - Safe non-sensitive error logging.
 */

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/payment.php';
require_once __DIR__ . '/gateway.php';

class PaymentService {
    protected PDO $pdo;
    protected PaymentGatewayInterface $gateway;

    public function __construct(?PDO $pdo = null, ?PaymentGatewayInterface $gateway = null) {
        $this->pdo = $pdo ?? get_db();
        $this->gateway = $gateway ?? new PayHereGateway();
    }

    public function getGateway(): PaymentGatewayInterface {
        return $this->gateway;
    }

    /**
     * Create payment request for an existing order
     */
    public function createPaymentPayload(array $order, array $customer): array {
        return $this->gateway->createPaymentRequest($order, $customer);
    }

    /**
     * Handle incoming server-to-server webhook notification
     *
     * @param array $postData Raw POST data from gateway
     * @return array [success => bool, http_code => int, message => string, order => ?array]
     */
    public function processCallback(array $postData): array {
        // 1. Verify signature and extract callback data
        $verification = $this->gateway->verifyCallback($postData);

        // Safe webhook logging
        $this->logPaymentEvent('WEBHOOK_RECEIVED', [
            'order_id'       => $verification['order_id'] ?? ($postData['order_id'] ?? ''),
            'payment_id'     => $verification['payment_id'] ?? ($postData['payment_id'] ?? ''),
            'amount'         => $verification['amount'] ?? ($postData['payhere_amount'] ?? ''),
            'status_code'    => $verification['status_code'] ?? ($postData['status_code'] ?? ''),
            'valid_signature'=> $verification['valid'] ?? false
        ]);

        if (!$verification['valid']) {
            return [
                'success'   => false,
                'http_code' => 400,
                'message'   => $verification['error'] ?? 'Checksum verification failed.'
            ];
        }

        $orderNumber = $verification['order_id'];

        // 2. Fetch order from database
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE order_number = ? OR id = ? LIMIT 1");
        $stmt->execute([$orderNumber, is_numeric($orderNumber) ? (int)$orderNumber : 0]);
        $order = $stmt->fetch();

        if (!$order) {
            return [
                'success'   => false,
                'http_code' => 404,
                'message'   => "Order #{$orderNumber} not found in database."
            ];
        }

        // 3. Verify payment amount matches order total in database
        $expectedAmount = number_format((float)$order['total_amount'], 2, '.', '');
        $receivedAmount = number_format((float)$verification['amount'], 2, '.', '');

        if ($expectedAmount !== $receivedAmount) {
            return [
                'success'   => false,
                'http_code' => 400,
                'message'   => "Order total mismatch. Expected: {$expectedAmount}, Received: {$receivedAmount}."
            ];
        }

        // 4. Check existing payment record for idempotency
        $stmtPay = $this->pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmtPay->execute([$order['id']]);
        $existingPayment = $stmtPay->fetch();

        if ($existingPayment && $existingPayment['payment_status'] === 'Paid' && $verification['payment_status'] === 'Paid') {
            return [
                'success'   => true,
                'http_code' => 200,
                'message'   => "Payment already verified and confirmed for Order #{$orderNumber}."
            ];
        }

        // 5. Update database inside transaction
        try {
            $this->pdo->beginTransaction();

            $paidAt = ($verification['payment_status'] === 'Paid') ? date('Y-m-d H:i:s') : null;
            $gatewayTxId = $verification['payment_id'] ?: null;

            // Update payments table
            if ($existingPayment) {
                $stmtUpd = $this->pdo->prepare("
                    UPDATE payments 
                    SET payment_status = ?,
                        transaction_id = ?,
                        payment_gateway = ?,
                        currency = ?,
                        card_type = ?,
                        card_masked = ?,
                        status_message = ?,
                        paid_at = COALESCE(paid_at, ?)
                    WHERE id = ?
                ");
                $stmtUpd->execute([
                    $verification['payment_status'],
                    $gatewayTxId,
                    $this->gateway->getGatewayName(),
                    $verification['currency'],
                    $verification['payment_method'],
                    $verification['card_masked'] ?? null,
                    $verification['status_message'],
                    $paidAt,
                    $existingPayment['id']
                ]);
            } else {
                $stmtIns = $this->pdo->prepare("
                    INSERT INTO payments (
                        order_id, payment_method, payment_gateway, amount, currency,
                        transaction_id, card_type, card_masked, payment_status,
                        status_message, paid_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtIns->execute([
                    $order['id'],
                    $verification['payment_method'],
                    $this->gateway->getGatewayName(),
                    $verification['amount'],
                    $verification['currency'],
                    $gatewayTxId,
                    $verification['payment_method'],
                    $verification['card_masked'] ?? null,
                    $verification['payment_status'],
                    $verification['status_message'],
                    $paidAt
                ]);
            }

            // Update orders table
            $stmtUpdOrder = $this->pdo->prepare("
                UPDATE orders 
                SET payment_status = ?,
                    transaction_id = ?,
                    payment_reference = ?,
                    paid_at = COALESCE(paid_at, ?),
                    status = ?
                WHERE id = ?
            ");
            $stmtUpdOrder->execute([
                $verification['payment_status'],
                $gatewayTxId,
                $gatewayTxId,
                $paidAt,
                $verification['order_status'],
                $order['id']
            ]);

            // Reduce product stock only after successful verified payment (and only once)
            if ($verification['payment_status'] === 'Paid') {
                $stmtItems = $this->pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $stmtItems->execute([$order['id']]);
                $orderItems = $stmtItems->fetchAll();

                $stmtStock = $this->pdo->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
                foreach ($orderItems as $it) {
                    if (!empty($it['product_id']) && !empty($it['quantity'])) {
                        $stmtStock->execute([(int)$it['quantity'], (int)$it['product_id']]);
                    }
                }
            }

            $this->pdo->commit();

            return [
                'success'        => true,
                'http_code'      => 200,
                'message'        => 'Payment callback processed successfully.',
                'payment_status' => $verification['payment_status'],
                'order_status'   => $verification['order_status'],
                'order_number'   => $order['order_number']
            ];

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return [
                'success'   => false,
                'http_code' => 500,
                'message'   => 'Database error updating payment status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Retrieve live verified payment status for an order
     */
    public function getOrderStatus(string $orderNumber): ?array {
        $stmt = $this->pdo->prepare("
            SELECT o.*, 
                   p.payment_status AS detailed_payment_status,
                   p.payment_gateway,
                   p.card_type,
                   p.card_masked,
                   p.status_message,
                   p.transaction_id AS gateway_tx_id
            FROM orders o
            LEFT JOIN (
                SELECT p1.* FROM payments p1
                INNER JOIN (SELECT order_id, MAX(id) as max_id FROM payments GROUP BY order_id) p2
                ON p1.id = p2.max_id
            ) p ON p.order_id = o.id
            WHERE o.order_number = ? OR o.id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderNumber, is_numeric($orderNumber) ? (int)$orderNumber : 0]);
        $order = $stmt->fetch();

        return $order ?: null;
    }

    /**
     * Safe non-sensitive event logger
     */
    protected function logPaymentEvent(string $event, array $context = []): void {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $entry = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'event'     => $event,
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ], $context);

        @file_put_contents($logDir . '/payment_events.log', json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    }
}
