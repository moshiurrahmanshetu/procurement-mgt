<?php
/**
 * Submit Draft Purchase Request Action
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
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

$user = currentUser();
$userId = currentUserId();
$isAdmin = userHasRole('administrator');
$db = getDb();

// 2. Fetch Request
$pr = null;
try {
    $stmt = $db->prepare("SELECT * FROM purchase_requests WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Submit PR Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found.');
    redirect('modules/purchase_requests/index.php');
}

// 3. Authorization Check
$isOwner = ((int)$pr['requested_by'] === $userId);
if (!$isOwner && !$isAdmin) {
    setFlash('error', 'Access Denied: You cannot submit another user\'s requisition.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 4. Validate Status Transition (draft -> pending_approval)
if ($pr['status'] !== 'draft') {
    setFlash('error', 'Only draft purchase requests can be submitted.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 5. Update Status
try {
    $db->beginTransaction();

    $updateStmt = $db->prepare("UPDATE purchase_requests SET status = 'pending_approval', updated_at = NOW() WHERE id = :id");
    $updateStmt->execute([':id' => $id]);

    $histStmt = $db->prepare("
        INSERT INTO purchase_request_history (
            purchase_request_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :purchase_request_id, :user_id, :action, :old_status, :new_status, :comments, NOW()
        )
    ");
    $histStmt->execute([
        ':purchase_request_id' => $id,
        ':user_id'             => $userId,
        ':action'              => 'Submitted',
        ':old_status'          => 'draft',
        ':new_status'          => 'pending_approval',
        ':comments'            => 'Requisition submitted for management approval.'
    ]);

    logActivity($userId, 'PR Submitted', 'Submitted purchase request #' . $pr['request_no'] . ' for approval.');
    $db->commit();

    setFlash('success', 'Purchase request ' . $pr['request_no'] . ' has been submitted for approval!');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Submit PR Transaction Error: ' . $e->getMessage());
    setFlash('error', 'Failed to submit requisition.');
}

redirect('modules/purchase_requests/view.php?id=' . $id);
