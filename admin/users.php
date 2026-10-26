<?php
/**
 * AUTO HUB - Admin Users & Customers Management
 * Administrator Account Creation and User Role Management
 */

$adminPageTitle = "Manage Users & Administrators";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();
$msg = '';
$error = '';

// Defensive server-side admin check
if (!is_admin()) {
    http_response_code(403);
    die('Access denied: Administrator privileges required.');
}

$currentAdminId = current_user_id();

// 1. Handle New Administrator Account Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin_account'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed (invalid or expired CSRF token). Please try again.';
    } else {
        $fullName        = trim($_POST['full_name'] ?? '');
        $email           = strtolower(trim($_POST['email'] ?? ''));
        $phone           = trim($_POST['phone'] ?? '');
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($fullName) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields (Full Name, Email Address, and Password).';
        } elseif (strlen($fullName) < 2 || strlen($fullName) > 100) {
            $error = 'Full name must be between 2 and 100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password and Confirm Password do not match.';
        } else {
            // Check for duplicate email
            $stmtCheck = $pdo->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
            $stmtCheck->execute([$email]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $error = 'An account with email "' . e($email) . '" already exists. Please choose a unique email address.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtInsert = $pdo->prepare("
                    INSERT INTO users (full_name, email, phone, password, role, created_at)
                    VALUES (?, ?, ?, ?, 'admin', NOW())
                ");
                $stmtInsert->execute([$fullName, $email, $phone ?: null, $hashedPassword]);

                $msg = "Administrator account for <strong>" . e($fullName) . "</strong> (" . e($email) . ") was created successfully!";
                // Reset form fields
                $_POST = [];
            }
        }
    }
}

// 2. Handle Safe Administrator Removal (with self-protection and last-admin lock)
if (isset($_GET['delete_admin'])) {
    $delId = (int)$_GET['delete_admin'];

    if ($delId <= 0) {
        $error = 'Invalid user ID specified.';
    } elseif ($delId === $currentAdminId) {
        // Prevent administrator from removing themselves
        $error = 'Security Protection: You cannot delete or remove your own administrator account.';
    } else {
        // Verify total admin count to never lock out the system
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $adminCount = (int)$stmtCount->fetchColumn();

        if ($adminCount <= 1) {
            $error = 'Safety Lockout: Cannot delete the last remaining administrator account in the system.';
        } else {
            // Confirm target is actually an administrator
            $stmtTarget = $pdo->prepare("SELECT id, full_name, email, role FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
            $stmtTarget->execute([$delId]);
            $targetAdmin = $stmtTarget->fetch();

            if (!$targetAdmin) {
                $error = 'Administrator account not found or target is not an administrator.';
            } else {
                $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
                $stmtDel->execute([$delId]);
                $msg = "Administrator <strong>" . e($targetAdmin['full_name']) . "</strong> (" . e($targetAdmin['email']) . ") was removed successfully.";
            }
        }
    }
}

// 3. Fetch all registered accounts with metrics
$stmtUsers = $pdo->query("
    SELECT u.*, COUNT(o.id) AS order_count, COALESCE(SUM(o.total_amount), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id AND o.status != 'cancelled'
    GROUP BY u.id
    ORDER BY CASE WHEN u.role = 'admin' THEN 0 ELSE 1 END, u.id ASC
");
$users = $stmtUsers->fetchAll();

$totalAccounts = count($users);
$totalAdmins = 0;
$totalCustomers = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin') {
        $totalAdmins++;
    } else {
        $totalCustomers++;
    }
}

$showAddForm = (!empty($error) && isset($_POST['add_admin_account'])) || (isset($_GET['action']) && $_GET['action'] === 'add_admin');
?>

<!-- Metric Overview Cards -->
<div class="metric-grid">
  <div class="metric-card">
    <div class="metric-icon-box" style="background: rgba(225, 29, 72, 0.15); color: var(--primary-red);">
      <i class="fas fa-users"></i>
    </div>
    <div>
      <div class="metric-value"><?php echo $totalAccounts; ?></div>
      <div class="metric-label">Total Accounts</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-icon-box" style="background: rgba(239, 68, 68, 0.18); color: #f87171;">
      <i class="fas fa-user-shield"></i>
    </div>
    <div>
      <div class="metric-value"><?php echo $totalAdmins; ?></div>
      <div class="metric-label">Administrators</div>
    </div>
  </div>

  <div class="metric-card">
    <div class="metric-icon-box" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8;">
      <i class="fas fa-user-group"></i>
    </div>
    <div>
      <div class="metric-value"><?php echo $totalCustomers; ?></div>
      <div class="metric-label">Customers</div>
    </div>
  </div>
</div>

<!-- Alert Messages -->
<?php if (!empty($msg)): ?>
  <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; padding: 0.9rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i>
    <div><?php echo $msg; ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
  <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #f87171; padding: 0.9rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
    <div><?php echo $error; ?></div>
  </div>
<?php endif; ?>

<!-- Create New Administrator Form Card (Toggleable) -->
<div id="addAdminCard" class="admin-card" style="border: 1px solid rgba(225, 29, 72, 0.4); background: #0c1322; <?php echo $showAddForm ? 'display: block;' : 'display: none;'; ?> margin-bottom: 2rem;">
  <div class="admin-card-header" style="border-bottom: 1px solid rgba(225, 29, 72, 0.25);">
    <h3 class="admin-card-title" style="font-size: 1.15rem; color: white;">
      <i class="fas fa-user-plus text-red"></i> Create New Administrator Account
    </h3>
    <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" onclick="toggleAddAdminForm(false)">
      <i class="fas fa-times"></i> Close Form
    </button>
  </div>

  <form action="users.php" method="POST" id="formAddAdmin">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="add_admin_account" value="1">

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem;">
      <div class="admin-form-group">
        <label class="admin-label">Full Name <span style="color: var(--primary-red);">*</span></label>
        <input type="text" name="full_name" class="admin-input" required placeholder="e.g. Ruwan Silva" value="<?php echo e($_POST['full_name'] ?? ''); ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Email Address <span style="color: var(--primary-red);">*</span></label>
        <input type="email" name="email" class="admin-input" required placeholder="e.g. ruwan.admin@autohub.lk" value="<?php echo e($_POST['email'] ?? ''); ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Contact Number (Phone)</label>
        <input type="text" name="phone" class="admin-input" placeholder="e.g. +94 77 123 4567" value="<?php echo e($_POST['phone'] ?? ''); ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Password <span style="color: var(--primary-red);">*</span> (Min. 6 characters)</label>
        <input type="password" name="password" class="admin-input" required minlength="6" placeholder="Enter secure admin password">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Confirm Password <span style="color: var(--primary-red);">*</span></label>
        <input type="password" name="confirm_password" class="admin-input" required minlength="6" placeholder="Repeat admin password">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Assigned Privilege Role</label>
        <div style="padding: 0.65rem 0.85rem; background: rgba(225, 29, 72, 0.12); border: 1px solid rgba(225, 29, 72, 0.3); border-radius: 8px; color: #f87171; font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fas fa-shield-halved"></i> Administrator (Full Control)
        </div>
      </div>
    </div>

    <div style="display: flex; gap: 1rem; align-items: center; margin-top: 1.25rem; border-top: 1px solid var(--admin-border); padding-top: 1.25rem;">
      <button type="submit" class="admin-btn admin-btn-primary">
        <i class="fas fa-user-shield"></i> Create Administrator Account
      </button>
      <button type="button" class="admin-btn admin-btn-secondary" onclick="toggleAddAdminForm(false)">
        Cancel
      </button>
    </div>
  </form>
</div>

<!-- Main Registered Accounts Table Card -->
<div class="admin-card">
  <div class="admin-card-header" style="flex-wrap: wrap; gap: 1rem;">
    <h2 class="admin-card-title"><i class="fas fa-users text-red"></i> Registered Accounts (<?php echo count($users); ?>)</h2>
    
    <button type="button" class="admin-btn admin-btn-primary" id="btnToggleAddAdmin" onclick="toggleAddAdminForm()">
      <i class="fas fa-user-shield"></i> Add New Admin
    </button>
  </div>

  <div style="overflow-x: auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width: 50px;">ID</th>
          <th>User Details</th>
          <th>Phone</th>
          <th>Role</th>
          <th>Account Status</th>
          <th>Activity / Orders</th>
          <th style="text-align: right;">Joined Date</th>
          <th style="text-align: center; width: 110px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <?php 
            $isAdmin = ($u['role'] === 'admin');
            $isSelf = ($u['id'] == $currentAdminId);
          ?>
          <tr style="<?php echo $isAdmin ? 'background: rgba(225, 29, 72, 0.03);' : ''; ?>">
            <td><strong style="color: var(--admin-text-muted);">#<?php echo $u['id']; ?></strong></td>
            <td>
              <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="width: 38px; height: 38px; border-radius: 50%; background: <?php echo $isAdmin ? 'rgba(225, 29, 72, 0.2)' : '#1e293b'; ?>; border: 1px solid <?php echo $isAdmin ? 'var(--primary-red)' : '#334155'; ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $isAdmin ? '#f87171' : '#94a3b8'; ?>; font-weight: bold;">
                  <?php if ($isAdmin): ?>
                    <i class="fas fa-shield-alt" style="font-size: 0.9rem;"></i>
                  <?php else: ?>
                    <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                  <?php endif; ?>
                </div>
                <div>
                  <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <strong style="color: white;"><?php echo e($u['full_name']); ?></strong>
                    <?php if ($isSelf): ?>
                      <span style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.1rem 0.4rem; border-radius: 10px; font-size: 0.7rem; font-weight: 700;">
                        <i class="fas fa-crown"></i> You
                      </span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size: 0.78rem; color: var(--admin-text-muted);"><?php echo e($u['email']); ?></div>
                </div>
              </div>
            </td>
            <td><span style="color: #cbd5e1;"><?php echo e($u['phone'] ?: 'N/A'); ?></span></td>
            <td>
              <?php if ($isAdmin): ?>
                <span class="admin-badge admin-badge-danger">
                  <i class="fas fa-shield-halved"></i> Admin
                </span>
              <?php else: ?>
                <span class="admin-badge admin-badge-info">
                  <i class="fas fa-user"></i> Customer
                </span>
              <?php endif; ?>
            </td>
            <td>
              <span class="admin-badge admin-badge-success">
                <i class="fas fa-circle-check"></i> Active
              </span>
            </td>
            <td>
              <?php if ($isAdmin): ?>
                <span style="color: #94a3b8; font-size: 0.82rem;">
                  <i class="fas fa-lock-open text-red"></i> Full System Access
                </span>
              <?php else: ?>
                <div><strong><?php echo (int)$u['order_count']; ?></strong> orders</div>
                <div style="font-size: 0.78rem; color: var(--primary-red); font-weight: 600;"><?php echo format_price($u['total_spent']); ?></div>
              <?php endif; ?>
            </td>
            <td style="text-align: right; color: var(--admin-text-muted); font-size: 0.8rem;">
              <?php echo date('d M Y', strtotime($u['created_at'])); ?>
            </td>
            <td style="text-align: center;">
              <?php if ($isAdmin): ?>
                <?php if ($isSelf): ?>
                  <span style="font-size: 0.75rem; color: #64748b; font-style: italic;">Current Session</span>
                <?php else: ?>
                  <a href="users.php?delete_admin=<?php echo (int)$u['id']; ?>" 
                     class="admin-btn admin-btn-danger admin-btn-sm" 
                     onclick="return confirm('Are you sure you want to revoke and delete administrator access for <?php echo e($u['full_name']); ?>?');"
                     title="Delete Administrator">
                    <i class="fas fa-trash-alt"></i> Revoke
                  </a>
                <?php endif; ?>
              <?php else: ?>
                <span style="font-size: 0.75rem; color: #475569;">Customer</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleAddAdminForm(forceState) {
  const formCard = document.getElementById('addAdminCard');
  if (!formCard) return;

  if (forceState !== undefined) {
    formCard.style.display = forceState ? 'block' : 'none';
  } else {
    formCard.style.display = (formCard.style.display === 'none' || formCard.style.display === '') ? 'block' : 'none';
  }

  if (formCard.style.display === 'block') {
    formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    const firstInput = formCard.querySelector('input[name="full_name"]');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 150);
    }
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
