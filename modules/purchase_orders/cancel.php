<?php
/**
 * Cancel Purchase Order
 * Procurement Management CMS - Phase 04
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Check: Procurement Officers, Managers, and Administrators can cancel
$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isManager && !$isOfficer) {
    setFlash('error', 'Access Denied: You do not have permission to cancel Purchase Orders.');
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
$cancellationReason = trim($_POST['cancellation_reason'] ?? '');

if ($id <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
}

if (empty($cancellationReason)) {
    setFlash('error', 'Cancellation reason is required.');
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

    // Business Rule Check: Sent POs cannot be cancelled
    if ($po['status'] === 'sent') {
        $db->rollBack();
        setFlash('error', 'Illegal Action: Purchase Orders that have already been sent to suppliers cannot be cancelled.');
        redirect('modules/purchase_orders/view.php?id=' . $id);
    }

    if ($po['status'] === 'cancelled') {
        $db->rollBack();
        setFlash('error', 'This Purchase Order is already cancelled.');
        redirect('modules/purchase_orders/view.php?id=' . $id);
    }

    $oldStatus = $po['status'];

    // Update status to cancelled
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
        ':reason' => $cancellationReason,
        ':id' => $id
    ]);

    // Insert history
    $hStmt = $db->prepare("
        INSERT INTO purchase_order_history (
            purchase_order_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :po_id, :user_id, 'cancelled', :old_status, 'cancelled', :comments, NOW()
        )
    ");
    $hStmt->execute([
        ':po_id' => $id,
        ':user_id' => $userId,
        ':old_status' => $oldStatus,
        ':comments' => 'Cancelled: ' . $cancellationReason
    ]);

    logActivity("Cancelled Purchase Order {$po['po_no']}", 'purchase_orders', $id);

    $db->commit();
    setFlash('warning', "Purchase Order {$po['po_no']} has been cancelled.");
    redirect('modules/purchase_orders/view.php?id=' . $id);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Cancel PO Error: ' . $e->getMessage());
    setFlash('error', 'Error cancelling Purchase Order: ' . $e->getMessage());
    redirect('modules/purchase_orders/view.php?id=' . $id);
}
