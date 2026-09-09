<?php
/**
 * Delete Draft Purchase Request Action
 * Procurement Management CMS - Phase 02
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/purchase_requests/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid purchase request ID.');
    redirect('modules/purchase_requests/index.php');
}

// 1. Verify CSRF
if (!validateCsrfToken()) {
    setFlash('error', 'Invalid security token or session expired.');
    redirect('modules/purchase_requests/index.php');
}

$user = currentUser();
$userId = currentUserId();
$isAdmin = userHasRole('administrator');
$db = getDb();

// 2. Fetch Request Record
$pr = null;
try {
    $stmt = $db->prepare("SELECT * FROM purchase_requests WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Delete PR Query Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found.');
    redirect('modules/purchase_requests/index.php');
}

// 3. Ownership / Authorization Check
$isOwner = ((int)$pr['requested_by'] === $userId);
if (!$isOwner && !$isAdmin) {
    setFlash('error', 'Access Denied: You cannot delete another user\'s requisition.');
    redirect('modules/purchase_requests/index.php');
}

// 4. Enforce Draft Status
if ($pr['status'] !== 'draft') {
    setFlash('error', 'Cannot delete submitted or processed purchase requests. Only draft requisitions can be deleted.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 5. Execute Delete
try {
    $delStmt = $db->prepare("DELETE FROM purchase_requests WHERE id = :id");
    $delStmt->execute([':id' => $id]);

    logActivity($userId, 'PR Draft Deleted', 'Deleted draft purchase request #' . $pr['request_no']);
    setFlash('success', 'Draft purchase request ' . $pr['request_no'] . ' has been deleted.');
} catch (Exception $e) {
    error_log('Delete PR Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while deleting the requisition draft.');
}

redirect('modules/purchase_requests/index.php');
