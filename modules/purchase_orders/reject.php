<?php
/**
 * Reject Purchase Order
 * Procurement Management CMS - Phase 04
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Check: Only Managers & Administrators can reject POs
$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
if (!$isAdmin && !$isManager) {
    setFlash('error', 'Access Denied: Only Managers and Administrators can reject Purchase Orders.');
    redirect('modules/purchase_orders/index.php');
}

// 2. Request Method & CSRF Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/purchase_orders/index.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid security token.');
    redirect('modules/purchase_orders/index.php');
}

$id = (int)($_POST['id'] ?? 0);
$rejectionReason = trim($_POST['rejection_reason'] ?? '');

if ($id <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
}

if (empty($rejectionReason)) {
    setFlash('error', 'Rejection reason is required.');
    redirect('modules/purchase_orders/view.php?id=' . $id);
}

$db = getDb();
$userId = currentUserId();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = :id AND deleted_at IS NULL FOR UPDATE");
    $stmt->execute([':id' => $id]);
    $po = $stmt->fetch();

    if (!$po) {
        $db->rollBack();
        setFlash('error', 'Purchase Order not found.');
        redirect('modules/purchase_orders/index.php');
    }

    if ($po['status'] !== 'pending_approval') {
        $db->rollBack();
        setFlash('error', 'Only Purchase Orders pending approval can be rejected.');
        redirect('modules/purchase_orders/view.php?id=' . $id);
    }

    // Update status to cancelled with rejection details
    $upd = $db->prepare("
        UPDATE purchase_orders 
        SET status = 'cancelled',
            cancelled_by = :cancelled_by,
            cancelled_at = NOW(),
            cancellation_reason = :reason,
            updated_at = NOW() 
        WHERE id = :id
    ");
    $upd->execute([
        ':cancelled_by' => $userId,
        ':reason' => 'Rejected during approval: ' . $rejectionReason,
        ':id' => $id
    ]);

    // Insert history
    $hStmt = $db->prepare("
        INSERT INTO purchase_order_history (
            purchase_order_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :po_id, :user_id, 'rejected', 'pending_approval', 'cancelled', :comments, NOW()
        )
    ");
    $hStmt->execute([
        ':po_id' => $id,
        ':user_id' => $userId,
        ':comments' => 'Rejected: ' . $rejectionReason
    ]);

    logActivity("Rejected Purchase Order {$po['po_no']}", 'purchase_orders', $id);

    $db->commit();
    setFlash('warning', "Purchase Order {$po['po_no']} has been rejected and marked as cancelled.");
    redirect('modules/purchase_orders/view.php?id=' . $id);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Reject PO Error: ' . $e->getMessage());
    setFlash('error', 'Error rejecting Purchase Order: ' . $e->getMessage());
    redirect('modules/purchase_orders/view.php?id=' . $id);
}
