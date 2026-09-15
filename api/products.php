<?php
/**
 * AUTO HUB - Products API
 * Filtering, searching, sorting, vehicle compatibility queries, and details
 */

require_once __DIR__ . '/../config/helpers.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = get_db();

try {
    // 1. Single Product Detail
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            json_response(['success' => false, 'message' => 'Product not found'], 404);
        }

        // Decode specifications
        $product['specs_array'] = !empty($product['specs']) ? json_decode($product['specs'], true) : [];
        
        // Gallery array
        $product['gallery_array'] = !empty($product['gallery']) 
            ? array_map('trim', explode(',', $product['gallery'])) 
            : [$product['image']];

        // Fetch vehicle compatibility records
        $stmtCompat = $pdo->prepare("
            SELECT vmk.name AS make, vm.name AS model, pvc.year
            FROM product_vehicle_compatibility pvc
            JOIN vehicle_makes vmk ON pvc.make_id = vmk.id
            JOIN vehicle_models vm ON pvc.model_id = vm.id
            WHERE pvc.product_id = ?
            ORDER BY vmk.name, vm.name, pvc.year DESC
        ");
        $stmtCompat->execute([$id]);
        $compatList = $stmtCompat->fetchAll();

        // Group compatibility for human display: "Make Model (MinYear - MaxYear)"
        $groupedCompat = [];
        foreach ($compatList as $row) {
            $key = $row['make'] . ' ' . $row['model'];
            if (!isset($groupedCompat[$key])) {
                $groupedCompat[$key] = ['make' => $row['make'], 'model' => $row['model'], 'years' => []];
            }
            $groupedCompat[$key]['years'][] = (int)$row['year'];
        }

        $fitmentStrings = [];
        foreach ($groupedCompat as $key => $data) {
            $minY = min($data['years']);
            $maxY = max($data['years']);
            $yearStr = ($minY === $maxY) ? (string)$minY : "$minY - $maxY";
            $fitmentStrings[] = "{$data['make']} {$data['model']} ($yearStr)";
        }
        $product['compatibility_list'] = $fitmentStrings;
        $product['compatibility_raw'] = $compatList;

        // Fetch related products from same category
        $stmtRelated = $pdo->prepare("
            SELECT p.id, p.name, p.brand, p.price, p.old_price, p.discount, p.image, p.rating, p.reviews_count, p.stock_quantity
            FROM products p
            WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
            ORDER BY p.is_featured DESC, p.id DESC
            LIMIT 4
        ");
        $stmtRelated->execute([$product['category_id'], $id]);
        $product['related_products'] = $stmtRelated->fetchAll();

        json_response(['success' => true, 'data' => $product]);
    }

    // 2. Fast Search Suggestions Dropdown
    if (isset($_GET['search_suggestions'])) {
        $query = trim($_GET['search_suggestions']);
        if (strlen($query) < 2) {
            json_response(['success' => true, 'data' => []]);
        }

        $searchTerm = "%$query%";
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.brand, p.price, p.image, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active' AND (
                p.name LIKE ? OR 
                p.brand LIKE ? OR 
                p.sku LIKE ? OR 
                p.part_number LIKE ? OR
                c.name LIKE ?
            )
            ORDER BY p.is_bestseller DESC, p.name ASC
            LIMIT 6
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $results = $stmt->fetchAll();
        json_response(['success' => true, 'data' => $results]);
    }

    // 3. Filtered Product Catalog List
    $make = trim($_GET['make'] ?? '');
    $model = trim($_GET['model'] ?? '');
    $year = (int)($_GET['year'] ?? 0);
    $category = trim($_GET['category'] ?? '');
    $brand = trim($_GET['brand'] ?? '');
    $min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
    $max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
    $in_stock = isset($_GET['in_stock']) && ($_GET['in_stock'] === '1' || $_GET['in_stock'] === 'true');
    $search = trim($_GET['search'] ?? '');
    $sort = trim($_GET['sort'] ?? 'featured');
    $tab = trim($_GET['tab'] ?? '');

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 24)));
    $offset = ($page - 1) * $limit;

    $where = ["p.status = 'active'"];
    $params = [];

    // Vehicle Compatibility Filters
    $joinCompatibility = false;
    if (!empty($make) || !empty($model) || $year > 0) {
        $joinCompatibility = true;
        if (!empty($make)) {
            $where[] = "vmk.name = ?";
            $params[] = $make;
        }
        if (!empty($model)) {
            $where[] = "vm.name = ?";
            $params[] = $model;
        }
        if ($year > 0) {
            $where[] = "pvc.year = ?";
            $params[] = $year;
        }
    }

    // Category Filter
    if (!empty($category)) {
        if (is_numeric($category)) {
            $where[] = "p.category_id = ?";
            $params[] = (int)$category;
        } else {
            $where[] = "(c.slug = ? OR c.name = ?)";
            $params[] = $category;
            $params[] = $category;
        }
    }

    // Brand Filter
    if (!empty($brand)) {
        $where[] = "p.brand = ?";
        $params[] = $brand;
    }

    // Price Filter
    if ($min_price !== null) {
        $where[] = "p.price >= ?";
        $params[] = $min_price;
    }
    if ($max_price !== null) {
        $where[] = "p.price <= ?";
        $params[] = $max_price;
    }

    // Stock Filter
    if ($in_stock) {
        $where[] = "p.stock_quantity > 0";
    }

    // Search Query Filter
    if (!empty($search)) {
        $searchTerm = "%$search%";
        $where[] = "(p.name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ? OR p.part_number LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Tabs (Home page tabs: bestseller, new, sale)
    if ($tab === 'bestseller') {
        $where[] = "p.is_bestseller = 1";
    } elseif ($tab === 'new') {
        $where[] = "p.is_new = 1";
    } elseif ($tab === 'sale') {
        $where[] = "p.old_price IS NOT NULL AND p.old_price > p.price";
    }

    // Sorting
    $orderBy = "p.is_featured DESC, p.id ASC";
    switch ($sort) {
        case 'price_low':
        case 'price-asc':
            $orderBy = "p.price ASC";
            break;
        case 'price_high':
        case 'price-desc':
            $orderBy = "p.price DESC";
            break;
        case 'rating':
            $orderBy = "p.rating DESC, p.reviews_count DESC";
            break;
        case 'popular':
        case 'bestselling':
            $orderBy = "p.is_bestseller DESC, p.reviews_count DESC";
            break;
        case 'newest':
            $orderBy = "p.is_new DESC, p.id DESC";
            break;
        default:
            $orderBy = "p.is_featured DESC, p.id ASC";
            break;
    }

    $whereSql = implode(' AND ', $where);

    // Build From SQL
    $fromSql = "FROM products p LEFT JOIN categories c ON p.category_id = c.id";
    if ($joinCompatibility) {
        $fromSql .= " JOIN product_vehicle_compatibility pvc ON p.id = pvc.product_id
                     JOIN vehicle_makes vmk ON pvc.make_id = vmk.id
                     JOIN vehicle_models vm ON pvc.model_id = vm.id";
    }

    // Count query
    $countSql = "SELECT COUNT(DISTINCT p.id) AS total $fromSql WHERE $whereSql";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalCount = (int)$stmtCount->fetch()['total'];

    // Data query
    $selectSql = "SELECT DISTINCT p.*, c.name AS category_name, c.slug AS category_slug $fromSql WHERE $whereSql ORDER BY $orderBy LIMIT $limit OFFSET $offset";
    $stmtData = $pdo->prepare($selectSql);
    $stmtData->execute($params);
    $products = $stmtData->fetchAll();

    // Format products for frontend consumption
    foreach ($products as &$prod) {
        $prod['in_stock'] = $prod['stock_quantity'] > 0;
        $prod['formatted_price'] = format_price($prod['price']);
        $prod['formatted_old_price'] = $prod['old_price'] ? format_price($prod['old_price']) : null;
    }

    json_response([
        'success' => true,
        'data' => $products,
        'pagination' => [
            'total' => $totalCount,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => ceil($totalCount / $limit),
        ],
        'filters_applied' => [
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'category' => $category,
            'brand' => $brand,
            'search' => $search,
            'sort' => $sort,
        ]
    ]);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
