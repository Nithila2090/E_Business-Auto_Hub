<?php
/**
 * AUTO HUB - Admin Header Component
 */
require_once __DIR__ . '/../../config/helpers.php';

require_admin('../admin/login.php');

$currentPage = basename($_SERVER['PHP_SELF']);
$adminUser = current_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo !empty($adminPageTitle) ? e($adminPageTitle) . ' | ' : ''; ?>AUTO HUB Admin Control Center</title>
  
  <link rel="icon" type="image/x-icon" href="../images/logo/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>

<div class="admin-wrapper">
  
  <!-- Sidebar -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content Wrapper -->
  <div class="admin-main">
    
    <!-- Top Nav Header -->
    <header class="admin-header">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; margin: 0; color: white;">
          <?php echo !empty($adminPageTitle) ? e($adminPageTitle) : 'Dashboard Overview'; ?>
        </h1>
      </div>

      <div style="display: flex; align-items: center; gap: 1.25rem;">
        <a href="../index.php" target="_blank" class="admin-btn admin-btn-secondary admin-btn-sm">
          <i class="fas fa-external-link-alt"></i> View Live Store
        </a>

        <div style="display: flex; align-items: center; gap: 0.6rem; color: #f8fafc; font-size: 0.88rem;">
          <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--primary-red); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold;">
            <i class="fas fa-user-shield"></i>
          </div>
          <div>
            <div style="font-weight: 700; line-height: 1.1;"><?php echo e($adminUser); ?></div>
            <div style="font-size: 0.72rem; color: #94a3b8;">Administrator</div>
          </div>
        </div>

        <a href="../logout.php" class="admin-btn admin-btn-danger admin-btn-sm" title="Log Out">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </header>

    <!-- Main View Body -->
    <div class="admin-content">
