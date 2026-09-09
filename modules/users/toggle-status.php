<?php
/**
 * Toggle User Active Status (Administrator Only)
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
$targetUserId = (int)($_POST['id'] ?? 0);

if ($targetUserId <= 0) {
    setFlash('error', 'Invalid user account selected.');
    redirect('modules/users/index.php');
}

try {
    $stmt = $db->prepare("
        SELECT u.*, r.slug AS role_slug
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

    $currentStatus = $user['status'];
    $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

    // Last Active Administrator Protection
    if ($currentStatus === 'active' && $user['role_slug'] === 'administrator') {
        $activeAdminCount = (int)$db->query("
            SELECT COUNT(DISTINCT u.id)
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.slug = 'administrator' AND u.status = 'active' AND u.deleted_at IS NULL
        ")->fetchColumn();

        if ($activeAdminCount <= 1) {
            setFlash('error', 'Cannot deactivate the last active administrator account.');
            redirect('modules/users/index.php');
        }
    }

    $upStmt = $db->prepare("UPDATE users SET status = :st, updated_at = NOW() WHERE id = :id");
    $upStmt->execute([':st' => $newStatus, ':id' => $targetUserId]);

    logActivity($currentAdminId, 'User Status Changed', "Admin changed status for User #{$targetUserId} (@{$user['username']}) to {$newStatus}.");
    setFlash('success', "Account for '{$user['full_name']}' is now {$newStatus}.");

} catch (Exception $e) {
    error_log('Toggle User Status Error: ' . $e->getMessage());
    setFlash('error', 'Database error while updating account status.');
}

redirect('modules/users/index.php');
