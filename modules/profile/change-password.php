<?php
/**
 * Change Password Action
 * Procurement Management CMS
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/profile/index.php');
}

// 1. Verify CSRF
if (!validateCsrfToken()) {
    setFlash('error', 'Invalid security token or session expired. Please try again.');
    redirect('modules/profile/index.php');
}

$user = currentUser(true);
$userId = currentUserId();

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

$errors = [];

// 2. Validate Inputs
if (empty($currentPassword)) {
    $errors[] = 'Please enter your current password.';
}

if (empty($newPassword)) {
    $errors[] = 'Please enter a new password.';
} elseif (strlen($newPassword) < 8) {
    $errors[] = 'The new password must be at least 8 characters long.';
}

if ($newPassword !== $newPasswordConfirm) {
    $errors[] = 'The new password confirmation does not match.';
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        setFlash('error', $error);
    }
    redirect('modules/profile/index.php');
}

// 3. Verify Current Password
if (!password_verify($currentPassword, $user['password'])) {
    setFlash('error', 'The current password you entered is incorrect.');
    redirect('modules/profile/index.php');
}

// 4. Update Password in Database
try {
    $db = getDb();
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        ':password' => $newHash,
        ':id'       => $userId
    ]);

    logActivity($userId, 'Password Changed', 'User changed their account password.');
    setFlash('success', 'Your password has been changed successfully.');
} catch (Exception $e) {
    error_log('Change Password Error: ' . $e->getMessage());
    setFlash('error', 'Failed to update password. Please try again.');
}

redirect('modules/profile/index.php');
