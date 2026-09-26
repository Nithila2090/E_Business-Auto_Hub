<?php
/**
 * AUTO HUB - Customer Profile API
 * Endpoints for viewing profile, updating details, uploading avatar, and changing password
 */

require_once __DIR__ . '/../config/helpers.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Ensure user is authenticated
if (!is_logged_in()) {
    json_response([
        'success' => false,
        'message' => 'Authentication required. Please log in to manage your profile.'
    ], 401);
}

$pdo = get_db();
$userId = current_user_id();
$method = $_SERVER['REQUEST_METHOD'];

// Fetch current user from database
$stmtUser = $pdo->prepare("SELECT id, full_name, email, phone, address, city, postal_code, province, role, profile_image, created_at FROM users WHERE id = ? LIMIT 1");
$stmtUser->execute([$userId]);
$currentUser = $stmtUser->fetch();

if (!$currentUser) {
    json_response([
        'success' => false,
        'message' => 'User account not found.'
    ], 404);
}

$input = [];
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);
    $input = is_array($json) ? array_merge($_POST, $json) : $_POST;
} else {
    $input = $_GET;
}

$action = $input['action'] ?? ($method === 'POST' ? 'update_profile' : 'get_profile');

try {
    // 1. Get Profile Details
    if ($action === 'get_profile' || $action === 'get') {
        // Fetch order stats for current user
        $stmtOrders = $pdo->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_spent FROM orders WHERE user_id = ?");
        $stmtOrders->execute([$userId]);
        $orderStats = $stmtOrders->fetch();

        json_response([
            'success' => true,
            'user' => [
                'id' => (int)$currentUser['id'],
                'full_name' => $currentUser['full_name'],
                'email' => $currentUser['email'],
                'phone' => $currentUser['phone'] ?? '',
                'address' => $currentUser['address'] ?? '',
                'city' => $currentUser['city'] ?? '',
                'postal_code' => $currentUser['postal_code'] ?? '',
                'province' => $currentUser['province'] ?? '',
                'role' => $currentUser['role'],
                'profile_image' => $currentUser['profile_image'] ?? null,
                'created_at' => $currentUser['created_at'],
                'member_since' => date('F j, Y', strtotime($currentUser['created_at'])),
                'stats' => [
                    'total_orders' => (int)($orderStats['total_orders'] ?? 0),
                    'total_spent' => (float)($orderStats['total_spent'] ?? 0),
                    'wishlist_count' => get_wishlist_count(),
                    'cart_count' => get_cart_count()
                ]
            ]
        ]);
    }

    // 2. Update Profile Details
    if ($action === 'update_profile') {
        // Verify CSRF if token provided in request headers or body
        $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($token !== null && !verify_csrf_token($token)) {
            json_response(['success' => false, 'message' => 'Invalid or expired security token. Please refresh and try again.'], 403);
        }

        $fullName = trim($input['full_name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $address = trim($input['address'] ?? '');
        $city = trim($input['city'] ?? '');
        $postalCode = trim($input['postal_code'] ?? '');
        $province = trim($input['province'] ?? '');

        if (empty($fullName)) {
            json_response(['success' => false, 'message' => 'Full name cannot be empty.'], 400);
        }

        if (strlen($fullName) < 2 || strlen($fullName) > 100) {
            json_response(['success' => false, 'message' => 'Full name must be between 2 and 100 characters.'], 400);
        }

        $profileImage = $currentUser['profile_image'];

        // Handle profile photo upload if provided
        if (!empty($_FILES['profile_image']['name'])) {
            $uploadRes = handle_image_upload($_FILES['profile_image'], 'profiles');
            if ($uploadRes['success']) {
                $profileImage = $uploadRes['file_path'];
            } else {
                json_response(['success' => false, 'message' => $uploadRes['error']], 400);
            }
        }

        // Execute update on current authenticated user record
        $stmtUpdate = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, phone = ?, address = ?, city = ?, postal_code = ?, province = ?, profile_image = ? 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$fullName, $phone, $address, $city, $postalCode, $province, $profileImage, $userId]);

        // Update active session name
        $_SESSION['user_name'] = $fullName;

        // Fetch refreshed user record
        $stmtUser->execute([$userId]);
        $updatedUser = $stmtUser->fetch();

        json_response([
            'success' => true,
            'message' => 'Profile updated successfully!',
            'user' => [
                'id' => (int)$updatedUser['id'],
                'full_name' => $updatedUser['full_name'],
                'email' => $updatedUser['email'],
                'phone' => $updatedUser['phone'] ?? '',
                'address' => $updatedUser['address'] ?? '',
                'city' => $updatedUser['city'] ?? '',
                'postal_code' => $updatedUser['postal_code'] ?? '',
                'province' => $updatedUser['province'] ?? '',
                'role' => $updatedUser['role'],
                'profile_image' => $updatedUser['profile_image'],
                'created_at' => $updatedUser['created_at']
            ]
        ]);
    }

    // 3. Change Password
    if ($action === 'change_password') {
        // Verify CSRF if token provided
        $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($token !== null && !verify_csrf_token($token)) {
            json_response(['success' => false, 'message' => 'Invalid or expired security token.'], 403);
        }

        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            json_response(['success' => false, 'message' => 'Please fill in all password fields.'], 400);
        }

        // Fetch stored password hash
        $stmtPw = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmtPw->execute([$userId]);
        $storedHash = $stmtPw->fetchColumn();

        if (!$storedHash || !password_verify($currentPassword, $storedHash)) {
            json_response(['success' => false, 'message' => 'Current password entered is incorrect.'], 400);
        }

        if (strlen($newPassword) < 6) {
            json_response(['success' => false, 'message' => 'New password must be at least 6 characters long.'], 400);
        }

        if ($newPassword !== $confirmPassword) {
            json_response(['success' => false, 'message' => 'New passwords do not match.'], 400);
        }

        if ($currentPassword === $newPassword) {
            json_response(['success' => false, 'message' => 'New password cannot be the same as your current password.'], 400);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmtUpdatePw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmtUpdatePw->execute([$newHash, $userId]);

        json_response([
            'success' => true,
            'message' => 'Your password has been changed successfully!'
        ]);
    }

    json_response(['success' => false, 'message' => 'Invalid action requested.'], 400);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
}
