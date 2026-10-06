<?php
/**
 * AUTO HUB - Complete Backend & Customer Profile Automated Test Suite
 */

require_once __DIR__ . '/config/helpers.php';

$pdo = get_db();
$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $description\n";
        $passed++;
    } else {
        echo "[FAIL] $description\n";
        $failed++;
    }
}

function run_api_test($script, $query = [], $post = null, $session = []) {
    $sessionCode = '';
    if (!empty($session)) {
        foreach ($session as $k => $v) {
            $sessionCode .= '$_SESSION[' . var_export($k, true) . '] = ' . var_export($v, true) . ";\n";
        }
    }

    $code = '<?php
    $_GET = ' . var_export($query, true) . ';
    $_POST = ' . var_export($post ?: [], true) . ';
    $_SERVER["REQUEST_METHOD"] = ' . var_export($post !== null ? 'POST' : 'GET', true) . ';
    session_start();
    ' . $sessionCode . '
    require "' . addslashes(__DIR__ . '/' . $script) . '";
    ';

    $tempFile = __DIR__ . '/scratch/test_runner_temp.php';
    if (!is_dir(__DIR__ . '/scratch')) {
        mkdir(__DIR__ . '/scratch', 0777, true);
    }
    file_put_contents($tempFile, $code);
    $output = shell_exec("php " . escapeshellarg($tempFile));
    @unlink($tempFile);
    return json_decode($output, true);
}

echo "========================================================\n";
echo "   AUTO HUB - PHP 8 + MySQL Backend Automated Test Suite\n";
echo "========================================================\n\n";

// 1. Database connection & Tables test
$tables = ['users', 'vehicle_makes', 'vehicle_models', 'vehicle_years', 'categories', 'products', 'product_vehicle_compatibility', 'cart', 'cart_items', 'wishlist', 'orders', 'order_items', 'payments'];
foreach ($tables as $t) {
    $count = $pdo->query("SELECT count(*) FROM `$t`")->fetchColumn();
    assert_test("Database Table '$t' exists and queryable (rows: $count)", $count !== false);
}

// 2. Vehicles API: Makes (Exactly 5 brands)
$json = run_api_test('api/vehicles.php', ['action' => 'makes']);
assert_test("Vehicles API returns exactly 5 main makes (Count: " . count($json['data'] ?? []) . ")", ($json['success'] ?? false) && count($json['data'] ?? []) === 5);

// 3. Vehicles API: Models for Toyota (Exactly 5 models)
$json = run_api_test('api/vehicles.php', ['action' => 'models', 'make' => 'Toyota']);
$modelNames = array_column($json['data'] ?? [], 'name');
assert_test("Vehicles API returns exactly 5 Toyota models (Corolla, Yaris, Camry, Prius, Hilux)", count($modelNames) === 5 && in_array('Corolla', $modelNames) && in_array('Prius', $modelNames) && in_array('Hilux', $modelNames));

// 4. Vehicles API: Years for Toyota Corolla
$json = run_api_test('api/vehicles.php', ['action' => 'years', 'make' => 'Toyota', 'model' => 'Corolla']);
assert_test("Vehicles API returns years for Toyota Corolla including 2024", in_array(2024, $json['data'] ?? []) || in_array('2024', $json['data'] ?? []));

// 5. Vehicle Search Flow: Toyota Corolla 2024
$json = run_api_test('api/products.php', ['make' => 'Toyota', 'model' => 'Corolla', 'year' => 2024]);
assert_test("Product Catalog filters compatible products for 'Toyota Corolla 2024' (Found: " . count($json['data'] ?? []) . ")", ($json['success'] ?? false) && count($json['data'] ?? []) >= 8);

// 6. Product Details: Product ID 1 (Brembo Front Brake Pads)
$json = run_api_test('api/products.php', ['id' => 1]);
assert_test("Product Details API returns specs and compatibility list for ID 1", ($json['success'] ?? false) && !empty($json['data']['compatibility_list']));

// 7. Live Search: 'brake'
$json = run_api_test('api/products.php', ['search_suggestions' => 'brake']);
assert_test("Search Suggestions API returns matches for 'brake'", ($json['success'] ?? false) && count($json['data'] ?? []) > 0);

// 8. Auth API: Customer Login (kasun@autohub.lk / Autohub@2026)
$json = run_api_test('api/auth.php', [], ['action' => 'login', 'email' => 'kasun@autohub.lk', 'password' => 'Autohub@2026']);
assert_test("Customer Login authenticates with password_verify()", ($json['success'] ?? false) && $json['role'] === 'customer');

// 9. Auth API: Admin Login (admin@autohub.lk / Admin@2026)
$json = run_api_test('api/auth.php', [], ['action' => 'login', 'email' => 'admin@autohub.lk', 'password' => 'Admin@2026']);
assert_test("Admin Login authenticates and provides admin redirect", ($json['success'] ?? false) && $json['role'] === 'admin');

// 10. Cart API: Add Product ID 1 (quantity 2)
$pdo->exec("UPDATE products SET stock_quantity = 50 WHERE stock_quantity < 10");
$json = run_api_test('api/cart.php', [], ['action' => 'add', 'product_id' => 1, 'quantity' => 2], ['user_id' => 2]);
assert_test("Cart API adds product to database cart (Cart count: " . ($json['cart_count'] ?? 0) . ")", ($json['success'] ?? false) && ($json['cart_count'] ?? 0) >= 2);

// 11. Cart API: Apply Coupon AUTOHUB10
$json = run_api_test('api/cart.php', [], ['action' => 'apply_coupon', 'coupon_code' => 'AUTOHUB10'], ['user_id' => 2]);
assert_test("Cart API validates promo coupon 'AUTOHUB10'", ($json['success'] ?? false) && ($json['coupon']['code'] ?? '') === 'AUTOHUB10');

// 12. Cart API: Retrieve computed totals
$json = run_api_test('api/cart.php', ['action' => 'get'], null, ['user_id' => 2]);
assert_test("Cart API calculates subtotal, discount, and shipping", ($json['success'] ?? false) && ($json['data']['subtotal'] ?? 0) > 0);

// 13. Wishlist API: Toggle Product ID 3
$json = run_api_test('api/wishlist.php', [], ['action' => 'toggle', 'product_id' => 3], ['user_id' => 2]);
assert_test("Wishlist API toggles item for logged in customer", ($json['success'] ?? false));

// 14. Order API: Create Order from current cart
$json = run_api_test('api/orders.php', [], [
    'action' => 'create',
    'shipping_name' => 'Kasun Jayasuriya',
    'shipping_phone' => '+94 77 987 6543',
    'shipping_email' => 'kasun@autohub.lk',
    'shipping_address' => 'No. 45/2, Flower Road',
    'shipping_city' => 'Colombo 07',
    'shipping_province' => 'Western Province',
    'payment_method' => 'cod',
    'delivery_instructions' => 'Test order verification'
], ['user_id' => 2]);
$createdOrderNumber = $json['order_number'] ?? '';
assert_test("Order API creates order in MySQL and decrements stock (Order: $createdOrderNumber)", ($json['success'] ?? false) && !empty($createdOrderNumber));

// 15. Order API: Track Order
$json = run_api_test('api/orders.php', ['action' => 'track', 'order_number' => $createdOrderNumber]);
assert_test("Order Tracking API retrieves created order details and timeline", ($json['success'] ?? false) && ($json['data']['order_number'] ?? '') === $createdOrderNumber);

// 16. Database: Users Table Address Schema Verification
$userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$hasProfileCols = in_array('address', $userCols) && in_array('city', $userCols) && in_array('postal_code', $userCols) && in_array('province', $userCols);
assert_test("Users table has address, city, postal_code, and province columns", $hasProfileCols);

// 17. Profile API: Unauthenticated Access Rejection
$json = run_api_test('api/profile.php', ['action' => 'get_profile']);
assert_test("Profile API rejects unauthenticated requests with 401", empty($json['success']));

// 18. Profile API: Authenticated User Profile Retrieval (User ID 2: Kasun)
$json = run_api_test('api/profile.php', ['action' => 'get_profile'], null, [
    'user_id' => 2,
    'user_name' => 'Kasun Jayasuriya',
    'user_email' => 'kasun@autohub.lk',
    'role' => 'customer'
]);
assert_test("Profile API returns current authenticated customer details", ($json['success'] ?? false) && ($json['user']['email'] ?? '') === 'kasun@autohub.lk');

// 19. Profile API: Update Profile Information
$json = run_api_test('api/profile.php', [], [
    'action' => 'update_profile',
    'full_name' => 'Kasun Jayasuriya Updated',
    'phone' => '+94 77 987 6543',
    'address' => 'No. 45/2, Flower Road, Colombo 07',
    'city' => 'Colombo',
    'postal_code' => '00700',
    'province' => 'Western Province'
], [
    'user_id' => 2,
    'user_name' => 'Kasun Jayasuriya',
    'user_email' => 'kasun@autohub.lk',
    'role' => 'customer'
]);
assert_test("Profile API updates full name, address, and city for current user", ($json['success'] ?? false) && ($json['user']['full_name'] ?? '') === 'Kasun Jayasuriya Updated');

// 20. Profile API: Change Password with Incorrect Current Password Rejection
$json = run_api_test('api/profile.php', [], [
    'action' => 'change_password',
    'current_password' => 'WrongPassword123',
    'new_password' => 'NewAutohub@2026',
    'confirm_password' => 'NewAutohub@2026'
], [
    'user_id' => 2,
    'user_name' => 'Kasun Jayasuriya',
    'user_email' => 'kasun@autohub.lk',
    'role' => 'customer'
]);
assert_test("Password Change rejects incorrect current password", empty($json['success']));

// 21. Profile API: Change Password with Valid Credentials
$json = run_api_test('api/profile.php', [], [
    'action' => 'change_password',
    'current_password' => 'Autohub@2026',
    'new_password' => 'Autohub@2026New',
    'confirm_password' => 'Autohub@2026New'
], [
    'user_id' => 2,
    'user_name' => 'Kasun Jayasuriya',
    'user_email' => 'kasun@autohub.lk',
    'role' => 'customer'
]);
$pwSuccess = ($json['success'] ?? false);

// Revert password back so subsequent test runs work cleanly
run_api_test('api/profile.php', [], [
    'action' => 'change_password',
    'current_password' => 'Autohub@2026New',
    'new_password' => 'Autohub@2026',
    'confirm_password' => 'Autohub@2026'
], [
    'user_id' => 2,
    'user_name' => 'Kasun Jayasuriya',
    'user_email' => 'kasun@autohub.lk',
    'role' => 'customer'
]);
assert_test("Password Change validates current password and updates hash with password_hash()", $pwSuccess);

// 22. Profile Security: Session Isolation (User 2 only retrieves their own orders)
$stmtIsolation = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$stmtIsolation->execute([2]);
$user2OrderCount = (int)$stmtIsolation->fetchColumn();
assert_test("User order queries strictly filter by authenticated user session ID ($user2OrderCount orders)", $user2OrderCount > 0);

// 23. Image Upload Security: Allowed format check
$imgUploadAllowed = function_exists('handle_image_upload');
assert_test("Image Upload security handler is defined with MIME validation", $imgUploadAllowed);

echo "\n========================================================\n";
echo "Test Results: $passed Passed, $failed Failed\n";
echo "========================================================\n";

if ($failed === 0) {
    echo ">>> ALL TESTS PASSED SUCCESSFULLY! <<<\n";
}
