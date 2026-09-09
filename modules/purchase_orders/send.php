<?php
/**
 * Send Purchase Order to Supplier
 * Procurement Management CMS - Phase 04
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Check: Only Procurement Officers & Administrators can mark PO as sent
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: Only Procurement Officers and Administrators can send Purchase Orders.');
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

    if ($po['status'] !== 'approved') {
        $db->rollBack();
        setFlash('error', 'Only approved Purchase Orders can be dispatched to suppliers.');
        redirect('modules/purchase_orders/view.php?id=' . $id);
    }

    // Update status to sent
    $upd = $db->prepare("
        UPDATE purchase_orders 
        SET status = 'sent',
            sent_at = NOW(),
            updated_at = NOW() 
        WHERE id = :id
    ");
    $upd->execute([':id' => $id]);

    // Insert history
    $hStmt = $db->prepare("
        INSERT INTO purchase_order_history (
            purchase_order_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :po_id, :user_id, 'sent', 'approved', 'sent', :comments, NOW()
        )
    ");
    $hStmt->execute([
        ':po_id' => $id,
        ':user_id' => $userId,
        ':comments' => !empty($comments) ? $comments : 'Purchase Order dispatched to supplier.'
    ]);

    logActivity("Sent Purchase Order {$po['po_no']} to supplier", 'purchase_orders', $id);

    $db->commit();
    setFlash('success', "Purchase Order {$po['po_no']} has been marked as officially Sent to supplier.");
    redirect('modules/purchase_orders/view.php?id=' . $id);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Send PO Error: ' . $e->getMessage());
    setFlash('error', 'Error sending Purchase Order: ' . $e->getMessage());
    redirect('modules/purchase_orders/view.php?id=' . $id);
}
