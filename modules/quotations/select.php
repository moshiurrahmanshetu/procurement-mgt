<?php
/**
 * Select Winning Quotation Handler (POST)
 * Awards the chosen quotation and automatically rejects all other competing quotations for the same Purchase Request
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/quotations/index.php');
}

// Permission check: Administrator or Manager only
if (!userHasRole('administrator') && !userHasRole('manager')) {
    $_SESSION['flash_error'] = 'Access denied. Only Administrators and Managers can award quotations.';
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
$selectionComments = sanitizeInput($_POST['selection_comments'] ?? '');

try {
    // 1. Fetch Winning Quotation with PR & Supplier details
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

    if ($quotation['status'] === 'selected') {
        $_SESSION['flash_error'] = 'This quotation has already been selected and awarded.';
        redirect('modules/quotations/view.php?id=' . $quotationId);
    }

    $prId = (int)$quotation['purchase_request_id'];

    $db->beginTransaction();

    // 2. Mark this quotation as Selected
    $awardStmt = $db->prepare("UPDATE quotations SET status = 'selected', updated_at = NOW() WHERE id = :id");
    $awardStmt->execute([':id' => $quotationId]);

    // Insert history for winning quotation
    $winHistStmt = $db->prepare("
        INSERT INTO quotation_history (
            quotation_id, user_id, action, from_status, to_status, comments, created_at
        ) VALUES (
            :quotation_id, :user_id, 'selected', :from_status, 'selected', :comments, NOW()
        )
    ");
    $winHistStmt->execute([
        ':quotation_id' => $quotationId,
        ':user_id'      => $user['id'],
        ':from_status'  => $quotation['status'],
        ':comments'     => !empty($selectionComments) ? $selectionComments : "Awarded and selected as winning quotation for PR {$quotation['request_no']}"
    ]);

    // 3. Find and automatically reject ALL OTHER non-deleted active quotations for this PR
    $compStmt = $db->prepare("
        SELECT id, quotation_no, status, supplier_id
        FROM quotations
        WHERE purchase_request_id = :pr_id
          AND id != :winning_id
          AND status != 'selected'
          AND deleted_at IS NULL
    ");
    $compStmt->execute([
        ':pr_id'      => $prId,
        ':winning_id' => $quotationId
    ]);
    $competingBids = $compStmt->fetchAll();

    $rejectStmt = $db->prepare("UPDATE quotations SET status = 'rejected', updated_at = NOW() WHERE id = :id");
    $rejectHistStmt = $db->prepare("
        INSERT INTO quotation_history (
            quotation_id, user_id, action, from_status, to_status, comments, created_at
        ) VALUES (
            :quotation_id, :user_id, 'rejected', :from_status, 'rejected', :comments, NOW()
        )
    ");

    foreach ($competingBids as $bid) {
        $rejectStmt->execute([':id' => $bid['id']]);
        $rejectHistStmt->execute([
            ':quotation_id' => $bid['id'],
            ':user_id'      => $user['id'],
            ':from_status'  => $bid['status'],
            ':comments'     => "Automatically rejected upon award of winning quotation {$quotation['quotation_no']} ({$quotation['supplier_name']})"
        ]);
    }

    $db->commit();

    logActivity($user['id'], 'select_quotation', "Awarded winning quotation {$quotation['quotation_no']} for PR {$quotation['request_no']}. Auto-rejected " . count($competingBids) . " competing bids.");

    $_SESSION['flash_success'] = "Quotation '{$quotation['quotation_no']}' awarded successfully! " . count($competingBids) . " competing bids were closed.";
    redirect('modules/quotations/view.php?id=' . $quotationId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error selecting winning quotation: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error selecting quotation: ' . $e->getMessage();
    redirect('modules/quotations/view.php?id=' . $quotationId);
}
