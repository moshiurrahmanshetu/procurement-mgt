<?php
/**
 * Print-Friendly Report Document
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

// Load Company Branding Settings
$companyName    = getSetting('company_name', APP_NAME);
$companyEmail   = getSetting('company_email', '');
$companyPhone   = getSetting('company_phone', '');
$companyAddress = getSetting('company_address', '');
$companyLogo    = getSetting('company_logo', '');
$currency       = getSetting('currency', '$');
$generatedDate  = date('d M Y, h:i A');

// Metadata & Data queries based on report type
$reportTitle = 'Procurement Report';
$filtersApplied = [];
$columns = [];
$rows = [];
$totals = [];

// ==============================================================================
// 1. PURCHASE REQUESTS
// ==============================================================================
if ($type === 'purchase_requests') {
    $reportTitle = 'Purchase Request Requisition Report';

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

    if (!empty($dateFrom)) { $where[] = "pr.request_date >= :df"; $params[':df'] = $dateFrom; $filtersApplied['Date From'] = $dateFrom; }
    if (!empty($dateTo)) { $where[] = "pr.request_date <= :dt"; $params[':dt'] = $dateTo; $filtersApplied['Date To'] = $dateTo; }
    if (!empty($departmentId)) {
        $where[] = "pr.department_id = :dept";
        $params[':dept'] = $departmentId;
        $dName = $db->query("SELECT name FROM departments WHERE id = {$departmentId}")->fetchColumn();
        $filtersApplied['Department'] = $dName ?: "#{$departmentId}";
    }
    if (!empty($requestedBy)) {
        $where[] = "pr.requested_by = :req";
        $params[':req'] = $requestedBy;
        $uName = $db->query("SELECT full_name FROM users WHERE id = {$requestedBy}")->fetchColumn();
        $filtersApplied['Requester'] = $uName ?: "#{$requestedBy}";
    }
    if (!empty($status)) { $where[] = "pr.status = :st"; $params[':st'] = $status; $filtersApplied['Status'] = ucfirst($status); }
    if (!empty($priority)) { $where[] = "pr.priority = :pri"; $params[':pri'] = $priority; $filtersApplied['Priority'] = ucfirst($priority); }

    $sql = "
        SELECT 
            pr.request_no,
            pr.request_date,
            u.full_name AS requester_name,
            d.name AS department_name,
            pr.priority,
            pr.status,
            pr.estimated_total
        FROM purchase_requests pr
        LEFT JOIN departments d ON pr.department_id = d.id
        LEFT JOIN users u ON pr.requested_by = u.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY pr.request_date DESC, pr.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $columns = ['PR Number', 'Request Date', 'Requester', 'Department', 'Priority', 'Status', 'Estimated Total'];
    $totalAmount = 0.00;

    foreach ($records as $r) {
        $totalAmount += (float)$r['estimated_total'];
        $rows[] = [
            $r['request_no'],
            formatDate($r['request_date'], 'd M Y'),
            $r['requester_name'] ?? '-',
            $r['department_name'] ?? '-',
            ucfirst($r['priority']),
            ucfirst(str_replace('_', ' ', $r['status'])),
            formatCurrency($r['estimated_total'], $currency)
        ];
    }
    $totals = ['Total Estimated Amount' => formatCurrency($totalAmount, $currency)];
}

// ==============================================================================
// 2. SUPPLIERS
// ==============================================================================
elseif ($type === 'suppliers') {
    $reportTitle = 'Supplier & Vendor Performance Report';

    $search  = sanitizeInput($_GET['search'] ?? '');
    $status  = sanitizeInput($_GET['status'] ?? '');
    $country = sanitizeInput($_GET['country'] ?? '');

    $where = ["s.deleted_at IS NULL"];
    $params = [];

    if (!empty($search)) {
        $where[] = "(s.name LIKE :search OR s.supplier_code LIKE :search OR s.email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
        $filtersApplied['Search'] = $search;
    }
    if (!empty($status)) { $where[] = "s.status = :st"; $params[':st'] = $status; $filtersApplied['Status'] = ucfirst($status); }
    if (!empty($country)) { $where[] = "s.country = :cty"; $params[':cty'] = $country; $filtersApplied['Country'] = $country; }

    $sql = "
        SELECT 
            s.supplier_code,
            s.name,
            s.email,
            s.phone,
            s.country,
            s.status,
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
    $records = $stmt->fetchAll();

    $columns = ['Supplier Code', 'Vendor Name', 'Email', 'Phone', 'Country', 'Status', 'Quotes (Won)', 'POs', 'Committed Spend'];
    $totalSpend = 0.00;

    foreach ($records as $r) {
        $totalSpend += (float)$r['total_spend'];
        $rows[] = [
            $r['supplier_code'],
            $r['name'],
            $r['email'] ?? '-',
            $r['phone'] ?? '-',
            $r['country'] ?? '-',
            ucfirst($r['status']),
            (int)$r['total_quotes'] . ' (' . (int)$r['selected_quotes'] . ')',
            (int)$r['total_pos'],
            formatCurrency($r['total_spend'], $currency)
        ];
    }
    $totals = ['Total Committed Spend' => formatCurrency($totalSpend, $currency)];
}

// ==============================================================================
// 3. QUOTATIONS
// ==============================================================================
elseif ($type === 'quotations') {
    $reportTitle = 'Supplier Quotation & Bidding Report';

    $dateFrom   = sanitizeInput($_GET['date_from'] ?? '');
    $dateTo     = sanitizeInput($_GET['date_to'] ?? '');
    $supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
    $status     = sanitizeInput($_GET['status'] ?? '');

    $where = ["q.deleted_at IS NULL"];
    $params = [];

    if (!empty($dateFrom)) { $where[] = "q.quotation_date >= :df"; $params[':df'] = $dateFrom; $filtersApplied['Date From'] = $dateFrom; }
    if (!empty($dateTo)) { $where[] = "q.quotation_date <= :dt"; $params[':dt'] = $dateTo; $filtersApplied['Date To'] = $dateTo; }
    if (!empty($supplierId)) {
        $where[] = "q.supplier_id = :sup";
        $params[':sup'] = $supplierId;
        $sName = $db->query("SELECT name FROM suppliers WHERE id = {$supplierId}")->fetchColumn();
        $filtersApplied['Supplier'] = $sName ?: "#{$supplierId}";
    }
    if (!empty($status)) { $where[] = "q.status = :st"; $params[':st'] = $status; $filtersApplied['Status'] = ucfirst($status); }

    $sql = "
        SELECT 
            q.quotation_no,
            pr.request_no,
            s.name AS supplier_name,
            q.quotation_date,
            q.valid_until,
            q.status,
            q.total_amount
        FROM quotations q
        JOIN purchase_requests pr ON pr.id = q.purchase_request_id
        JOIN suppliers s ON s.id = q.supplier_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY q.quotation_date DESC, q.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $columns = ['Quotation No', 'PR Reference', 'Supplier Vendor', 'Quote Date', 'Valid Until', 'Status', 'Total Amount'];
    $totalAmount = 0.00;

    foreach ($records as $r) {
        $totalAmount += (float)$r['total_amount'];
        $rows[] = [
            $r['quotation_no'],
            $r['request_no'],
            $r['supplier_name'],
            formatDate($r['quotation_date'], 'd M Y'),
            !empty($r['valid_until']) ? formatDate($r['valid_until'], 'd M Y') : 'Open',
            ucfirst(str_replace('_', ' ', $r['status'])),
            formatCurrency($r['total_amount'], $currency)
        ];
    }
    $totals = ['Gross Quotations Value' => formatCurrency($totalAmount, $currency)];
}

// ==============================================================================
// 4. PURCHASE ORDERS
// ==============================================================================
elseif ($type === 'purchase_orders') {
    $reportTitle = 'Purchase Order Commitment Report';

    $dateFrom   = sanitizeInput($_GET['date_from'] ?? '');
    $dateTo     = sanitizeInput($_GET['date_to'] ?? '');
    $supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
    $status     = sanitizeInput($_GET['status'] ?? '');

    $where = ["po.deleted_at IS NULL"];
    $params = [];

    if (!empty($dateFrom)) { $where[] = "po.po_date >= :df"; $params[':df'] = $dateFrom; $filtersApplied['Date From'] = $dateFrom; }
    if (!empty($dateTo)) { $where[] = "po.po_date <= :dt"; $params[':dt'] = $dateTo; $filtersApplied['Date To'] = $dateTo; }
    if (!empty($supplierId)) {
        $where[] = "po.supplier_id = :sup";
        $params[':sup'] = $supplierId;
        $sName = $db->query("SELECT name FROM suppliers WHERE id = {$supplierId}")->fetchColumn();
        $filtersApplied['Supplier'] = $sName ?: "#{$supplierId}";
    }
    if (!empty($status)) { $where[] = "po.status = :st"; $params[':st'] = $status; $filtersApplied['Status'] = ucfirst($status); }

    $sql = "
        SELECT 
            po.po_no,
            pr.request_no,
            s.name AS supplier_name,
            po.po_date,
            po.expected_delivery_date,
            po.status,
            po.grand_total
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        JOIN suppliers s ON s.id = po.supplier_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY po.po_date DESC, po.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $columns = ['PO Number', 'PR Reference', 'Supplier Vendor', 'PO Date', 'Expected Delivery', 'Status', 'Grand Total'];
    $totalAmount = 0.00;

    foreach ($records as $r) {
        $totalAmount += (float)$r['grand_total'];
        $rows[] = [
            $r['po_no'],
            $r['request_no'],
            $r['supplier_name'],
            formatDate($r['po_date'], 'd M Y'),
            !empty($r['expected_delivery_date']) ? formatDate($r['expected_delivery_date'], 'd M Y') : '-',
            ucfirst(str_replace('_', ' ', $r['status'])),
            formatCurrency($r['grand_total'], $currency)
        ];
    }
    $totals = ['Total PO Spend Commitment' => formatCurrency($totalAmount, $currency)];
}

// ==============================================================================
// 5. GOODS RECEIVING
// ==============================================================================
elseif ($type === 'goods_receiving') {
    $reportTitle = 'Goods Receiving (GRN) Inspection Report';

    $dateFrom   = sanitizeInput($_GET['date_from'] ?? '');
    $dateTo     = sanitizeInput($_GET['date_to'] ?? '');
    $supplierId = !empty($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
    $poId       = !empty($_GET['po_id']) ? (int)$_GET['po_id'] : null;
    $status     = sanitizeInput($_GET['status'] ?? '');

    $where = ["gr.deleted_at IS NULL"];
    $params = [];

    if (!empty($dateFrom)) { $where[] = "gr.receipt_date >= :df"; $params[':df'] = $dateFrom; $filtersApplied['Date From'] = $dateFrom; }
    if (!empty($dateTo)) { $where[] = "gr.receipt_date <= :dt"; $params[':dt'] = $dateTo; $filtersApplied['Date To'] = $dateTo; }
    if (!empty($supplierId)) {
        $where[] = "gr.supplier_id = :sup";
        $params[':sup'] = $supplierId;
        $sName = $db->query("SELECT name FROM suppliers WHERE id = {$supplierId}")->fetchColumn();
        $filtersApplied['Supplier'] = $sName ?: "#{$supplierId}";
    }
    if (!empty($poId)) {
        $where[] = "gr.purchase_order_id = :po";
        $params[':po'] = $poId;
        $poNo = $db->query("SELECT po_no FROM purchase_orders WHERE id = {$poId}")->fetchColumn();
        $filtersApplied['Purchase Order'] = $poNo ?: "#{$poId}";
    }
    if (!empty($status)) { $where[] = "gr.status = :st"; $params[':st'] = $status; $filtersApplied['Status'] = ucfirst($status); }

    $sql = "
        SELECT 
            gr.grn_no,
            po.po_no,
            s.name AS supplier_name,
            gr.receipt_date,
            u.full_name AS receiver_name,
            (SELECT COALESCE(SUM(received_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_rec_qty,
            (SELECT COALESCE(SUM(rejected_qty), 0) FROM goods_receipt_items WHERE goods_receipt_id = gr.id) AS total_rej_qty,
            gr.status
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN suppliers s ON s.id = gr.supplier_id
        LEFT JOIN users u ON u.id = gr.received_by
        WHERE " . implode(' AND ', $where) . "
        ORDER BY gr.receipt_date DESC, gr.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $columns = ['GRN Number', 'PO Number', 'Supplier Vendor', 'Receipt Date', 'Received By', 'Status', 'Accepted Qty', 'Rejected Qty'];
    $totRec = 0;
    $totRej = 0;

    foreach ($records as $r) {
        $totRec += (float)$r['total_rec_qty'];
        $totRej += (float)$r['total_rej_qty'];
        $rows[] = [
            $r['grn_no'],
            $r['po_no'],
            $r['supplier_name'],
            formatDate($r['receipt_date'], 'd M Y'),
            $r['receiver_name'] ?? '-',
            ucfirst($r['status']),
            '+' . number_format((float)$r['total_rec_qty'], 0),
            number_format((float)$r['total_rej_qty'], 0)
        ];
    }
    $totals = [
        'Total Accepted Units' => '+' . number_format($totRec, 0),
        'Total Rejected Units' => number_format($totRej, 0)
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($reportTitle) ?> | <?= e($companyName) ?></title>
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/bootstrap-icons.css') ?>">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #0f172a;
            background-color: #f8fafc;
            font-size: 13px;
        }
        .print-container {
            max-width: 1020px;
            margin: 20px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .filter-badge {
            display: inline-block;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-right: 6px;
            margin-bottom: 4px;
        }
        .table-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .table-print th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 600;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px 10px;
        }
        .table-print td {
            border-bottom: 1px solid #e2e8f0;
            padding: 7px 10px;
        }
        .no-print {
            margin-bottom: 20px;
        }
        @media print {
            body {
                background: #ffffff;
            }
            .print-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
            a {
                text-decoration: none !important;
                color: #000000 !important;
            }
        }
    </style>
</head>
<body>

<div class="print-container">
    <!-- Toolbar on Screen -->
    <div class="no-print d-flex justify-content-between align-items-center bg-light p-3 rounded border mb-4">
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 fw-semibold">
                <i class="bi bi-printer me-1"></i> Print / Save as PDF
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary btn-sm ms-2">
                <i class="bi bi-x-lg me-1"></i> Close Window
            </button>
        </div>
        <div class="small text-muted font-monospace">
            Generated: <?= e($generatedDate) ?>
        </div>
    </div>

    <!-- Official Report Document Header -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col-8">
                <?php if (!empty($companyLogo) && file_exists(LOGO_UPLOAD_DIR . $companyLogo)): ?>
                    <img src="<?= asset('uploads/logos/' . $companyLogo) ?>" alt="<?= e($companyName) ?>" style="max-height: 45px; max-width: 180px; object-fit: contain;" class="mb-2">
                <?php endif; ?>
                <h3 class="fw-bold mb-0 text-dark"><?= e($companyName) ?></h3>
                <div class="small text-muted mt-1">
                    <?php if (!empty($companyAddress)): ?>
                        <span><?= e($companyAddress) ?> &bull; </span>
                    <?php endif; ?>
                    <?php if (!empty($companyEmail)): ?>
                        <span><?= e($companyEmail) ?> &bull; </span>
                    <?php endif; ?>
                    <?php if (!empty($companyPhone)): ?>
                        <span><?= e($companyPhone) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-4 text-end">
                <h5 class="fw-bold text-primary mb-1 text-uppercase" style="letter-spacing: 0.5px;"><?= e($reportTitle) ?></h5>
                <div class="small text-muted font-monospace">Generated: <?= e($generatedDate) ?></div>
                <div class="small text-muted">Audited by: <strong><?= e($user['full_name']) ?></strong></div>
            </div>
        </div>
    </div>

    <!-- Active Filters Summary Strip -->
    <div class="mb-3 p-2 bg-light rounded border">
        <span class="small fw-bold text-dark me-2">Active Criteria:</span>
        <?php if (!empty($filtersApplied)): ?>
            <?php foreach ($filtersApplied as $fKey => $fVal): ?>
                <span class="filter-badge">
                    <strong><?= e($fKey) ?>:</strong> <?= e($fVal) ?>
                </span>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="small text-muted font-monospace">All active records (No filters applied)</span>
        <?php endif; ?>
    </div>

    <!-- Report Table Data -->
    <table class="table-print">
        <thead>
            <tr>
                <?php foreach ($columns as $idx => $col): ?>
                    <th class="<?= ($idx >= count($columns) - 1) ? 'text-end' : '' ?>"><?= e($col) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php foreach ($r as $idx => $val): ?>
                            <td class="<?= ($idx >= count($r) - 1) ? 'text-end font-monospace fw-bold' : '' ?>"><?= e($val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= count($columns) ?>" class="text-center py-4 text-muted">
                        No records found for the selected criteria.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($totals)): ?>
            <tfoot>
                <?php foreach ($totals as $tLabel => $tVal): ?>
                    <tr style="border-top: 2px solid #0f172a; font-weight: bold; background-color: #f8fafc;">
                        <td colspan="<?= count($columns) - 1 ?>" class="text-end"><?= e($tLabel) ?>:</td>
                        <td class="text-end font-monospace"><?= e($tVal) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tfoot>
        <?php endif; ?>
    </table>

    <!-- Footer Timestamp & Signoff -->
    <div class="mt-4 pt-3 border-top d-flex justify-content-between text-muted small" style="font-size: 11px;">
        <div>
            Report generated by <?= e(APP_NAME) ?> &bull; Confidential & Internal Procurement Audit Document
        </div>
        <div>
            Page 1 of 1
        </div>
    </div>
</div>

</body>
</html>
