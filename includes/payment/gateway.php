<?php
/**
 * AUTO HUB - Payment Gateway Abstract Interface & PayHere Implementation
 * Handles Sri Lankan Payment Gateway integrations (PayHere Hosted Checkout API v1)
 *
 * Security:
 * - Never exposes sensitive merchant secrets to the client.
 * - Enforces server-side MD5 hash calculation and IPN checksum verification.
 */

require_once __DIR__ . '/../../config/payment.php';

interface PaymentGatewayInterface {
    public function getGatewayName(): string;
    public function getGatewayUrl(): string;
    public function isSandbox(): bool;
    public function createPaymentRequest(array $order, array $customer): array;
    public function verifyCallback(array $data): array;
}

/**
 * PayHere Sri Lanka Gateway Provider
 */
class PayHereGateway implements PaymentGatewayInterface {
    protected array $config;

    public function __construct(?array $config = null) {
        $this->config = $config ?? get_payment_config();
    }

    public function getGatewayName(): string {
        return 'PayHere';
    }

    public function getGatewayUrl(): string {
        return $this->config['gateway_url'];
    }

    public function isSandbox(): bool {
        return !empty($this->config['sandbox']);
    }

    /**
     * Generate PayHere MD5 security hash
     * Formula: UPPERCASE(MD5(merchant_id + order_id + amount_formatted + currency + UPPERCASE(MD5(merchant_secret))))
     */
    public function generateHash(string|int $orderId, float|int|string $amount, string $currency = 'LKR'): string {
        $merchantId = $this->config['merchant_id'];
        $merchantSecret = $this->config['merchant_secret'];
        $formattedAmount = number_format((float)$amount, 2, '.', '');
        $hashedSecret = strtoupper(md5($merchantSecret));
        
        return strtoupper(md5($merchantId . (string)$orderId . $formattedAmount . $currency . $hashedSecret));
    }

    /**
     * Verify PayHere server callback checksum
     * Formula: UPPERCASE(MD5(merchant_id + order_id + payhere_amount + payhere_currency + status_code + UPPERCASE(MD5(merchant_secret))))
     */
    public function verifyChecksum(string $merchantId, string $orderId, string|float $amount, string $currency, string|int $statusCode, string $receivedSignature): bool {
        return verify_payhere_hash($merchantId, $orderId, $amount, $currency, $statusCode, $receivedSignature);
    }

    /**
     * Build signed PayHere request payload
     */
    public function createPaymentRequest(array $order, array $customer): array {
        $orderNumber = $order['order_number'] ?? (string)$order['id'];
        $amount = number_format((float)$order['total_amount'], 2, '.', '');
        $currency = $this->config['currency'] ?? 'LKR';

        $hash = $this->generateHash($orderNumber, $amount, $currency);

        $fullName = trim($customer['shipping_name'] ?? $customer['full_name'] ?? 'Customer');
        $nameParts = explode(' ', $fullName, 2);
        $firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Valued';
        $lastName  = !empty($nameParts[1]) ? $nameParts[1] : 'Customer';

        $email = trim($customer['shipping_email'] ?? $customer['email'] ?? 'customer@autohub.lk');
        $phone = trim($customer['shipping_phone'] ?? $customer['phone'] ?? '0707275599');
        $address = trim($customer['shipping_address'] ?? $customer['address'] ?? 'AutoHub Delivery Address');
        $city = trim($customer['shipping_city'] ?? $customer['city'] ?? 'Colombo');
        $country = 'Sri Lanka';

        $returnUrl = $this->config['return_url'] . (str_contains($this->config['return_url'], '?') ? '&' : '?') . 'order_id=' . urlencode($orderNumber);
        $cancelUrl = $this->config['cancel_url'] . (str_contains($this->config['cancel_url'], '?') ? '&' : '?') . 'order_id=' . urlencode($orderNumber);

        return [
            'merchant_id'      => $this->config['merchant_id'],
            'return_url'       => $returnUrl,
            'cancel_url'       => $cancelUrl,
            'notify_url'       => $this->config['notify_url'],
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
     * Verify incoming payment callback data
     */
    public function verifyCallback(array $data): array {
        $merchantId      = trim($data['merchant_id'] ?? '');
        $orderId         = trim($data['order_id'] ?? '');
        $paymentId       = trim($data['payment_id'] ?? '');
        $payhereAmount   = trim($data['payhere_amount'] ?? '');
        $payhereCurrency = trim($data['payhere_currency'] ?? 'LKR');
        $statusCode      = trim((string)($data['status_code'] ?? ''));
        $receivedSig     = trim($data['md5sig'] ?? '');
        $method          = trim($data['method'] ?? 'Online Card Payment');
        $statusMessage   = trim($data['status_message'] ?? '');
        $cardNoMasked    = trim($data['card_no'] ?? '');

        if (empty($merchantId) || empty($orderId) || empty($payhereAmount) || $statusCode === '' || empty($receivedSig)) {
            return [
                'valid'   => false,
                'error'   => 'Missing mandatory callback parameters.',
                'order_id'=> $orderId
            ];
        }

        $isValid = $this->verifyChecksum($merchantId, $orderId, $payhereAmount, $payhereCurrency, $statusCode, $receivedSig);
        if (!$isValid) {
            return [
                'valid'   => false,
                'error'   => 'Invalid payment signature checksum (tamper attempt).',
                'order_id'=> $orderId
            ];
        }

        // Map status_code to standardized status
        $statusMap = [
            '2'  => 'Paid',
            '0'  => 'Pending',
            '-1' => 'Cancelled',
            '-2' => 'Failed',
            '-3' => 'Chargedback'
        ];
        $paymentStatus = $statusMap[$statusCode] ?? 'Pending';

        $orderStatusMap = [
            '2'  => 'Processing',
            '0'  => 'Pending Payment',
            '-1' => 'Cancelled',
            '-2' => 'Payment Failed',
            '-3' => 'Cancelled'
        ];
        $orderStatus = $orderStatusMap[$statusCode] ?? 'Pending Payment';

        return [
            'valid'          => true,
            'order_id'       => $orderId,
            'payment_id'     => $paymentId,
            'amount'         => $payhereAmount,
            'currency'       => $payhereCurrency,
            'status_code'    => (int)$statusCode,
            'payment_status' => $paymentStatus,
            'order_status'   => $orderStatus,
            'payment_method' => $method,
            'status_message' => $statusMessage ?: ($paymentStatus === 'Paid' ? 'Payment captured successfully' : 'Payment was not completed'),
            'card_masked'    => $cardNoMasked,
        ];
    }
}
