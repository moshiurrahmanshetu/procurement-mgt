<?php
/**
 * Store New Purchase Request Action
 * Procurement Management CMS - Phase 02
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/purchase_requests/index.php');
}

// 1. Validate CSRF
if (!validateCsrfToken()) {
    setFlash('error', 'Invalid security token or session expired. Please try again.');
    redirect('modules/purchase_requests/create.php');
}

$user = currentUser();
$userId = currentUserId();
$db = getDb();

// 2. Capture Form Inputs
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

// 3. Validate Header Data
if ($departmentId <= 0) {
    $errors[] = 'Please select a valid department.';
} else {
    // Verify department in DB
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

// 4. Validate & Recalculate Items
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
    redirect('modules/purchase_requests/create.php');
}

// 5. Calculate Final Totals
$estimatedTax = max(0.00, round($estimatedTaxInput, 2));
$estimatedTotal = round($subtotal + $estimatedTax, 2);

$initialStatus = ($submitAction === 'submit') ? 'pending_approval' : 'draft';

// 6. Save in Database using Transaction
try {
    $db->beginTransaction();

    // Insert initial request row with temporary number
    $tempRequestNo = 'PR-TMP-' . uniqid();
    $insertPrSql = "
        INSERT INTO purchase_requests (
            request_no, requested_by, department_id, request_date, required_date,
            priority, purpose, notes, estimated_subtotal, estimated_tax, estimated_total,
            status, created_at, updated_at
        ) VALUES (
            :request_no, :requested_by, :department_id, :request_date, :required_date,
            :priority, :purpose, :notes, :estimated_subtotal, :estimated_tax, :estimated_total,
            :status, NOW(), NOW()
        )
    ";
    $insertPrStmt = $db->prepare($insertPrSql);
    $insertPrStmt->execute([
        ':request_no'          => $tempRequestNo,
        ':requested_by'        => $userId,
        ':department_id'       => $departmentId,
        ':request_date'        => $requestDate,
        ':required_date'       => $requiredDate,
        ':priority'            => $priority,
        ':purpose'             => $purpose,
        ':notes'               => $notes,
        ':estimated_subtotal'  => $subtotal,
        ':estimated_tax'       => $estimatedTax,
        ':estimated_total'     => $estimatedTotal,
        ':status'              => $initialStatus
    ]);

    $requestId = (int)$db->lastInsertId();

    // Generate reliable auto-increment sequence request number (PR-000001)
    $finalRequestNo = sprintf('PR-%06d', $requestId);

    $updatePrStmt = $db->prepare("UPDATE purchase_requests SET request_no = :request_no WHERE id = :id");
    $updatePrStmt->execute([
        ':request_no' => $finalRequestNo,
        ':id'         => $requestId
    ]);

    // Insert Line Items
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
            ':purchase_request_id' => $requestId,
            ':item_name'           => $vItem['item_name'],
            ':description'         => $vItem['description'],
            ':quantity'            => $vItem['quantity'],
            ':unit'                => $vItem['unit'],
            ':estimated_unit_price'=> $vItem['estimated_unit_price'],
            ':estimated_total'     => $vItem['estimated_total']
        ]);
    }

    // Insert Workflow History
    $historySql = "
        INSERT INTO purchase_request_history (
            purchase_request_id, user_id, action, old_status, new_status, comments, created_at
        ) VALUES (
            :purchase_request_id, :user_id, :action, :old_status, :new_status, :comments, NOW()
        )
    ";
    $historyStmt = $db->prepare($historySql);

    if ($initialStatus === 'draft') {
        $historyStmt->execute([
            ':purchase_request_id' => $requestId,
            ':user_id'             => $userId,
            ':action'              => 'Created Draft',
            ':old_status'          => null,
            ':new_status'          => 'draft',
            ':comments'            => 'Requisition draft created with ' . count($validItems) . ' line item(s).'
        ]);
        logActivity($userId, 'PR Created Draft', 'Created draft purchase request #' . $finalRequestNo);
        setFlash('success', 'Purchase request ' . $finalRequestNo . ' has been saved as draft.');
    } else {
        $historyStmt->execute([
            ':purchase_request_id' => $requestId,
            ':user_id'             => $userId,
            ':action'              => 'Created & Submitted',
            ':old_status'          => 'draft',
            ':new_status'          => 'pending_approval',
            ':comments'            => 'Requisition submitted directly for management approval.'
        ]);
        logActivity($userId, 'PR Submitted', 'Created and submitted purchase request #' . $finalRequestNo);
        setFlash('success', 'Purchase request ' . $finalRequestNo . ' has been submitted for approval!');
    }

    $db->commit();
    redirect('modules/purchase_requests/view.php?id=' . $requestId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Store PR Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while creating the purchase request. Please try again.');
    redirect('modules/purchase_requests/create.php');
}
