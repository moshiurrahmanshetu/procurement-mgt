<?php
/**
 * Native CSV Data Export Engine
 * Procurement Management CMS - Phase 06
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$isRequester = userHasRole('requester');
$db = getDb();

$type = sanitizeInput($_GET['type'] ?? 'purchase_requests');
$timestamp = date('Y-m-d_His');
$filename = "procurecms_{$type}_{$timestamp}.csv";

// Clean any buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Send HTTP CSV Headers
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ==============================================================================
// 1. PURCHASE REQUESTS EXPORT
// ==============================================================================
if ($type === 'purchase_requests') {
    $dateFrom     = sanitizeInput($_GET['date_from'] ?? '');
    $dateTo       = sanitizeInput($_GET['date_to'] ?? '');
    $departmentId = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;
    $requestedBy  = !empty($_GET['requested_by']) ? (int)$_GET['requested_by'] : null;
    $status       = sanitizeInput($_GET['status'] ?? '');
    $priority     = sanitizeInput($_GET['priority'] ?? '');

    if ($isRequester && !$isAdmin && !$isManager && !$isOfficer) {
        $requestedBy = (int)$user['id'];
    }

    $where = ["pr.deleted_at IS NULL"];
    $params = [];

    if (!empty($dateFrom)) { $where[] = "pr.request_date >= :df"; $params[':df'] = $dateFrom; }
    if (!empty($dateTo)) { $where[] = "pr.request_date <= :dt"; $params[':dt'] = $dateTo; }
    if (!empty($departmentId)) { $where[] = "pr.department_id = :dept"; $params[':dept'] = $departmentId; }
    if (!empty($requestedBy)) { $where[] = "pr.requested_by = :req"; $params[':req'] = $requestedBy; }
    if (!empty($status)) { $where[] = "pr.status = :st"; $params[':st'] = $status; }
    if (!empty($priority)) { $where[] = "pr.priority = :pri"; $params[':pri'] = $priority; }

    $sql = "
        SELECT 
            pr.request_no,
            pr.request_date,
            pr.required_date,
            u.full_name AS requester_name,
            d.name AS department_name,
            pr.priority,
            pr.status,
            pr.estimated_subtotal,
            pr.estimated_tax,
            pr.estimated_total,
            pr.purpose
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY pr.request_date DESC, pr.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    fputcsv($output, ['PR Number', 'Request Date', 'Required Date', 'Requester', 'Department', 'Priority', 'Status', 'Est. Subtotal', 'Est. Tax', 'Est. Total', 'Purpose']);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['request_no'],
            $row['request_date'],
            $row['required_date'] ?? '',
            $row['requester_name'] ?? '',
            $row['department_name'] ?? '',
            ucfirst($row['priority']),
            ucfirst(str_replace('_', ' ', $row['status'])),
            number_format((float)$row['estimated_subtotal'], 2, '.', ''),
            number_format((float)$row['estimated_tax'], 2, '.', ''),
            number_format((float)$row['estimated_total'], 2, '.', ''),
            $row['purpose']
        ]);
    }
}

// ==============================================================================
// 2. SUPPLIERS EXPORT
// ==============================================================================
elseif ($type === 'suppliers') {
    $search  = sanitizeInput($_GET['search'] ?? '');
    $status  = sanitizeInput($_GET['status'] ?? '');
    $country = sanitizeInput($_GET['country'] ?? '');

    $where = ["s.deleted_at IS NULL"];
    $params = [];

    if (!empty($search)) {
        $where[] = "(s.name LIKE :search OR s.supplier_code LIKE :search OR s.email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    if (!empty($status)) { $where[] = "s.status = :st"; $params[':st'] = $status; }
    if (!empty($country)) { $where[] = "s.country = :cty"; $params[':cty'] = $country; }

    $sql = "
        SELECT 
            s.supplier_code,
            s.name,
            s.company_name,
            s.email,
            s.phone,
            s.city,
            s.country,
            s.tax_number,
            s.payment_terms,
            s.status,
            (SELECT sc.name FROM supplier_contacts sc WHERE sc.supplier_id = s.id AND sc.is_primary = 1 LIMIT 1) AS primary_contact,
            (SELECT COUNT(*) FROM quotations q WHERE q.supplier_id = s.id AND q.deleted_at IS NULL) AS total_quotes,
            (SELECT COUNT(*) FROM quotations q WHERE q.supplier_id = s.id AND q.status = 'selected' AND q.deleted_at IS NULL) AS selected_quotes,
            (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.deleted_at IS NULL) AS total_pos,
            (SELECT COALESCE(SUM(po.grand_total), 0.00) FROM purchase_orders po WHERE po.supplier_id = s.id AND po.status IN ('approved', 'sent', 'partially_received', 'fully_received', 'closed') AND po.deleted_at IS NULL) AS total_spend
        FROM suppliers s
        WHERE " . implode(' AND ', $where) . "
        ORDER BY s.name ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    fputcsv($output, ['Supplier Code', 'Name', 'Company', 'Primary Contact', 'Email', 'Phone', 'City', 'Country', 'Tax No', 'Payment Terms', 'Status', 'Total Quotes', 'Awarded Quotes', 'Total POs', 'Committed Spend']);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['supplier_code'],
            $row['name'],
            $row['company_name'] ?? '',
            $row['primary_contact'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['city'] ?? '',
            $row['country'] ?? '',
            $row['tax_number'] ?? '',
            $row['payment_terms'] ?? '',
            ucfirst($row['status']),
            (int)$row['total_quotes'],
            (int)$row['selected_quotes'],
            (int)$row['total_pos'],
            number_format((float)$row['total_spend'], 2, '.', '')
        ]);
    }
}

// ==============================================================================
// 3. QUOTATIONS EXPORT
// ==============================================================================
elseif ($type === 'quotations') {
    $dateFrom   = sanitizeInput($_GET['date_from'] ?? '');
    $dateTo     = sanitizeInput($_GET['date_to'] ?? '');
    $supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
    $status     = sanitizeInput($_GET['status'] ?? '');

    $where = ["q.deleted_at IS NULL"];
    $params = [];

    if (!empty($dateFrom)) { $where[] = "q.quotation_date >= :df"; $params[':df'] = $dateFrom; }
    if (!empty($dateTo)) { $where[] = "q.quotation_date <= :dt"; $params[':dt'] = $dateTo; }
    if (!empty($supplierId)) { $where[] = "q.supplier_id = :sup"; $params[':sup'] = $supplierId; }
    if (!empty($status)) { $where[] = "q.status = :st"; $params[':st'] = $status; }

    $sql = "
        SELECT 
            q.quotation_no,
            pr.request_no,
            s.name AS supplier_name,
            s.supplier_code,
            q.quotation_date,
            q.valid_until,
            q.reference_number,
            q.subtotal,
            q.tax_amount,
            q.shipping_cost,
            q.discount_amount,
            q.total_amount,
            q.status
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY q.quotation_date DESC, q.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    fputcsv($output, ['Quotation No', 'PR Reference', 'Supplier Name', 'Supplier Code', 'Quotation Date', 'Valid Until', 'Ref No', 'Subtotal', 'Tax Amount', 'Shipping Cost', 'Discount', 'Total Amount', 'Status']);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['quotation_no'],
            $row['request_no'],
            $row['supplier_name'],
            $row['supplier_code'],
            $row['quotation_date'],
            $row['valid_until'] ?? '',
            $row['reference_number'] ?? '',
            number_format((float)$row['subtotal'], 2, '.', ''),
            number_format((float)$row['tax_amount'], 2, '.', ''),
            number_format((float)$row['shipping_cost'], 2, '.', ''),
            number_format((float)$row['discount_amount'], 2, '.', ''),
            number_format((float)$row['total_amount'], 2, '.', ''),
            ucfirst(str_replace('_', ' ', $row['status']))
        ]);
    }
}

// ==============================================================================
// 4. PURCHASE ORDERS EXPORT
// ==============================================================================
elseif ($type === 'purchase_orders') {
    $dateFrom   = sanitizeInput($_GET['date_from'] ?? '');
    $dateTo     = sanitizeInput($_GET['date_to'] ?? '');
    $supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
    $status     = sanitizeInput($_GET['status'] ?? '');

    $where = ["po.deleted_at IS NULL"];
    $params = [];

    if (!empty($dateFrom)) { $where[] = "po.po_date >= :df"; $params[':df'] = $dateFrom; }
    if (!empty($dateTo)) { $where[] = "po.po_date <= :dt"; $params[':dt'] = $dateTo; }
    if (!empty($supplierId)) { $where[] = "po.supplier_id = :sup"; $params[':sup'] = $supplierId; }
    if (!empty($status)) { $where[] = "po.status = :st"; $params[':st'] = $status; }

    $sql = "
        SELECT 
            po.po_no,
            pr.request_no,
            s.name AS supplier_name,
            s.supplier_code,
            po.po_date,
            po.expected_delivery_date,
            po.subtotal,
            po.tax_amount,
            po.discount_amount,
            po.grand_total,
            po.status,
            u.full_name AS creator_name
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = po.supplier_id
        LEFT JOIN users u ON u.id = po.created_by
        WHERE " . implode(' AND ', $where) . "
        ORDER BY po.po_date DESC, po.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    fputcsv($output, ['PO Number', 'PR Reference', 'Supplier Name', 'Supplier Code', 'PO Date', 'Expected Delivery', 'Subtotal', 'Tax Amount', 'Discount', 'Grand Total', 'Status', 'Issued By']);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['po_no'],
            $row['request_no'],
            $row['supplier_name'],
            $row['supplier_code'],
            $row['po_date'],
            $row['expected_delivery_date'] ?? '',
            number_format((float)$row['subtotal'], 2, '.', ''),
            number_format((float)$row['tax_amount'], 2, '.', ''),
            number_format((float)$row['discount_amount'], 2, '.', ''),
            number_format((float)$row['grand_total'], 2, '.', ''),
            ucfirst(str_replace('_', ' ', $row['status'])),
            $row['creator_name'] ?? ''
        ]);
    }
}

// ==============================================================================
// 5. GOODS RECEIVING EXPORT
// ==============================================================================
elseif ($type === 'goods_receiving') {
    $dateFrom   = sanitizeInput($_GET['date_from'] ?? '');
    $dateTo     = sanitizeInput($_GET['date_to'] ?? '');
    $supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
    $poId       = !empty($_GET['po_id']) ? (int)$_GET['po_id'] : null;
    $status     = sanitizeInput($_GET['status'] ?? '');

    $where = ["gr.deleted_at IS NULL"];
    $params = [];

    if (!empty($dateFrom)) { $where[] = "gr.receipt_date >= :df"; $params[':df'] = $dateFrom; }
    if (!empty($dateTo)) { $where[] = "gr.receipt_date <= :dt"; $params[':dt'] = $dateTo; }
    if (!empty($supplierId)) { $where[] = "gr.supplier_id = :sup"; $params[':sup'] = $supplierId; }
    if (!empty($poId)) { $where[] = "gr.purchase_order_id = :po"; $params[':po'] = $poId; }
    if (!empty($status)) { $where[] = "gr.status = :st"; $params[':st'] = $status; }

    $sql = "
        SELECT 
            gr.grn_no,
            po.po_no,
            s.name AS supplier_name,
            s.supplier_code,
            gr.receipt_date,
            gr.delivery_note_no,
            u.full_name AS receiver_name,
            (SELECT COALESCE(SUM(received_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_rec_qty,
            (SELECT COALESCE(SUM(rejected_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_rej_qty,
            gr.status,
            gr.notes
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN suppliers s ON s.id = gr.supplier_id
        LEFT JOIN users u ON u.id = gr.received_by
        WHERE " . implode(' AND ', $where) . "
        ORDER BY gr.receipt_date DESC, gr.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    fputcsv($output, ['GRN Number', 'PO Number', 'Supplier Name', 'Supplier Code', 'Receipt Date', 'Delivery Note No', 'Received By', 'Accepted Qty', 'Rejected Qty', 'Status', 'Notes']);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['grn_no'],
            $row['po_no'],
            $row['supplier_name'],
            $row['supplier_code'],
            $row['receipt_date'],
            $row['delivery_note_no'] ?? '',
            $row['receiver_name'] ?? '',
            number_format((float)$row['total_rec_qty'], 2, '.', ''),
            number_format((float)$row['total_rej_qty'], 2, '.', ''),
            ucfirst($row['status']),
            $row['notes'] ?? ''
        ]);
    }
}

fclose($output);
exit;
