<?php
require_once __DIR__ . '/../config/db.php';

try {
    // 1. Ensure payments table supports all status strings: Pending, Paid, Failed, Cancelled, Refunded, Chargedback
    $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_status VARCHAR(50) NOT NULL DEFAULT 'Pending'");
    echo "[OK] payments.payment_status updated to VARCHAR(50)\n";

    // 2. Ensure orders table has payment_status and payment_message / payment_reference
    $cols = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('payment_status', $cols)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) NOT NULL DEFAULT 'Pending' AFTER payment_method");
        echo "[OK] orders.payment_status added\n";
    }
    if (!in_array('transaction_id', $cols)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(100) NULL AFTER payment_status");
        echo "[OK] orders.transaction_id added\n";
    }
    if (!in_array('paid_at', $cols)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN paid_at DATETIME NULL AFTER transaction_id");
        echo "[OK] orders.paid_at added\n";
    }
    if (!in_array('payment_message', $cols)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_message VARCHAR(255) NULL AFTER paid_at");
        echo "[OK] orders.payment_message added\n";
    }

    // 3. Ensure payments table has all necessary fields
    $payCols = $pdo->query("DESCRIBE payments")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status_message', $payCols)) {
        $pdo->exec("ALTER TABLE payments ADD COLUMN status_message VARCHAR(255) NULL AFTER payment_status");
        echo "[OK] payments.status_message added\n";
    }

    echo "Database schema verified and updated successfully!\n";
} catch (Exception $e) {
    echo "Error updating database schema: " . $e->getMessage() . "\n";
}
