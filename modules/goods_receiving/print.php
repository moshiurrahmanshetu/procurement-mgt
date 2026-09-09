<?php
/**
 * Goods Receipt Note (GRN) A4 Printable View
 * Procurement Management CMS - Phase 05
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

$user = currentUser();
$userId = currentUserId();
$db = getDb();

$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewAll = $isAdmin || $isManager || $isOfficer;

$grnId = (int)($_GET['id'] ?? 0);
if ($grnId <= 0) {
    die('Invalid Goods Receipt ID.');
}

// Fetch GRN record with relations
$grn = null;
try {
    $stmt = $db->prepare("
        SELECT gr.*,
               po.po_no,
               po.po_date,
               po.status AS po_status,
               po.delivery_address AS po_delivery_address,
               po.delivery_terms AS po_delivery_terms,
               pr.request_no,
               pr.requested_by AS pr_requested_by,
               d.name AS department_name,
               s.name AS supplier_name,
               s.supplier_code,
               s.email AS supplier_email,
               s.phone AS supplier_phone,
               s.tax_number AS supplier_tax,
               s.address AS supplier_address,
               s.city AS supplier_city,
               s.country AS supplier_country,
               u_rec.full_name AS receiver_name
        FROM goods_receipts gr
        JOIN purchase_orders po ON po.id = gr.purchase_order_id
        JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        LEFT JOIN departments d ON d.id = pr.department_id
        JOIN suppliers s ON s.id = gr.supplier_id
        LEFT JOIN users u_rec ON u_rec.id = gr.received_by
        WHERE gr.id = :id AND gr.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([':id' => $grnId]);
    $grn = $stmt->fetch();
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

if (!$grn) {
    die('Goods Receipt record not found.');
}

if (!$canViewAll && (int)$grn['pr_requested_by'] !== $userId) {
    die('Access Denied.');
}

// Fetch line items
$items = [];
$totalAccepted = 0.0;
$totalRejected = 0.0;
try {
    $itemStmt = $db->prepare("
        SELECT gri.*
        FROM goods_receipt_items gri
        WHERE gri.goods_receipt_id = :grn_id
        ORDER BY gri.id ASC
    ");
    $itemStmt->execute([':grn_id' => $grnId]);
    $items = $itemStmt->fetchAll();

    foreach ($items as $item) {
        $totalAccepted += (float)$item['received_qty'];
        $totalRejected += (float)$item['rejected_qty'];
    }
} catch (Exception $e) {
    die('Error fetching items: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goods Receipt Note - <?= e($grn['grn_no']) ?></title>
    <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/bootstrap-icons.min.css') ?>" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            color: #1e293b;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
        }
        .print-container {
            max-width: 900px;
            margin: 20px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .doc-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #0f172a;
        }
        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
        }
        .table-print th {
            background-color: #f1f5f9 !important;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #cbd5e1;
        }
        .table-print td, .table-print th {
            padding: 8px 12px;
            border-color: #e2e8f0;
        }
        .sign-box {
            border-top: 1px solid #94a3b8;
            margin-top: 50px;
            padding-top: 6px;
            text-align: center;
        }
        @media print {
            body {
                background: #ffffff;
                color: #000000;
                font-size: 11pt;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            .table-print th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page {
                size: A4 portrait;
                margin: 15mm;
            }
        }
    </style>
</head>
<body>

<!-- Action Bar (Hidden on Print) -->
<div class="no-print bg-dark text-white py-2 px-3 mb-3 shadow-sm">
    <div class="container-fluid d-flex justify-content-between align-items-center" style="max-width: 900px;">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary font-monospace"><?= e($grn['grn_no']) ?></span>
            <span class="small opacity-75">Goods Receipt Note Document Preview</span>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-printer"></i> Print Document
            </button>
            <button onclick="window.close()" class="btn btn-outline-light btn-sm">Close</button>
        </div>
    </div>
</div>

<div class="print-container">
    <!-- Header: Company Logo & Document Header -->
    <div class="row align-items-start border-bottom pb-3 mb-4">
        <div class="col-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div style="width: 36px; height: 36px; background-color: #2563eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: bold; font-size: 18px;">
                    P
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">ProcureCMS Enterprise</h5>
                    <span class="text-muted small">Procurement & Warehouse Receiving Division</span>
                </div>
            </div>
            <div class="small text-secondary">
                100 Corporate Parkway, Suite 400 &bull; Logistics Terminal A<br>
                Email: receiving@procurecms.local &bull; Tel: +1 (800) 555-0199
            </div>
        </div>
        <div class="col-5 text-end">
            <div class="doc-title text-uppercase text-primary mb-1">GOODS RECEIPT NOTE</div>
            <div class="h5 font-monospace fw-bold text-dark mb-1"><?= e($grn['grn_no']) ?></div>
            <div class="small">
                Status: <strong class="text-uppercase"><?= e($grn['status']) ?></strong><br>
                Date: <strong class="font-monospace"><?= formatDate($grn['receipt_date'], 'd M Y') ?></strong>
            </div>
        </div>
    </div>

    <!-- Vendor & Order Reference Grid -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="meta-box h-100">
                <div class="text-uppercase text-muted small fw-bold mb-2">Delivered By (Supplier / Vendor)</div>
                <div class="fw-bold text-dark fs-6"><?= e($grn['supplier_name']) ?></div>
                <div class="font-monospace text-muted small mb-1">Code: <?= e($grn['supplier_code']) ?></div>
                <?php if (!empty($grn['supplier_address'])): ?>
                    <div class="small text-secondary mb-1"><?= e($grn['supplier_address']) ?><?= !empty($grn['supplier_city']) ? ', ' . e($grn['supplier_city']) : '' ?></div>
                <?php endif; ?>
                <div class="small text-secondary">
                    Phone: <?= e($grn['supplier_phone'] ?: 'N/A') ?> &bull; Email: <?= e($grn['supplier_email'] ?: 'N/A') ?>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="meta-box h-100">
                <div class="text-uppercase text-muted small fw-bold mb-2">Purchase Order & Waybill Details</div>
                <div class="mb-1">
                    <span class="text-muted small">Purchase Order #:</span>
                    <strong class="font-monospace text-dark"><?= e($grn['po_no']) ?></strong>
                </div>
                <div class="mb-1">
                    <span class="text-muted small">PO Date:</span>
                    <span class="font-monospace text-dark"><?= formatDate($grn['po_date'], 'd M Y') ?></span>
                </div>
                <div class="mb-1">
                    <span class="text-muted small">Requisition #:</span>
                    <span class="font-monospace text-dark"><?= e($grn['request_no']) ?></span> (<?= e($grn['department_name'] ?? 'General') ?>)
                </div>
                <div class="mb-0">
                    <span class="text-muted small">Vendor Delivery Note #:</span>
                    <strong class="font-monospace text-primary"><?= e($grn['delivery_note_no'] ?: 'N/A') ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-print align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">SL</th>
                    <th style="width: 40%;">Item Description</th>
                    <th class="text-center" style="width: 10%;">Unit</th>
                    <th class="text-center" style="width: 12%;">Ordered Qty</th>
                    <th class="text-center" style="width: 15%;">Accepted Qty</th>
                    <th class="text-center" style="width: 10%;">Rejected</th>
                    <th style="width: 18%;">Remarks / Condition</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                    <tr>
                        <td class="text-center text-muted font-monospace"><?= $idx + 1 ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($item['item_name']) ?></div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="small text-muted"><?= e($item['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center font-monospace small"><?= e($item['unit']) ?></td>
                        <td class="text-center font-monospace"><?= number_format((float)$item['ordered_qty'], 2) ?></td>
                        <td class="text-center font-monospace fw-bold text-dark">+<?= number_format((float)$item['received_qty'], 2) ?></td>
                        <td class="text-center font-monospace text-danger"><?= (float)$item['rejected_qty'] > 0 ? '-' . number_format((float)$item['rejected_qty'], 2) : '0.00' ?></td>
                        <td class="small text-secondary"><?= e($item['notes'] ?: 'Good condition') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold table-light">
                    <td colspan="4" class="text-end">Summary Received Quantities:</td>
                    <td class="text-center font-monospace text-primary fs-7">+<?= number_format($totalAccepted, 2) ?></td>
                    <td class="text-center font-monospace text-danger fs-7"><?= $totalRejected > 0 ? '-' . number_format($totalRejected, 2) : '0.00' ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Receiving Notes Section -->
    <?php if (!empty($grn['notes'])): ?>
        <div class="meta-box mb-4">
            <div class="text-uppercase text-muted small fw-bold mb-1">Inspection & Receiving Remarks:</div>
            <div class="small text-secondary"><?= nl2br(e($grn['notes'])) ?></div>
        </div>
    <?php endif; ?>

    <!-- Destination Address -->
    <div class="small text-muted mb-4">
        <strong>Receiving Warehouse Destination:</strong> <?= e($grn['po_delivery_address'] ?: 'Central Procurement Receiving Dock') ?>
    </div>

    <!-- Signatures & Authority Section -->
    <div class="row pt-4 mt-4 g-4">
        <div class="col-4">
            <div class="sign-box">
                <div class="fw-bold text-dark small"><?= e($grn['receiver_name'] ?? 'Receiving Clerk') ?></div>
                <div class="text-muted" style="font-size: 11px;">Warehouse Receiving Officer</div>
                <div class="text-muted font-monospace" style="font-size: 10px;">Date: <?= formatDate($grn['receipt_date'], 'd M Y') ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="sign-box">
                <div class="fw-bold text-dark small">QA / Quality Inspection</div>
                <div class="text-muted" style="font-size: 11px;">Verification & Acceptance</div>
                <div class="text-muted font-monospace" style="font-size: 10px;">Date: _______________</div>
            </div>
        </div>
        <div class="col-4">
            <div class="sign-box">
                <div class="fw-bold text-dark small">Procurement Authority</div>
                <div class="text-muted" style="font-size: 11px;">Store Manager / Approver</div>
                <div class="text-muted font-monospace" style="font-size: 10px;">Date: _______________</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
