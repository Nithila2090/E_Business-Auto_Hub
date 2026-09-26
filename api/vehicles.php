<?php
/**
 * AUTO HUB - Vehicles API
 * Endpoints for Vehicle Makes, Models, and Years
 */

require_once __DIR__ . '/../config/helpers.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$pdo = get_db();
$action = $_GET['action'] ?? 'makes';

try {
    if ($action === 'makes') {
        $stmt = $pdo->query("SELECT id, name FROM vehicle_makes ORDER BY name ASC");
        $makes = $stmt->fetchAll();
        json_response(['success' => true, 'data' => $makes]);
    }

    if ($action === 'models') {
        $make_id = $_GET['make_id'] ?? null;
        $make_name = $_GET['make'] ?? null;

        if ($make_id) {
            $stmt = $pdo->prepare("SELECT id, name FROM vehicle_models WHERE make_id = ? ORDER BY name ASC");
            $stmt->execute([$make_id]);
        } elseif ($make_name) {
            $stmt = $pdo->prepare("SELECT vm.id, vm.name 
                                   FROM vehicle_models vm 
                                   JOIN vehicle_makes vmk ON vm.make_id = vmk.id 
                                   WHERE vmk.name = ? 
                                   ORDER BY vm.name ASC");
            $stmt->execute([$make_name]);
        } else {
            json_response(['success' => false, 'message' => 'Make ID or Make Name is required'], 400);
        }

        $models = $stmt->fetchAll();
        json_response(['success' => true, 'data' => $models]);
    }

    if ($action === 'years') {
        $model_id = $_GET['model_id'] ?? null;
        $model_name = $_GET['model'] ?? null;
        $make_name = $_GET['make'] ?? null;

        if ($model_id) {
            $stmt = $pdo->prepare("SELECT DISTINCT year FROM vehicle_years WHERE model_id = ? ORDER BY year DESC");
            $stmt->execute([$model_id]);
        } elseif ($model_name) {
            if ($make_name) {
                $stmt = $pdo->prepare("SELECT DISTINCT vy.year 
                                       FROM vehicle_years vy
                                       JOIN vehicle_models vm ON vy.model_id = vm.id
                                       JOIN vehicle_makes vmk ON vm.make_id = vmk.id
                                       WHERE vm.name = ? AND vmk.name = ?
                                       ORDER BY vy.year DESC");
                $stmt->execute([$model_name, $make_name]);
            } else {
                $stmt = $pdo->prepare("SELECT DISTINCT vy.year 
                                       FROM vehicle_years vy
                                       JOIN vehicle_models vm ON vy.model_id = vm.id
                                       WHERE vm.name = ?
                                       ORDER BY vy.year DESC");
                $stmt->execute([$model_name]);
            }
        } else {
            // Default list of modern auto years
            $years = range(2026, 2010);
            json_response(['success' => true, 'data' => $years]);
        }

        $years_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // Fallback default years if specific model years aren't individually populated
        $years = !empty($years_raw) ? $years_raw : range(2026, 2012);
        json_response(['success' => true, 'data' => $years]);
    }

    if ($action === 'all') {
        // Return full hierarchical vehicle dictionary
        $stmt = $pdo->query("SELECT vmk.name AS make_name, vm.name AS model_name, vy.year 
                             FROM vehicle_makes vmk 
                             JOIN vehicle_models vm ON vm.make_id = vmk.id 
                             LEFT JOIN vehicle_years vy ON vy.model_id = vm.id 
                             ORDER BY vmk.name, vm.name, vy.year DESC");
        $rows = $stmt->fetchAll();
        $tree = [];
        foreach ($rows as $r) {
            $make = $r['make_name'];
            $model = $r['model_name'];
            $year = $r['year'];

            if (!isset($tree[$make])) {
                $tree[$make] = ['models' => [], 'years' => []];
            }
            if (!in_array($model, $tree[$make]['models'])) {
                $tree[$make]['models'][] = $model;
            }
            if ($year && !in_array((string)$year, $tree[$make]['years'])) {
                $tree[$make]['years'][] = (string)$year;
            }
        }
        json_response(['success' => true, 'data' => $tree]);
    }

    json_response(['success' => false, 'message' => 'Invalid action parameter'], 400);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
