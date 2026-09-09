<?php
/**
 * Post Goods Receipt (GRN) Handler
 * Procurement Management CMS - Phase 05
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Verification
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: You do not have permission to post goods receipts.');
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

// 3. Capture Inputs
$grnId = (int)($_POST['id'] ?? 0);
$comments = sanitizeInput($_POST['comments'] ?? '');

if ($grnId <= 0) {
    setFlash('error', 'Invalid Goods Receipt ID.');
    redirect('modules/goods_receiving/index.php');
}

try {
    $db->beginTransaction();

    // 4. Lock & Validate GRN Record
    $grnStmt = $db->prepare("
        SELECT id, grn_no, purchase_order_id, supplier_id, status
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
        setFlash('error', 'This Goods Receipt is already posted and locked.');
        redirect('modules/goods_receiving/view.php?id=' . $grnId);
    }

    $poId = (int)$grn['purchase_order_id'];

    // 5. Lock & Validate Purchase Order
    $poStmt = $db->prepare("
        SELECT id, po_no, status
        FROM purchase_orders
        WHERE id = :id AND deleted_at IS NULL
        FOR UPDATE
    ");
    $poStmt->execute([':id' => $poId]);
    $po = $poStmt->fetch();

    if (!$po) {
        $db->rollBack();
        setFlash('error', 'Associated Purchase Order not found or has been deleted.');
        redirect('modules/goods_receiving/view.php?id=' . $grnId);
    }

    if (!in_array($po['status'], ['sent', 'partially_received'])) {
        $db->rollBack();
        setFlash('error', 'Associated Purchase Order is not in a receivable state (Status: ' . ucfirst(str_replace('_', ' ', $po['status'])) . ').');
        redirect('modules/goods_receiving/view.php?id=' . $grnId);
    }

    // 6. Fetch Items of this GRN
    $griStmt = $db->prepare("
        SELECT * FROM goods_receipt_items 
        WHERE goods_receipt_id = :grn_id
    ");
    $griStmt->execute([':grn_id' => $grnId]);
    $grnItems = $griStmt->fetchAll();

    if (empty($grnItems)) {
        $db->rollBack();
        setFlash('error', 'Cannot post an empty Goods Receipt Note.');
        redirect('modules/goods_receiving/view.php?id=' . $grnId);
    }

    // 7. Fetch all PO items with prior cumulative POSTED received quantities
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
               ), 0) AS cumulative_posted_qty
        FROM purchase_order_items poi
        WHERE poi.purchase_order_id = :po_id
    ");
    $poiStmt->execute([':po_id' => $poId]);
    $allPoItems = $poiStmt->fetchAll();

    $poItemsKeyed = [];
    foreach ($allPoItems as $pi) {
        $poItemsKeyed[(int)$pi['id']] = $pi;
    }

    // 8. Validate against over-receiving
    $totalAcceptedInGrn = 0.0;
    $grnItemsByPoiId = [];
    foreach ($grnItems as $gi) {
        $poiId = (int)$gi['purchase_order_item_id'];
        $grnItemsByPoiId[$poiId] = $gi;
        $totalAcceptedInGrn += (float)$gi['received_qty'];

        if (!isset($poItemsKeyed[$poiId])) {
            $db->rollBack();
            setFlash('error', 'Item does not belong to Purchase Order ' . $po['po_no']);
            redirect('modules/goods_receiving/view.php?id=' . $grnId);
        }

        $orderedQty = (float)$poItemsKeyed[$poiId]['quantity'];
        $priorPosted = (float)$poItemsKeyed[$poiId]['cumulative_posted_qty'];
        $newAccepted = (float)$gi['received_qty'];

        if (($priorPosted + $newAccepted) > ($orderedQty + 0.0001)) {
            $db->rollBack();
            setFlash('error', "Over-receiving blocked for '{$poItemsKeyed[$poiId]['item_name']}'. Ordered: {$orderedQty}, Previously Posted: {$priorPosted}, Attempted: {$newAccepted}.");
            redirect('modules/goods_receiving/view.php?id=' . $grnId);
        }
    }

    // 9. Update GRN status to 'posted'
    $postGrnStmt = $db->prepare("
        UPDATE goods_receipts
        SET status = 'posted',
            posted_at = NOW(),
            updated_at = NOW()
        WHERE id = :id
    ");
    $postGrnStmt->execute([':id' => $grnId]);

    // 10. Evaluate New PO Status
    $allFulfilled = true;
    $hasAnyReceived = false;

    foreach ($allPoItems as $pi) {
        $poiId = (int)$pi['id'];
        $ordered = (float)$pi['quantity'];
        $prior = (float)$pi['cumulative_posted_qty'];
        $thisRec = isset($grnItemsByPoiId[$poiId]) ? (float)$grnItemsByPoiId[$poiId]['received_qty'] : 0.0;
        $totalAfter = $prior + $thisRec;

        if ($totalAfter < ($ordered - 0.0001)) {
            $allFulfilled = false;
        }

        if ($totalAfter > 0.0001) {
            $hasAnyReceived = true;
        }
    }

    $oldPoStatus = $po['status'];
    $newPoStatus = $oldPoStatus;

    if ($allFulfilled) {
        $newPoStatus = 'fully_received';
    } elseif ($hasAnyReceived) {
        $newPoStatus = 'partially_received';
    }

    // Update PO Status if changed
    if ($newPoStatus !== $oldPoStatus) {
        $updatePoStmt = $db->prepare("
            UPDATE purchase_orders
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");
        $updatePoStmt->execute([
            ':status' => $newPoStatus,
            ':id'     => $poId
        ]);

        // Record PO Status Change History
        $poHistStmt = $db->prepare("
            INSERT INTO purchase_order_history (
                purchase_order_id, user_id, action, old_status, new_status, comments, created_at
            ) VALUES (
                :po_id, :user_id, 'fulfillment_update', :old_status, :new_status, :comments, NOW()
            )
        ");
        $poHistStmt->execute([
            ':po_id'      => $poId,
            ':user_id'    => $userId,
            ':old_status' => $oldPoStatus,
            ':new_status' => $newPoStatus,
            ':comments'   => "Status updated to " . ucfirst(str_replace('_', ' ', $newPoStatus)) . " via Goods Receipt {$grn['grn_no']} posting."
        ]);
    }

    // 11. Record GRN History
    $grnHistStmt = $db->prepare("
        INSERT INTO goods_receipt_history (
            goods_receipt_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :grn_id, :user_id, 'posted', 'draft', 'posted', :comments, NOW()
        )
    ");
    $grnHistStmt->execute([
        ':grn_id'   => $grnId,
        ':user_id'  => $userId,
        ':comments' => !empty($comments) ? $comments : 'Goods Receipt officially posted and locked. Accepted quantity: ' . number_format($totalAcceptedInGrn, 2)
    ]);

    // 12. Activity Log
    logActivity($userId, 'GRN Posted', "Posted Goods Receipt {$grn['grn_no']} for Purchase Order {$po['po_no']}. PO status: {$newPoStatus}");

    $db->commit();

    setFlash('success', "Goods Receipt Note {$grn['grn_no']} successfully posted! Purchase Order {$po['po_no']} status is now " . ucfirst(str_replace('_', ' ', $newPoStatus)) . ".");
    redirect('modules/goods_receiving/view.php?id=' . $grnId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error posting goods receipt: ' . $e->getMessage());
    setFlash('error', 'A database error occurred while posting the goods receipt: ' . $e->getMessage());
    redirect('modules/goods_receiving/view.php?id=' . $grnId);
}
