<?php
/**
 * Approve Purchase Order
 * Procurement Management CMS - Phase 04
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Check: Only Managers & Administrators can approve POs
$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
if (!$isAdmin && !$isManager) {
    setFlash('error', 'Access Denied: Only Managers and Administrators can approve Purchase Orders.');
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
$comments = trim($_POST['comments'] ?? '');

if ($id <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
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
        setFlash('error', 'This Purchase Order is not awaiting approval.');
        redirect('modules/purchase_orders/view.php?id=' . $id);
    }

    // Strict Rule: A Procurement Officer cannot approve their own PO
    // (If non-admin creator tries to approve, block)
    if ((int)$po['created_by'] === $userId && !$isAdmin) {
        $db->rollBack();
        setFlash('error', 'Separation of Duties Violation: You cannot approve a Purchase Order that you created.');
        redirect('modules/purchase_orders/view.php?id=' . $id);
    }

    // Update status to approved
    $upd = $db->prepare("
        UPDATE purchase_orders 
        SET status = 'approved',
            approved_by = :approved_by,
            approved_at = NOW(),
            updated_at = NOW() 
        WHERE id = :id
    ");
    $upd->execute([
        ':approved_by' => $userId,
        ':id' => $id
    ]);

    // Insert history
    $hStmt = $db->prepare("
        INSERT INTO purchase_order_history (
            purchase_order_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :po_id, :user_id, 'approved', 'pending_approval', 'approved', :comments, NOW()
        )
    ");
    $hStmt->execute([
        ':po_id' => $id,
        ':user_id' => $userId,
        ':comments' => !empty($comments) ? $comments : 'Purchase Order approved for dispatch.'
    ]);

    logActivity("Approved Purchase Order {$po['po_no']}", 'purchase_orders', $id);

    $db->commit();
    setFlash('success', "Purchase Order {$po['po_no']} has been successfully approved.");
    redirect('modules/purchase_orders/view.php?id=' . $id);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Approve PO Error: ' . $e->getMessage());
    setFlash('error', 'Error approving Purchase Order: ' . $e->getMessage());
    redirect('modules/purchase_orders/view.php?id=' . $id);
}
