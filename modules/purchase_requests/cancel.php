<?php
/**
 * Cancel Purchase Request Action
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
$comments = sanitizeInput($_POST['comments'] ?? '');

// 2. Fetch Request Record
$pr = null;
try {
    $stmt = $db->prepare("SELECT * FROM purchase_requests WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Cancel PR Query Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found.');
    redirect('modules/purchase_requests/index.php');
}

// 3. Ownership / Authorization Check
$isOwner = ((int)$pr['requested_by'] === $userId);
if (!$isOwner && !$isAdmin) {
    setFlash('error', 'Access Denied: You cannot cancel another user\'s requisition.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 4. Validate Status Transition (draft or pending_approval -> cancelled)
if (!in_array($pr['status'], ['draft', 'pending_approval'], true)) {
    setFlash('error', 'Cannot cancel a purchase request that is already ' . ucfirst(str_replace('_', ' ', $pr['status'])) . '.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 5. Execute Cancellation in Transaction
try {
    $db->beginTransaction();

    $oldStatus = $pr['status'];
    $updateStmt = $db->prepare("UPDATE purchase_requests SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
    $updateStmt->execute([':id' => $id]);

    $histComment = !empty($comments) ? $comments : 'Purchase requisition cancelled.';
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
        ':action'              => 'Cancelled',
        ':old_status'          => $oldStatus,
        ':new_status'          => 'cancelled',
        ':comments'            => $histComment
    ]);

    logActivity($userId, 'PR Cancelled', 'Cancelled purchase request #' . $pr['request_no']);
    $db->commit();

    setFlash('info', 'Purchase request ' . $pr['request_no'] . ' has been cancelled.');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Cancel PR Transaction Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while cancelling the requisition.');
}

redirect('modules/purchase_requests/view.php?id=' . $id);
