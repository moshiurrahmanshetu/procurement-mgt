<?php
/**
 * Reject Quotation Handler (POST)
 * Rejects a quotation with mandatory audit reason
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/quotations/index.php');
}

// Permission check: Administrator or Manager only
if (!userHasRole('administrator') && !userHasRole('manager')) {
    $_SESSION['flash_error'] = 'Access denied. Only Administrators and Managers can reject quotations.';
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

$rejectionReason = sanitizeInput($_POST['rejection_reason'] ?? '');
if (empty($rejectionReason)) {
    $_SESSION['flash_error'] = 'A valid rejection reason is required.';
    redirect('modules/quotations/view.php?id=' . $quotationId);
}

$user = currentUser();
$db = getDb();

try {
    $stmt = $db->prepare("
        SELECT q.*, pr.request_no, s.name AS supplier_name
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE q.id = :id AND q.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $quotationId]);
    $quotation = $stmt->fetch();

    if (!$quotation) {
        $_SESSION['flash_error'] = 'Quotation not found or has been deleted.';
        redirect('modules/quotations/index.php');
    }

    $db->beginTransaction();

    $updStmt = $db->prepare("UPDATE quotations SET status = 'rejected', updated_at = NOW() WHERE id = :id");
    $updStmt->execute([':id' => $quotationId]);

    $histStmt = $db->prepare("
        INSERT INTO quotation_history (
            quotation_id, user_id, action, from_status, to_status, comments, created_at
        ) VALUES (
            :quotation_id, :user_id, 'rejected', :from_status, 'rejected', :comments, NOW()
        )
    ");
    $histStmt->execute([
        ':quotation_id' => $quotationId,
        ':user_id'      => $user['id'],
        ':from_status'  => $quotation['status'],
        ':comments'     => $rejectionReason
    ]);

    $db->commit();

    logActivity($user['id'], 'reject_quotation', "Rejected quotation {$quotation['quotation_no']} for PR {$quotation['request_no']}. Reason: {$rejectionReason}");

    $_SESSION['flash_success'] = "Quotation '{$quotation['quotation_no']}' has been rejected.";
    redirect('modules/quotations/view.php?id=' . $quotationId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error rejecting quotation: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error rejecting quotation: ' . $e->getMessage();
    redirect('modules/quotations/view.php?id=' . $quotationId);
}
