<?php
/**
 * Print Purchase Order (A4 Document Format)
 * Procurement Management CMS - Phase 04
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$poId = (int)($_GET['id'] ?? 0);
if ($poId <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
}

$db = getDb();

// 1. Fetch Purchase Order details
$po = null;
try {
    $stmt = $db->prepare("
        SELECT po.*,
               pr.request_no,
               pr.purpose AS pr_purpose,
               d.name AS department_name,
               q.quotation_no,
               q.quotation_date,
               s.name AS supplier_name,
               s.supplier_code,
               s.email AS supplier_email,
               s.phone AS supplier_phone,
               s.tax_number AS supplier_tax,
               s.address AS supplier_address,
               s.city AS supplier_city,
               s.country AS supplier_country,
               u_cr.full_name AS creator_name,
               u_app.full_name AS approver_name
        FROM purchase_orders po
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        LEFT JOIN departments d ON d.id = pr.department_id
        JOIN quotations q ON q.id = po.quotation_id
        JOIN suppliers s ON s.id = po.supplier_id
        LEFT JOIN users u_cr ON u_cr.id = po.created_by
        LEFT JOIN users u_app ON u_app.id = po.approved_by
        WHERE po.id = :id AND po.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $poId]);
    $po = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error fetching PO for print: ' . $e->getMessage());
}

if (!$po) {
    setFlash('error', 'Purchase Order not found.');
    redirect('modules/purchase_orders/index.php');
}

// 2. Fetch Line Items
$items = [];
try {
    $itemStmt = $db->prepare("SELECT * FROM purchase_order_items WHERE purchase_order_id = :po_id ORDER BY id ASC");
    $itemStmt->execute([':po_id' => $poId]);
    $items = $itemStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching items for print: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order <?= e($po['po_no']) ?> - Procurement Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
        }
        .print-container {
            max-width: 850px;
            margin: 30px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .header-logo {
            font-size: 24px;
            font-weight: 800;
            color: #0d6efd;
            letter-spacing: -0.5px;
        }
        .table-po th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 2px solid #cbd5e1;
        }
        .table-po td {
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }
        .totals-table td {
            padding: 4px 8px;
        }
        .signature-box {
            border-top: 1px dashed #94a3b8;
            padding-top: 8px;
            margin-top: 45px;
            text-align: center;
        }
        @media print {
            body {
                background: #ffffff;
                color: #000000;
                font-size: 12px;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                max-width: 100%;
                margin: 0;
                padding: 10px 20px;
                box-shadow: none;
                border-radius: 0;
            }
            .table-po th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            a {
                text-decoration: none !important;
                color: #000000 !important;
            }
        }
    </style>
</head>
<body>

<!-- Print Actions Toolbar (Hidden in Print) -->
<div class="container max-w-lg my-3 no-print" style="max-width: 850px;">
    <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm border">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-printer-fill text-primary fs-5"></i>
            <span class="fw-bold">Purchase Order Document Preview</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print();" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-printer"></i>
                <span>Print / Save PDF</span>
            </button>
            <a href="<?= url('modules/purchase_orders/view.php?id=' . $po['id']) ?>" class="btn btn-outline-secondary">
                Close
            </a>
        </div>
    </div>
</div>

<div class="print-container">
    <!-- Document Header -->
    <div class="row pb-4 mb-4 border-bottom">
        <div class="col-7">
            <div class="header-logo mb-1">
                <i class="bi bi-shield-check me-1"></i> PROCURE<span class="text-dark">CMS</span>
            </div>
            <div class="fw-semibold text-dark">Procurement & Supply Chain Operations</div>
            <div class="text-muted small">
                Corporate Headquarters &bull; Central Logistics Center<br>
                Email: procurement@organization.com &bull; Tel: +1 (555) 019-2834
            </div>
        </div>
        <div class="col-5 text-end">
            <h2 class="h3 fw-bold text-dark font-monospace mb-1"><?= e($po['po_no']) ?></h2>
            <div class="badge <?= $po['status'] === 'sent' ? 'bg-success' : ($po['status'] === 'approved' ? 'bg-primary' : 'bg-secondary') ?> px-3 py-2 text-uppercase mb-2">
                STATUS: <?= strtoupper(str_replace('_', ' ', $po['status'])) ?>
            </div>
            <div class="text-muted small">
                <strong>Date Issued:</strong> <?= formatDate($po['po_date'], 'd F Y') ?><br>
                <strong>Delivery Date:</strong> <?= !empty($po['expected_delivery_date']) ? formatDate($po['expected_delivery_date'], 'd F Y') : 'As Scheduled' ?>
            </div>
        </div>
    </div>

    <!-- Vendor & Shipping Details -->
    <div class="row mb-4">
        <div class="col-6">
            <div class="p-3 bg-light rounded border h-100">
                <div class="text-uppercase text-muted fw-bold small mb-2">
                    <i class="bi bi-building me-1"></i> Vendor / Supplier
                </div>
                <div class="fw-bold text-dark fs-6"><?= e($po['supplier_name']) ?></div>
                <div class="text-muted small font-monospace mb-2">Vendor Code: <?= e($po['supplier_code']) ?></div>
                <div class="small text-muted">
                    <?= nl2br(e($po['supplier_address'] ?? '')) ?><br>
                    <?= e($po['supplier_city'] ?? '') ?><?= (!empty($po['supplier_city']) && !empty($po['supplier_country'])) ? ', ' : '' ?><?= e($po['supplier_country'] ?? '') ?><br>
                    <strong>Email:</strong> <?= e($po['supplier_email'] ?: 'N/A') ?><br>
                    <strong>Phone:</strong> <?= e($po['supplier_phone'] ?: 'N/A') ?><br>
                    <strong>Tax ID:</strong> <?= e($po['supplier_tax'] ?: 'N/A') ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="p-3 bg-light rounded border h-100">
                <div class="text-uppercase text-muted fw-bold small mb-2">
                    <i class="bi bi-geo-alt me-1"></i> Ship To / Delivery Location
                </div>
                <div class="fw-bold text-dark">
                    <?= e($po['department_name'] ? $po['department_name'] . ' Department' : 'Main Receiving Center') ?>
                </div>
                <div class="small text-muted mb-3">
                    <?= nl2br(e($po['delivery_address'] ?: "Central Receiving Dock, Building B\nProcurement Distribution Center")) ?>
                </div>
                <div class="small text-muted border-top pt-2">
                    <strong>Requisition Ref:</strong> <?= e($po['request_no']) ?><br>
                    <strong>Quotation Ref:</strong> <?= e($po['quotation_no']) ?> (<?= formatDate($po['quotation_date'], 'd M Y') ?>)
                </div>
            </div>
        </div>
    </div>

    <!-- Line Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-po align-middle">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 40%;">Description / Item Name</th>
                    <th class="text-center" style="width: 12%;">Qty</th>
                    <th class="text-end" style="width: 15%;">Unit Price</th>
                    <th class="text-center" style="width: 10%;">Tax %</th>
                    <th class="text-end" style="width: 18%;">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                    <tr>
                        <td class="text-muted small"><?= $idx + 1 ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= e($item['item_name']) ?></div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="text-muted small" style="font-size: 11px;"><?= e($item['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="font-monospace fw-semibold"><?= number_format((float)$item['quantity'], 2) ?></span>
                            <span class="text-muted small" style="font-size: 10px;"><?= e($item['unit']) ?></span>
                        </td>
                        <td class="text-end font-monospace">
                            <?= formatCurrency($item['unit_price']) ?>
                        </td>
                        <td class="text-center font-monospace small">
                            <?= number_format((float)$item['tax_percent'], 1) ?>%
                        </td>
                        <td class="text-end font-monospace fw-bold text-dark">
                            <?= formatCurrency($item['line_total']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Terms & Financial Totals Breakdown -->
    <div class="row mb-4">
        <div class="col-7">
            <div class="p-3 bg-light rounded border h-100 small">
                <div class="fw-bold text-dark mb-1">Commercial & Delivery Terms:</div>
                <div class="mb-2"><strong>Payment Terms:</strong> <?= e($po['payment_terms'] ?: 'Net 30 Days') ?></div>
                <div class="mb-2"><strong>Shipping Terms:</strong> <?= e($po['delivery_terms'] ?: 'Standard Delivery / FOB') ?></div>
                <?php if (!empty($po['notes'])): ?>
                    <div class="border-top pt-2 mt-2">
                        <strong>Special Vendor Instructions:</strong><br>
                        <?= nl2br(e($po['notes'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-5">
            <table class="table table-sm table-borderless totals-table mb-0">
                <tr>
                    <td class="text-muted">Subtotal:</td>
                    <td class="text-end font-monospace fw-semibold text-dark"><?= formatCurrency($po['subtotal']) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Estimated Tax / VAT:</td>
                    <td class="text-end font-monospace text-dark">+<?= formatCurrency($po['tax_amount']) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Discounts:</td>
                    <td class="text-end font-monospace text-danger">-<?= formatCurrency($po['discount_amount']) ?></td>
                </tr>
                <tr class="border-top border-dark border-2">
                    <td class="fw-bold text-dark fs-6 pt-2">Grand Total:</td>
                    <td class="text-end font-monospace fw-bold text-primary fs-5 pt-2"><?= formatCurrency($po['grand_total']) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Authorization & Signatures -->
    <div class="row pt-3 mt-4">
        <div class="col-4">
            <div class="signature-box">
                <div class="fw-bold text-dark small"><?= e($po['creator_name'] ?? 'Procurement Officer') ?></div>
                <div class="text-muted small" style="font-size: 11px;">Prepared By (Procurement)</div>
                <div class="text-muted small" style="font-size: 10px;"><?= formatDate($po['created_at'], 'd M Y') ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="signature-box">
                <div class="fw-bold text-dark small"><?= e($po['approver_name'] ?? 'Management Approval') ?></div>
                <div class="text-muted small" style="font-size: 11px;">Authorized Approver</div>
                <div class="text-muted small" style="font-size: 10px;"><?= !empty($po['approved_at']) ? formatDate($po['approved_at'], 'd M Y') : 'Date / Signature' ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="signature-box">
                <div class="fw-bold text-dark small"><?= e($po['supplier_name']) ?></div>
                <div class="text-muted small" style="font-size: 11px;">Vendor Acceptance & Stamp</div>
                <div class="text-muted small" style="font-size: 10px;">Authorized Signature / Date</div>
            </div>
        </div>
    </div>

    <!-- Document Footer -->
    <div class="text-center text-muted border-top pt-3 mt-4" style="font-size: 10px;">
        This document represents an official Purchase Order contract generated by the Procurement Management CMS.
        All deliveries must reference Purchase Order Number <strong><?= e($po['po_no']) ?></strong> on all shipping crates, bills of lading, and invoices.
    </div>
</div>

</body>
</html>
