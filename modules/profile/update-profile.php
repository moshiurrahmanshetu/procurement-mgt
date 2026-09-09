<?php
/**
 * Update Profile Information Action
 * Procurement Management CMS
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/profile/index.php');
}

// 1. Validate CSRF Token
if (!validateCsrfToken()) {
    setFlash('error', 'Invalid security token or session expired. Please try again.');
    redirect('modules/profile/index.php');
}

$user = currentUser();
$userId = currentUserId();

$fullName = sanitizeInput($_POST['full_name'] ?? '');
$username = sanitizeInput($_POST['username'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');

$errors = [];

// 2. Validate Inputs
if (empty($fullName)) {
    $errors[] = 'Full name is required.';
} elseif (strlen($fullName) > 150) {
    $errors[] = 'Full name cannot exceed 150 characters.';
}

if (empty($username)) {
    $errors[] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
    $errors[] = 'Username must be 3-50 characters and can only contain letters, numbers, dots, hyphens, and underscores.';
}

if (empty($email)) {
    $errors[] = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        setFlash('error', $error);
    }
    redirect('modules/profile/index.php');
}

// 3. Check Unique Constraints (excluding current user)
try {
    $db = getDb();

    // Check username uniqueness
    $stmtUsername = $db->prepare("
        SELECT id FROM users 
        WHERE username = :username AND id != :id AND deleted_at IS NULL 
        LIMIT 1
    ");
    $stmtUsername->execute([':username' => $username, ':id' => $userId]);
    if ($stmtUsername->fetch()) {
        setFlash('error', 'The username "' . $username . '" is already taken by another account.');
        redirect('modules/profile/index.php');
    }

    // Check email uniqueness
    $stmtEmail = $db->prepare("
        SELECT id FROM users 
        WHERE email = :email AND id != :id AND deleted_at IS NULL 
        LIMIT 1
    ");
    $stmtEmail->execute([':email' => $email, ':id' => $userId]);
    if ($stmtEmail->fetch()) {
        setFlash('error', 'The email address "' . $email . '" is already registered to another account.');
        redirect('modules/profile/index.php');
    }

    // 4. Update Database
    $updateStmt = $db->prepare("
        UPDATE users 
        SET full_name = :full_name, username = :username, email = :email, updated_at = NOW() 
        WHERE id = :id
    ");
    $updateStmt->execute([
        ':full_name' => $fullName,
        ':username'  => $username,
        ':email'     => $email,
        ':id'        => $userId
    ]);

    logActivity($userId, 'Profile Updated', 'User updated profile details (name, username, email).');
    setFlash('success', 'Your profile details have been updated successfully.');

} catch (Exception $e) {
    error_log('Update Profile Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while updating your profile. Please try again.');
}

redirect('modules/profile/index.php');
