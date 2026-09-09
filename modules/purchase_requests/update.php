<?php
/**
 * Update Draft Purchase Request Action
 * Procurement Management CMS - Phase 02
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/purchase_requests/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid purchase request ID.');
    redirect('modules/purchase_requests/index.php');
}

// 1. Validate CSRF
if (!validateCsrfToken()) {
    setFlash('error', 'Invalid security token or session expired. Please try again.');
    redirect('modules/purchase_requests/edit.php?id=' . $id);
}

$user = currentUser();
$userId = currentUserId();
$isAdmin = userHasRole('administrator');
$db = getDb();

// 2. Fetch Existing Request
$pr = null;
try {
    $stmt = $db->prepare("SELECT * FROM purchase_requests WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $id]);
    $pr = $stmt->fetch();
} catch (Exception $e) {
    error_log('Fetch PR Update Error: ' . $e->getMessage());
}

if (!$pr) {
    setFlash('error', 'Purchase request not found.');
    redirect('modules/purchase_requests/index.php');
}

// 3. Enforce Draft Status
if ($pr['status'] !== 'draft') {
    setFlash('error', 'Only draft purchase requests can be modified.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 4. Enforce Ownership / Admin Authorization
$isOwner = ((int)$pr['requested_by'] === $userId);
if (!$isOwner && !$isAdmin) {
    setFlash('error', 'Access Denied: You cannot modify another user\'s requisition.');
    redirect('modules/purchase_requests/view.php?id=' . $id);
}

// 5. Capture Form Inputs
$departmentId = (int)($_POST['department_id'] ?? 0);
$priority = sanitizeInput($_POST['priority'] ?? 'medium');
$requestDate = sanitizeInput($_POST['request_date'] ?? date('Y-m-d'));
$requiredDate = sanitizeInput($_POST['required_date'] ?? '');
$purpose = sanitizeInput($_POST['purpose'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');
$estimatedTaxInput = (float)($_POST['estimated_tax'] ?? 0.00);
$submitAction = sanitizeInput($_POST['submit_action'] ?? 'draft');
$rawItems = $_POST['items'] ?? [];

$errors = [];

// 6. Validate Department & Dates
if ($departmentId <= 0) {
    $errors[] = 'Please select a valid department.';
} else {
    $deptStmt = $db->prepare("SELECT id FROM departments WHERE id = :id AND deleted_at IS NULL AND status = 'active' LIMIT 1");
    $deptStmt->execute([':id' => $departmentId]);
    if (!$deptStmt->fetch()) {
        $errors[] = 'The selected department is invalid or inactive.';
    }
}

if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
    $priority = 'medium';
}

if (empty($requestDate) || !strtotime($requestDate)) {
    $errors[] = 'Please provide a valid request date.';
}

if (!empty($requiredDate)) {
    if (!strtotime($requiredDate)) {
        $errors[] = 'Please provide a valid required-by date.';
    } elseif ($requiredDate < $requestDate) {
        $errors[] = 'Required-by date cannot be earlier than the request date.';
    }
} else {
    $requiredDate = null;
}

if (empty($purpose)) {
    $errors[] = 'Please state the purpose / business justification for this requisition.';
}

// 7. Validate & Recalculate Items
$validItems = [];
$subtotal = 0.00;

if (!is_array($rawItems) || count($rawItems) === 0) {
    $errors[] = 'At least one line item is required.';
} else {
    foreach ($rawItems as $index => $item) {
        $name = sanitizeInput($item['name'] ?? '');
        $desc = sanitizeInput($item['description'] ?? '');
        $qty = (float)($item['quantity'] ?? 0);
        $unit = sanitizeInput($item['unit'] ?? 'Pcs');
        $unitPrice = (float)($item['unit_price'] ?? 0);

        if (empty($name)) {
            $errors[] = 'Line item #' . ($index + 1) . ' is missing an item name.';
            continue;
        }

        if ($qty <= 0) {
            $errors[] = 'Line item "' . htmlspecialchars($name) . '" must have a quantity greater than 0.';
            continue;
        }

        if (empty($unit)) {
            $unit = 'Pcs';
        }

        if ($unitPrice < 0) {
            $errors[] = 'Line item "' . htmlspecialchars($name) . '" cannot have a negative unit price.';
            continue;
        }

        $lineTotal = round($qty * $unitPrice, 2);
        $subtotal += $lineTotal;

        $validItems[] = [
            'item_name'            => $name,
            'description'          => $desc,
            'quantity'             => $qty,
            'unit'                 => $unit,
            'estimated_unit_price' => $unitPrice,
            'estimated_total'      => $lineTotal
        ];
    }
}

if (empty($validItems) && empty($errors)) {
    $errors[] = 'Please enter at least one valid line item.';
}

if (!empty($errors)) {
    foreach ($errors as $err) {
        setFlash('error', $err);
    }
    redirect('modules/purchase_requests/edit.php?id=' . $id);
}

// 8. Calculate Final Totals & Status
$estimatedTax = max(0.00, round($estimatedTaxInput, 2));
$estimatedTotal = round($subtotal + $estimatedTax, 2);
$newStatus = ($submitAction === 'submit') ? 'pending_approval' : 'draft';

// 9. Execute Updates in Transaction
try {
    $db->beginTransaction();

    // Update parent request
    $updateSql = "
        UPDATE purchase_requests SET
            department_id = :department_id,
            request_date = :request_date,
            required_date = :required_date,
            priority = :priority,
            purpose = :purpose,
            notes = :notes,
            estimated_subtotal = :estimated_subtotal,
            estimated_tax = :estimated_tax,
            estimated_total = :estimated_total,
            status = :status,
            updated_at = NOW()
        WHERE id = :id
    ";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute([
        ':department_id'      => $departmentId,
        ':request_date'       => $requestDate,
        ':required_date'      => $requiredDate,
        ':priority'           => $priority,
        ':purpose'            => $purpose,
        ':notes'              => $notes,
        ':estimated_subtotal' => $subtotal,
        ':estimated_tax'      => $estimatedTax,
        ':estimated_total'    => $estimatedTotal,
        ':status'             => $newStatus,
        ':id'                 => $id
    ]);

    // Replace items
    $delItemsStmt = $db->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id = :pr_id");
    $delItemsStmt->execute([':pr_id' => $id]);

    $itemInsertSql = "
        INSERT INTO purchase_request_items (
            purchase_request_id, item_name, description, quantity, unit,
            estimated_unit_price, estimated_total, created_at, updated_at
        ) VALUES (
            :purchase_request_id, :item_name, :description, :quantity, :unit,
            :estimated_unit_price, :estimated_total, NOW(), NOW()
        )
    ";
    $itemInsertStmt = $db->prepare($itemInsertSql);

    foreach ($validItems as $vItem) {
        $itemInsertStmt->execute([
            ':purchase_request_id' => $id,
            ':item_name'           => $vItem['item_name'],
            ':description'         => $vItem['description'],
            ':quantity'            => $vItem['quantity'],
            ':unit'                => $vItem['unit'],
            ':estimated_unit_price'=> $vItem['estimated_unit_price'],
            ':estimated_total'     => $vItem['estimated_total']
        ]);
    }

    // Insert Workflow History Record
    $historySql = "
        INSERT INTO purchase_request_history (
            purchase_request_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :purchase_request_id, :user_id, :action, :old_status, :new_status, :comments, NOW()
        )
    ";
    $historyStmt = $db->prepare($historySql);

    if ($newStatus === 'draft') {
        $historyStmt->execute([
            ':purchase_request_id' => $id,
            ':user_id'             => $userId,
            ':action'              => 'Updated Draft',
            ':old_status'          => 'draft',
            ':new_status'          => 'draft',
            ':comments'            => 'Requisition draft updated with ' . count($validItems) . ' line item(s).'
        ]);
        logActivity($userId, 'PR Updated Draft', 'Updated draft purchase request #' . $pr['request_no']);
        setFlash('success', 'Purchase request ' . $pr['request_no'] . ' draft has been updated successfully.');
    } else {
        $historyStmt->execute([
            ':purchase_request_id' => $id,
            ':user_id'             => $userId,
            ':action'              => 'Updated & Submitted',
            ':old_status'          => 'draft',
            ':new_status'          => 'pending_approval',
            ':comments'            => 'Requisition updated and submitted for approval.'
        ]);
        logActivity($userId, 'PR Submitted', 'Updated and submitted purchase request #' . $pr['request_no']);
        setFlash('success', 'Purchase request ' . $pr['request_no'] . ' has been updated and submitted for approval!');
    }

    $db->commit();
    redirect('modules/purchase_requests/view.php?id=' . $id);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Update PR Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while updating the requisition.');
    redirect('modules/purchase_requests/edit.php?id=' . $id);
}
