<?php
/**
 * Soft Delete User Action (Administrator Only)
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

// Prevent Self-Deletion
if ($targetUserId === $currentAdminId) {
    setFlash('error', 'You cannot delete your own active administrator account.');
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
        setFlash('error', 'The user account does not exist or was already deleted.');
        redirect('modules/users/index.php');
    }

    // Last Active Administrator Protection
    if ($user['role_slug'] === 'administrator' && $user['status'] === 'active') {
        $activeAdminCount = (int)$db->query("
            SELECT COUNT(DISTINCT u.id)
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.slug = 'administrator' AND u.status = 'active' AND u.deleted_at IS NULL
        ")->fetchColumn();

        if ($activeAdminCount <= 1) {
            setFlash('error', 'Cannot delete the only remaining active administrator in the system.');
            redirect('modules/users/index.php');
        }
    }

    // Execute Soft Delete
    $delStmt = $db->prepare("UPDATE users SET deleted_at = NOW(), status = 'inactive' WHERE id = :id");
    $delStmt->execute([':id' => $targetUserId]);

    logActivity($currentAdminId, 'User Deleted', "Admin soft-deleted user account #{$targetUserId} (@{$user['username']}).");
    setFlash('success', "User account for '{$user['full_name']}' was soft-deleted successfully.");

} catch (Exception $e) {
    error_log('Delete User Error: ' . $e->getMessage());
    setFlash('error', 'Database error while deleting user account.');
}

redirect('modules/users/index.php');
