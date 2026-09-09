<?php
/**
 * Update User Action (Administrator Only)
 * Procurement Management CMS - Phase 06
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireRole('administrator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/users/index.php');
}

// 1. Verify CSRF
if (!validateCsrfToken()) {
    setFlash('error', 'Invalid security token or session expired. Please try again.');
    redirect('modules/users/index.php');
}

$db = getDb();
$currentAdminId = currentUserId();

$targetUserId    = (int)($_POST['id'] ?? 0);
$fullName        = sanitizeInput($_POST['full_name'] ?? '');
$username        = strtolower(sanitizeInput($_POST['username'] ?? ''));
$email           = strtolower(sanitizeInput($_POST['email'] ?? ''));
$roleId          = (int)($_POST['role_id'] ?? 0);
$status          = sanitizeInput($_POST['status'] ?? 'active');
$password        = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if ($targetUserId <= 0) {
    setFlash('error', 'Invalid user account selected.');
    redirect('modules/users/index.php');
}

// Fetch Existing User
$stmt = $db->prepare("
    SELECT u.*, ur.role_id, r.slug AS role_slug
    FROM users u
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    WHERE u.id = :id AND u.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([':id' => $targetUserId]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', 'The user account does not exist or has been deleted.');
    redirect('modules/users/index.php');
}

$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full legal name is required.';
}

if (empty($username) || !preg_match('/^[a-zA-Z0-9_\-\.]{3,100}$/', $username)) {
    $errors[] = 'Username must be 3-100 alphanumeric characters.';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

if ($roleId <= 0) {
    $errors[] = 'Please select a valid system role.';
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

// Check Last Active Admin Protection
$adminRole = $db->query("SELECT id FROM roles WHERE slug = 'administrator' LIMIT 1")->fetch();
$adminRoleId = $adminRole ? (int)$adminRole['id'] : 1;

if ($user['role_slug'] === 'administrator' && $user['status'] === 'active') {
    $activeAdminCount = (int)$db->query("
        SELECT COUNT(DISTINCT u.id)
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        JOIN roles r ON ur.role_id = r.id
        WHERE r.slug = 'administrator' AND u.status = 'active' AND u.deleted_at IS NULL
    ")->fetchColumn();

    if ($activeAdminCount <= 1) {
        // Must stay Administrator and Active
        if ($roleId !== $adminRoleId) {
            $errors[] = 'Cannot remove the Administrator role from the only active administrator in the system.';
        }
        if ($status !== 'active') {
            $errors[] = 'Cannot deactivate the only active administrator in the system.';
        }
    }
}

// Validate Uniqueness against other records
if (empty($errors)) {
    try {
        $chkUser = $db->prepare("SELECT id FROM users WHERE username = :u AND id != :id AND deleted_at IS NULL LIMIT 1");
        $chkUser->execute([':u' => $username, ':id' => $targetUserId]);
        if ($chkUser->fetch()) {
            $errors[] = "Username '@{$username}' is already taken by another account.";
        }

        $chkEmail = $db->prepare("SELECT id FROM users WHERE email = :e AND id != :id AND deleted_at IS NULL LIMIT 1");
        $chkEmail->execute([':e' => $email, ':id' => $targetUserId]);
        if ($chkEmail->fetch()) {
            $errors[] = "Email address '{$email}' is already registered to another account.";
        }
    } catch (Exception $e) {
        error_log('User Edit Validation Error: ' . $e->getMessage());
        $errors[] = 'Database error occurred during uniqueness verification.';
    }
}

// Password change if provided
$updatePassword = false;
if (!empty($password)) {
    if (strlen($password) < 6) {
        $errors[] = 'New password must be at least 6 characters.';
    }
    if ($password !== $passwordConfirm) {
        $errors[] = 'New password and confirmation do not match.';
    }
    $updatePassword = true;
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    redirect('modules/users/edit.php?id=' . $targetUserId);
}

// Execute Updates in Transaction
try {
    $db->beginTransaction();

    if ($updatePassword) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $upStmt = $db->prepare("
            UPDATE users
            SET full_name = :full_name, username = :username, email = :email, password = :password, status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $upStmt->execute([
            ':full_name' => $fullName,
            ':username'  => $username,
            ':email'     => $email,
            ':password'  => $hashed,
            ':status'    => $status,
            ':id'        => $targetUserId
        ]);
    } else {
        $upStmt = $db->prepare("
            UPDATE users
            SET full_name = :full_name, username = :username, email = :email, status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $upStmt->execute([
            ':full_name' => $fullName,
            ':username'  => $username,
            ':email'     => $email,
            ':status'    => $status,
            ':id'        => $targetUserId
        ]);
    }

    // Update Role Mapping
    $db->prepare("DELETE FROM user_roles WHERE user_id = :uid")->execute([':uid' => $targetUserId]);
    $roleStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:uid, :rid, NOW())");
    $roleStmt->execute([':uid' => $targetUserId, ':rid' => $roleId]);

    $db->commit();

    logActivity($currentAdminId, 'User Updated', "Admin updated account details for User #{$targetUserId} (@{$username}).");
    setFlash('success', "User account for '{$fullName}' updated successfully.");
    redirect('modules/users/index.php');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Update User Error: ' . $e->getMessage());
    setFlash('error', 'Failed to update user account due to a database error.');
    redirect('modules/users/edit.php?id=' . $targetUserId);
}
