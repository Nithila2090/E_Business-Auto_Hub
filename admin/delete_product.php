<?php
/**
 * AUTO HUB - Admin Delete Product
 */

require_once __DIR__ . '/../../config/helpers.php';
require_admin('../admin/login.php');

$pdo = get_db();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: products.php?success=' . urlencode('Product deleted successfully.'));
} else {
    header('Location: products.php');
}
exit;
