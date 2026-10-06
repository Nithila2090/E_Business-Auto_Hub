<?php
require_once __DIR__ . '/config/helpers.php';

echo "--- 1. Testing Vehicles API ---\n";
$_GET = ['action' => 'makes'];
ob_start();
require __DIR__ . '/api/vehicles.php';
$makesJson = ob_get_clean();
$makes = json_decode($makesJson, true);
echo "Total makes: " . count($makes['data']) . "\n";

echo "\n--- 2. Testing Vehicle Models for Toyota ---\n";
$_GET = ['action' => 'models', 'make' => 'Toyota'];
ob_start();
require __DIR__ . '/api/vehicles.php';
$modelsJson = ob_get_clean();
$models = json_decode($modelsJson, true);
echo "Toyota models: " . count($models['data']) . " (e.g. " . $models['data'][0]['name'] . ")\n";

echo "\n--- 3. Testing Products Compatible with Toyota Premio 2024 ---\n";
$_GET = ['make' => 'Toyota', 'model' => 'Premio', 'year' => 2024];
ob_start();
require __DIR__ . '/api/products.php';
$prodsJson = ob_get_clean();
$prods = json_decode($prodsJson, true);
echo "Compatible products found: " . count($prods['data']) . "\n";
foreach ($prods['data'] as $p) {
    echo "  * [" . $p['sku'] . "] " . $p['name'] . " - " . $p['formatted_price'] . "\n";
}
