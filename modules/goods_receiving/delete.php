<?php
/**
 * Delete Draft Goods Receipt (GRN) Handler
 * Procurement Management CMS - Phase 05
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Verification
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: You do not have permission to delete goods receipts.');
    redirect('modules/goods_receiving/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/goods_receiving/index.php');
}

// 2. CSRF Token Verification
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid or expired session token. Please try again.');
    redirect('modules/goods_receiving/index.php');
}

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$grnId = (int)($_POST['id'] ?? 0);
if ($grnId <= 0) {
    setFlash('error', 'Invalid Goods Receipt ID.');
    redirect('modules/goods_receiving/index.php');
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("
        SELECT id, grn_no, status, purchase_order_id
        FROM goods_receipts
        WHERE id = :id AND deleted_at IS NULL
        FOR UPDATE
    ");
    $stmt->execute([':id' => $grnId]);
    $grn = $stmt->fetch();

    if (!$grn) {
        $db->rollBack();
        setFlash('error', 'Goods Receipt not found or already deleted.');
        redirect('modules/goods_receiving/index.php');
    }

    if ($grn['status'] !== 'draft') {
        $db->rollBack();
        setFlash('error', 'Posted goods receipts are immutable and cannot be deleted.');
        redirect('modules/goods_receiving/view.php?id=' . $grnId);
    }

    // Soft delete
    $delStmt = $db->prepare("
        UPDATE goods_receipts
        SET deleted_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ");
    $delStmt->execute([':id' => $grnId]);

    // Record history
    $histStmt = $db->prepare("
        INSERT INTO goods_receipt_history (
            goods_receipt_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :goods_receipt_id, :user_id, 'deleted', 'draft', 'deleted', 'Draft Goods Receipt soft-deleted.', NOW()
        )
    ");
    $histStmt->execute([
        ':goods_receipt_id' => $grnId,
        ':user_id'          => $userId
    ]);

    // Activity log
    logActivity($userId, 'GRN Deleted', "Deleted draft Goods Receipt {$grn['grn_no']}");

    $db->commit();

    setFlash('success', "Draft Goods Receipt {$grn['grn_no']} has been deleted.");
    redirect('modules/goods_receiving/index.php');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error deleting GRN: ' . $e->getMessage());
    setFlash('error', 'A database error occurred while deleting the goods receipt: ' . $e->getMessage());
    redirect('modules/goods_receiving/index.php');
}
