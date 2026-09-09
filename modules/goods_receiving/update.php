<?php
/**
 * Update Draft Goods Receipt (GRN) Handler
 * Procurement Management CMS - Phase 05
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Verification
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: You do not have permission to modify goods receipts.');
    redirect('modules/goods_receiving/index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/goods_receiving/index.php');
}

// 2. CSRF Token Verification
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid or expired session token. Please try submitting the form again.');
    redirect('modules/goods_receiving/index.php');
}

$user = currentUser();
$userId = currentUserId();
$db = getDb();

// 3. Capture & Sanitize Form Inputs
$grnId = (int)($_POST['id'] ?? 0);
$receiptDate = sanitizeInput($_POST['receipt_date'] ?? '');
$deliveryNoteNo = sanitizeInput($_POST['delivery_note_no'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');
$submittedItems = $_POST['items'] ?? [];

if ($grnId <= 0) {
    setFlash('error', 'Invalid Goods Receipt ID.');
    redirect('modules/goods_receiving/index.php');
}

if (empty($receiptDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $receiptDate)) {
    setFlash('error', 'Please provide a valid delivery / receipt date.');
    redirect('modules/goods_receiving/edit.php?id=' . $grnId);
}

try {
    $db->beginTransaction();

    // 4. Lock & Validate GRN Record
    $grnStmt = $db->prepare("
        SELECT id, grn_no, purchase_order_id, status
        FROM goods_receipts
        WHERE id = :id AND deleted_at IS NULL
        FOR UPDATE
    ");
    $grnStmt->execute([':id' => $grnId]);
    $grn = $grnStmt->fetch();

    if (!$grn) {
        $db->rollBack();
        setFlash('error', 'Goods Receipt not found or has been deleted.');
        redirect('modules/goods_receiving/index.php');
    }

    if ($grn['status'] !== 'draft') {
        $db->rollBack();
        setFlash('error', 'Posted goods receipts are immutable and cannot be edited.');
        redirect('modules/goods_receiving/view.php?id=' . $grnId);
    }

    $poId = (int)$grn['purchase_order_id'];

    // 5. Fetch PO Items and calculate cumulative POSTED received quantities
    $poiStmt = $db->prepare("
        SELECT poi.*,
               COALESCE((
                   SELECT SUM(gri.received_qty)
                   FROM goods_receipt_items gri
                   JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
                   WHERE gr.purchase_order_id = poi.purchase_order_id
                     AND gr.status = 'posted'
                     AND gr.deleted_at IS NULL
                     AND gri.purchase_order_item_id = poi.id
               ), 0) AS previously_received_qty
        FROM purchase_order_items poi
        WHERE poi.purchase_order_id = :po_id
        ORDER BY poi.id ASC
    ");
    $poiStmt->execute([':po_id' => $poId]);
    $dbItems = $poiStmt->fetchAll();

    $dbItemsKeyed = [];
    foreach ($dbItems as $dbi) {
        $dbItemsKeyed[(int)$dbi['id']] = $dbi;
    }

    // 6. Validate items and quantities
    $itemsToInsert = [];
    $totalReceivedQty = 0.0;
    $totalRejectedQty = 0.0;

    foreach ($submittedItems as $subItem) {
        $poiId = (int)($subItem['purchase_order_item_id'] ?? 0);
        if (!isset($dbItemsKeyed[$poiId])) {
            continue;
        }

        $dbPoi = $dbItemsKeyed[$poiId];
        $orderedQty = (float)$dbPoi['quantity'];
        $prevReceived = (float)$dbPoi['previously_received_qty'];
        $remainingQty = max(0.0, $orderedQty - $prevReceived);

        $recQty = (float)($subItem['received_qty'] ?? 0);
        $rejQty = (float)($subItem['rejected_qty'] ?? 0);
        $lineNotes = sanitizeInput($subItem['notes'] ?? '');

        if ($recQty < 0 || $rejQty < 0) {
            $db->rollBack();
            setFlash('error', 'Negative quantities are not allowed for item: ' . $dbPoi['item_name']);
            redirect('modules/goods_receiving/edit.php?id=' . $grnId);
        }

        // Strict over-receiving check
        if ($recQty > ($remainingQty + 0.0001)) {
            $db->rollBack();
            setFlash('error', "Cannot receive {$recQty} {$dbPoi['unit']} of '{$dbPoi['item_name']}'. Maximum remaining quantity is {$remainingQty}.");
            redirect('modules/goods_receiving/edit.php?id=' . $grnId);
        }

        $itemsToInsert[] = [
            'purchase_order_item_id'  => $poiId,
            'item_name'               => $dbPoi['item_name'],
            'description'             => $dbPoi['description'],
            'ordered_qty'             => $orderedQty,
            'previously_received_qty' => $prevReceived,
            'received_qty'            => round($recQty, 2),
            'rejected_qty'            => round($rejQty, 2),
            'unit'                    => $dbPoi['unit'],
            'notes'                   => $lineNotes
        ];

        $totalReceivedQty += $recQty;
        $totalRejectedQty += $rejQty;
    }

    if (($totalReceivedQty + $totalRejectedQty) <= 0.0001) {
        $db->rollBack();
        setFlash('error', 'At least one item must have a received (accepted) or rejected quantity greater than zero.');
        redirect('modules/goods_receiving/edit.php?id=' . $grnId);
    }

    // 7. Update Header
    $upStmt = $db->prepare("
        UPDATE goods_receipts
        SET receipt_date = :receipt_date,
            delivery_note_no = :delivery_note_no,
            notes = :notes,
            updated_at = NOW()
        WHERE id = :id
    ");
    $upStmt->execute([
        ':id'               => $grnId,
        ':receipt_date'     => $receiptDate,
        ':delivery_note_no' => !empty($deliveryNoteNo) ? $deliveryNoteNo : null,
        ':notes'            => !empty($notes) ? $notes : null
    ]);

    // 8. Replace Items
    $delStmt = $db->prepare("DELETE FROM goods_receipt_items WHERE goods_receipt_id = :grn_id");
    $delStmt->execute([':grn_id' => $grnId]);

    $itemInsert = $db->prepare("
        INSERT INTO goods_receipt_items (
            goods_receipt_id, purchase_order_item_id, item_name, description,
            ordered_qty, previously_received_qty, received_qty, rejected_qty,
            unit, notes, created_at, updated_at
        ) VALUES (
            :goods_receipt_id, :purchase_order_item_id, :item_name, :description,
            :ordered_qty, :previously_received_qty, :received_qty, :rejected_qty,
            :unit, :notes, NOW(), NOW()
        )
    ");

    foreach ($itemsToInsert as $item) {
        $itemInsert->execute([
            ':goods_receipt_id'        => $grnId,
            ':purchase_order_item_id'  => $item['purchase_order_item_id'],
            ':item_name'               => $item['item_name'],
            ':description'             => $item['description'],
            ':ordered_qty'             => $item['ordered_qty'],
            ':previously_received_qty' => $item['previously_received_qty'],
            ':received_qty'            => $item['received_qty'],
            ':rejected_qty'            => $item['rejected_qty'],
            ':unit'                    => $item['unit'],
            ':notes'                   => $item['notes']
        ]);
    }

    // 9. Record History
    $histStmt = $db->prepare("
        INSERT INTO goods_receipt_history (
            goods_receipt_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :goods_receipt_id, :user_id, 'updated', 'draft', 'draft', :comments, NOW()
        )
    ");
    $histStmt->execute([
        ':goods_receipt_id' => $grnId,
        ':user_id'          => $userId,
        ':comments'         => 'Draft updated. Total Accepted: ' . number_format($totalReceivedQty, 2) . ', Total Rejected: ' . number_format($totalRejectedQty, 2)
    ]);

    // 10. Activity Log
    logActivity($userId, 'GRN Updated', "Updated draft Goods Receipt {$grn['grn_no']}");

    $db->commit();

    setFlash('success', "Goods Receipt Note {$grn['grn_no']} updated successfully.");
    redirect('modules/goods_receiving/view.php?id=' . $grnId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error updating goods receipt: ' . $e->getMessage());
    setFlash('error', 'A database error occurred while updating the goods receipt: ' . $e->getMessage());
    redirect('modules/goods_receiving/edit.php?id=' . $grnId);
}
