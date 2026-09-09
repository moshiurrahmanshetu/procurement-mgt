<?php
/**
 * Submit Quotation Handler (POST)
 * Transitions Draft -> Submitted
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/quotations/index.php');
}

// Permission check
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to submit quotations.';
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

    if ($quotation['status'] !== 'draft') {
        $_SESSION['flash_error'] = "Quotation cannot be submitted from current status '{$quotation['status']}'.";
        redirect('modules/quotations/view.php?id=' . $quotationId);
    }

    $db->beginTransaction();

    $updStmt = $db->prepare("UPDATE quotations SET status = 'submitted', updated_at = NOW() WHERE id = :id");
    $updStmt->execute([':id' => $quotationId]);

    $histStmt = $db->prepare("
        INSERT INTO quotation_history (
            quotation_id, user_id, action, from_status, to_status, comments, created_at
        ) VALUES (
            :quotation_id, :user_id, 'submitted', 'draft', 'submitted', 'Quotation formally submitted for evaluation', NOW()
        )
    ");
    $histStmt->execute([
        ':quotation_id' => $quotationId,
        ':user_id'      => $user['id']
    ]);

    $db->commit();

    logActivity($user['id'], 'submit_quotation', "Submitted quotation {$quotation['quotation_no']} for review");

    $_SESSION['flash_success'] = "Quotation '{$quotation['quotation_no']}' has been submitted for evaluation.";
    redirect('modules/quotations/view.php?id=' . $quotationId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error submitting quotation: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error submitting quotation: ' . $e->getMessage();
    redirect('modules/quotations/view.php?id=' . $quotationId);
}
