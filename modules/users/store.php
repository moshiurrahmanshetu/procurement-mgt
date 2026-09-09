<?php
/**
 * Store New User Action (Administrator Only)
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
    redirect('modules/users/create.php');
}

$db = getDb();
$currentAdminId = currentUserId();

$fullName        = sanitizeInput($_POST['full_name'] ?? '');
$username        = strtolower(sanitizeInput($_POST['username'] ?? ''));
$email           = strtolower(sanitizeInput($_POST['email'] ?? ''));
$roleId          = (int)($_POST['role_id'] ?? 0);
$status          = sanitizeInput($_POST['status'] ?? 'active');
$password        = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

$errors = [];

if (empty($fullName)) {
    $errors[] = 'Full name is required.';
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

if (empty($password) || strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters.';
}

if ($password !== $passwordConfirm) {
    $errors[] = 'Passwords do not match.';
}

// Check for Unique Username and Email
if (empty($errors)) {
    try {
        $chkUser = $db->prepare("SELECT id FROM users WHERE username = :u AND deleted_at IS NULL LIMIT 1");
        $chkUser->execute([':u' => $username]);
        if ($chkUser->fetch()) {
            $errors[] = "Username '@{$username}' is already taken.";
        }

        $chkEmail = $db->prepare("SELECT id FROM users WHERE email = :e AND deleted_at IS NULL LIMIT 1");
        $chkEmail->execute([':e' => $email]);
        if ($chkEmail->fetch()) {
            $errors[] = "Email address '{$email}' is already registered.";
        }
    } catch (Exception $e) {
        error_log('User validation query error: ' . $e->getMessage());
        $errors[] = 'Database query failed during uniqueness validation.';
    }
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    redirect('modules/users/create.php');
}

// Insert New User Record in Transaction
try {
    $db->beginTransaction();

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare("
        INSERT INTO users (full_name, username, email, password, status, created_at, updated_at)
        VALUES (:full_name, :username, :email, :password, :status, NOW(), NOW())
    ");
    $stmt->execute([
        ':full_name' => $fullName,
        ':username'  => $username,
        ':email'     => $email,
        ':password'  => $hashedPassword,
        ':status'    => $status
    ]);

    $newUserId = (int)$db->lastInsertId();

    // Assign Role in user_roles
    $roleStmt = $db->prepare("
        INSERT INTO user_roles (user_id, role_id, created_at)
        VALUES (:user_id, :role_id, NOW())
    ");
    $roleStmt->execute([
        ':user_id' => $newUserId,
        ':role_id' => $roleId
    ]);

    $db->commit();

    logActivity($currentAdminId, 'User Created', "Admin created new account for @{$username} ({$fullName}) with User ID #{$newUserId}.");
    setFlash('success', "User account for '{$fullName}' created successfully.");
    redirect('modules/users/index.php');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Store User Error: ' . $e->getMessage());
    setFlash('error', 'Failed to create user account due to a database error.');
    redirect('modules/users/create.php');
}
