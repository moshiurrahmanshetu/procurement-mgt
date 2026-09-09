<?php
/**
 * Reject Purchase Request Action
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
$isManager = userHasRole('manager');

// 2. Enforce Role Check
if (!$isAdmin && !$isManager) {
    setFlash('error', 'Access Denied: Only Managers or Administrators can reject purchase requests.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

$rejectionReason = sanitizeInput($_POST['rejection_reason'] ?? '');
if (empty($rejectionReason)) {
    setFlash('error', 'A mandatory rejection reason is required to reject a requisition.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

$db = getDb();

// 3. Fetch Request Record
$pr = null;
try {
    $stmt = $db->prepare("SELECT * FROM purchase_requests WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Reject PR Query Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found.');
    redirect('modules/purchase_requests/index.php');
}

// 4. Validate Status Transition (pending_approval -> rejected)
if ($pr['status'] !== 'pending_approval') {
    setFlash('error', 'Only pending approval requests can be rejected.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 5. Execute Rejection in Transaction
try {
    $db->beginTransaction();

    $updateStmt = $db->prepare("
        UPDATE purchase_requests SET
            status = 'rejected',
            approved_by = :approved_by,
            rejection_reason = :rejection_reason,
            updated_at = NOW()
        WHERE id = :id
    ");
    $updateStmt->execute([
        ':approved_by'       => $userId,
        ':rejection_reason'  => $rejectionReason,
        ':id'                => $id
    ]);

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
        ':action'              => 'Rejected',
        ':old_status'          => 'pending_approval',
        ':new_status'          => 'rejected',
        ':comments'            => 'Rejection Reason: ' . $rejectionReason
    ]);

    logActivity($userId, 'PR Rejected', 'Rejected purchase request #' . $pr['request_no'] . ' with reason.');
    $db->commit();

    setFlash('warning', 'Purchase request ' . $pr['request_no'] . ' has been rejected.');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Reject PR Transaction Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while rejecting the requisition.');
}

redirect('modules/purchase_requests/view.php?id=' . $id);
