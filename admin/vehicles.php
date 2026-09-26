<?php
/**
 * AUTO HUB - Admin Vehicle Makes, Models & Years Management
 */

$adminPageTitle = "Manage Vehicles";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();
$msg = '';
$error = '';

// 1. Handle Add Make
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_make'])) {
    $makeName = trim($_POST['make_name'] ?? '');
    if (empty($makeName)) {
        $error = 'Make name cannot be empty.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO vehicle_makes (name) VALUES (?)");
            $stmt->execute([$makeName]);
            $msg = "Vehicle make '$makeName' added successfully!";
        } catch (Exception $e) {
            $error = "Make '$makeName' already exists or error occurred.";
        }
    }
}

// 2. Handle Add Model
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_model'])) {
    $makeId = (int)($_POST['make_id'] ?? 0);
    $modelName = trim($_POST['model_name'] ?? '');
    if ($makeId <= 0 || empty($modelName)) {
        $error = 'Please select a Make and enter a Model name.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO vehicle_models (make_id, name) VALUES (?, ?)");
            $stmt->execute([$makeId, $modelName]);
            $modelId = (int)$pdo->lastInsertId();

            // Default auto years (2026 to 2014)
            $stmtYr = $pdo->prepare("INSERT INTO vehicle_years (model_id, year) VALUES (?, ?)");
            foreach (range(2026, 2014) as $yr) {
                $stmtYr->execute([$modelId, $yr]);
            }

            $msg = "Model '$modelName' added with standard year range (2014-2026)!";
        } catch (Exception $e) {
            $error = "Model '$modelName' already exists under this Make.";
        }
    }
}

// 3. Handle Add Year
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_year'])) {
    $modelId = (int)($_POST['model_id'] ?? 0);
    $year = (int)($_POST['year'] ?? 0);
    if ($modelId <= 0 || $year <= 1980 || $year > 2030) {
        $error = 'Please select a valid model and enter a valid 4-digit year.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO vehicle_years (model_id, year) VALUES (?, ?)");
            $stmt->execute([$modelId, $year]);
            $msg = "Year $year added successfully!";
        } catch (Exception $e) {
            $error = "Year $year already exists for this model.";
        }
    }
}

// Fetch all Makes
$makes = $pdo->query("SELECT * FROM vehicle_makes ORDER BY name ASC")->fetchAll();

// Fetch Models list
$stmtModels = $pdo->query("
    SELECT vm.*, vmk.name AS make_name, COUNT(vy.id) AS year_count
    FROM vehicle_models vm
    JOIN vehicle_makes vmk ON vm.make_id = vmk.id
    LEFT JOIN vehicle_years vy ON vy.model_id = vm.id
    GROUP BY vm.id
    ORDER BY vmk.name ASC, vm.name ASC
");
$models = $stmtModels->fetchAll();
?>

<?php if (!empty($msg)): ?>
  <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; padding: 0.85rem 1.25rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1.5rem;">
    <i class="fas fa-check-circle"></i> <?php echo e($msg); ?>
  </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 0.85rem 1.25rem; border-radius: 8px; font-size: 0.9rem; margin-bottom: 1.5rem;">
    <i class="fas fa-exclamation-triangle"></i> <?php echo e($error); ?>
  </div>
<?php endif; ?>

<!-- Quick Creation Forms Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
  
  <!-- Add Make -->
  <div class="admin-card">
    <h3 style="font-size: 1.05rem; font-weight: 700; color: white; margin-bottom: 1rem;">
      <i class="fas fa-car text-red"></i> 1. Add Vehicle Make
    </h3>
    <form action="vehicles.php" method="POST">
      <input type="hidden" name="add_make" value="1">
      <div class="admin-form-group">
        <label class="admin-label">Make Name</label>
        <input type="text" name="make_name" class="admin-input" required placeholder="e.g. Subaru">
      </div>
      <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm" style="width: 100%; justify-content: center;">
        <i class="fas fa-plus"></i> Add Make
      </button>
    </form>
  </div>

  <!-- Add Model -->
  <div class="admin-card">
    <h3 style="font-size: 1.05rem; font-weight: 700; color: white; margin-bottom: 1rem;">
      <i class="fas fa-car-side text-red"></i> 2. Add Model to Make
    </h3>
    <form action="vehicles.php" method="POST">
      <input type="hidden" name="add_model" value="1">
      <div class="admin-form-group">
        <label class="admin-label">Select Make</label>
        <select name="make_id" class="admin-select" required>
          <option value="">Choose Make</option>
          <?php foreach ($makes as $m): ?>
            <option value="<?php echo $m['id']; ?>"><?php echo e($m['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="admin-form-group">
        <label class="admin-label">Model Name</label>
        <input type="text" name="model_name" class="admin-input" required placeholder="e.g. Forester">
      </div>
      <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm" style="width: 100%; justify-content: center;">
        <i class="fas fa-plus"></i> Add Model
      </button>
    </form>
  </div>

  <!-- Add Year -->
  <div class="admin-card">
    <h3 style="font-size: 1.05rem; font-weight: 700; color: white; margin-bottom: 1rem;">
      <i class="fas fa-calendar-alt text-red"></i> 3. Add Specific Year
    </h3>
    <form action="vehicles.php" method="POST">
      <input type="hidden" name="add_year" value="1">
      <div class="admin-form-group">
        <label class="admin-label">Select Model</label>
        <select name="model_id" class="admin-select" required>
          <option value="">Choose Model</option>
          <?php foreach ($models as $mod): ?>
            <option value="<?php echo $mod['id']; ?>"><?php echo e($mod['make_name']); ?> - <?php echo e($mod['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="admin-form-group">
        <label class="admin-label">Year (4 digits)</label>
        <input type="number" name="year" class="admin-input" required placeholder="2027" min="1980" max="2035">
      </div>
      <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm" style="width: 100%; justify-content: center;">
        <i class="fas fa-plus"></i> Add Year
      </button>
    </form>
  </div>

</div>

<!-- Vehicle Database Table -->
<div class="admin-card">
  <div class="admin-card-header">
    <h2 class="admin-card-title"><i class="fas fa-table"></i> Vehicle Hierarchy (<?php echo count($makes); ?> Makes &bull; <?php echo count($models); ?> Models)</h2>
  </div>

  <table class="admin-table">
    <thead>
      <tr>
        <th style="width: 25%;">Vehicle Make</th>
        <th style="width: 35%;">Model Name</th>
        <th style="width: 20%;">Year Count</th>
        <th style="text-align: right;">Added On</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($models as $mo): ?>
        <tr>
          <td><strong style="color: var(--primary-red);"><?php echo e($mo['make_name']); ?></strong></td>
          <td><strong style="color: white;"><?php echo e($mo['name']); ?></strong></td>
          <td><span class="admin-badge admin-badge-info"><?php echo (int)$mo['year_count']; ?> years</span></td>
          <td style="text-align: right; color: var(--admin-text-muted); font-size: 0.8rem;"><?php echo date('d M Y', strtotime($mo['created_at'])); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
