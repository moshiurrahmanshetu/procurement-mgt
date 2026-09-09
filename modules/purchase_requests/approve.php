<?php
/**
 * Approve Purchase Request Action
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

// 2. Enforce Role Check (Manager or Admin only)
if (!$isAdmin && !$isManager) {
    setFlash('error', 'Access Denied: Only Managers or Administrators can approve purchase requests.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

$db = getDb();
$comments = sanitizeInput($_POST['comments'] ?? '');

// 3. Fetch Request Record
$pr = null;
try {
    $stmt = $db->prepare("SELECT * FROM purchase_requests WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Approve PR Query Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found.');
    redirect('modules/purchase_requests/index.php');
}

// 4. Validate Status Transition (pending_approval -> approved)
if ($pr['status'] !== 'pending_approval') {
    setFlash('error', 'Only requisitions with pending approval status can be approved. Current status: ' . ucfirst(str_replace('_', ' ', $pr['status'])));
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 5. Execute Approval in Transaction
try {
    $db->beginTransaction();

    $updateStmt = $db->prepare("
        UPDATE purchase_requests SET
            status = 'approved',
            approved_by = :approved_by,
            approved_at = NOW(),
            rejection_reason = NULL,
            updated_at = NOW()
        WHERE id = :id
    ");
    $updateStmt->execute([
        ':approved_by' => $userId,
        ':id'          => $id
    ]);

    $histComment = !empty($comments) ? $comments : 'Purchase requisition approved.';
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
        ':action'              => 'Approved',
        ':old_status'          => 'pending_approval',
        ':new_status'          => 'approved',
        ':comments'            => $histComment
    ]);

    logActivity($userId, 'PR Approved', 'Approved purchase request #' . $pr['request_no']);
    $db->commit();

    setFlash('success', 'Purchase request ' . $pr['request_no'] . ' has been approved successfully.');
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Approve PR Transaction Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while approving the requisition.');
}

redirect('modules/purchase_requests/view.php?id=' . $id);
