<?php
/**
 * Review Quotation Handler (POST)
 * Transitions Submitted -> Under Review
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/quotations/index.php');
}

// Permission check
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to review quotations.';
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
    redirect('modules/quotations/view.php?id=' . $quotationId);
}

$user = currentUser();
$db = getDb();

try {
    $stmt = $db->prepare("SELECT * FROM quotations WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $quotationId]);
    $quotation = $stmt->fetch();

    if (!$quotation) {
        $_SESSION['flash_error'] = 'Quotation not found.';
        redirect('modules/quotations/index.php');
    }

    if ($quotation['status'] !== 'submitted') {
        $_SESSION['flash_error'] = "Quotation cannot be moved to review from status '{$quotation['status']}'.";
        redirect('modules/quotations/view.php?id=' . $quotationId);
    }

    $db->beginTransaction();

    $updStmt = $db->prepare("UPDATE quotations SET status = 'under_review', updated_at = NOW() WHERE id = :id");
    $updStmt->execute([':id' => $quotationId]);

    $histStmt = $db->prepare("
        INSERT INTO quotation_history (
            quotation_id, user_id, action, from_status, to_status, comments, created_at
        ) VALUES (
            :quotation_id, :user_id, 'under_review', 'submitted', 'under_review', 'Quotation marked under technical & commercial review', NOW()
        )
    ");
    $histStmt->execute([
        ':quotation_id' => $quotationId,
        ':user_id'      => $user['id']
    ]);

    $db->commit();

    logActivity($user['id'], 'review_quotation', "Marked quotation {$quotation['quotation_no']} under review");

    $_SESSION['flash_success'] = "Quotation '{$quotation['quotation_no']}' is now Under Review.";
    redirect('modules/quotations/view.php?id=' . $quotationId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error marking quotation under review: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error updating quotation: ' . $e->getMessage();
    redirect('modules/quotations/view.php?id=' . $quotationId);
}
