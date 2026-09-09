<?php
/**
 * Delete Quotation Handler (POST)
 * Soft-delete draft quotation
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/quotations/index.php');
}

// Permission check
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to delete quotations.';
    redirect('modules/quotations/index.php');
}

$quotationId = (int)($_POST['id'] ?? 0);
if ($quotationId <= 0) {
    $_SESSION['flash_error'] = 'Invalid quotation ID.';
    redirect('modules/quotations/index.php');
}

// Verify CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
    redirect('modules/quotations/index.php');
}

$user = currentUser();
$db = getDb();

try {
    $stmt = $db->prepare("SELECT * FROM quotations WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $quotationId]);
    $quotation = $stmt->fetch();

    if (!$quotation) {
        $_SESSION['flash_error'] = 'Quotation not found or already deleted.';
        redirect('modules/quotations/index.php');
    }

    if ($quotation['status'] !== 'draft') {
        $_SESSION['flash_error'] = "Only draft quotations can be deleted. Quotations in '{$quotation['status']}' state are protected for audit compliance.";
        redirect('modules/quotations/view.php?id=' . $quotationId);
    }

    $db->beginTransaction();

    $delStmt = $db->prepare("UPDATE quotations SET deleted_at = NOW() WHERE id = :id");
    $delStmt->execute([':id' => $quotationId]);

    $histStmt = $db->prepare("
        INSERT INTO quotation_history (
            quotation_id, user_id, action, from_status, to_status, comments, created_at
        ) VALUES (
            :quotation_id, :user_id, 'deleted', 'draft', 'deleted', 'Draft quotation soft-deleted', NOW()
        )
    ");
    $histStmt->execute([
        ':quotation_id' => $quotationId,
        ':user_id'      => $user['id']
    ]);

    $db->commit();

    logActivity($user['id'], 'delete_quotation', "Soft deleted draft quotation {$quotation['quotation_no']}");

    $_SESSION['flash_success'] = "Draft quotation '{$quotation['quotation_no']}' has been deleted.";
    redirect('modules/quotations/index.php');

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error deleting quotation: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error deleting quotation: ' . $e->getMessage();
    redirect('modules/quotations/index.php');
}
