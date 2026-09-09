<?php
/**
 * Delete Purchase Order (Soft Delete)
 * Procurement Management CMS - Phase 04
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

// 1. RBAC Check: Only Procurement Officers & Administrators can delete draft POs
$isAdmin = userHasRole('administrator');
$isOfficer = userHasRole('procurement-officer');
if (!$isAdmin && !$isOfficer) {
    setFlash('error', 'Access Denied: Only Procurement Officers and Administrators can delete Purchase Orders.');
    redirect('modules/purchase_orders/index.php');
}

// 2. Request Method & CSRF Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/purchase_orders/index.php');
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid security token.');
    redirect('modules/purchase_orders/index.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid Purchase Order ID.');
    redirect('modules/purchase_orders/index.php');
}

$db = getDb();

try {
    $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = :id AND deleted_at IS NULL");
    $stmt->execute([':id' => $id]);
    $po = $stmt->fetch();

    if (!$po) {
        setFlash('error', 'Purchase Order not found.');
        redirect('modules/purchase_orders/index.php');
    }

    // Business Rule Check: Only draft POs can be deleted
    if ($po['status'] !== 'draft') {
        setFlash('error', 'Only draft Purchase Orders can be deleted.');
        redirect('modules/purchase_orders/view.php?id=' . $id);
    }

    // Soft delete
    $upd = $db->prepare("UPDATE purchase_orders SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id");
    $upd->execute([':id' => $id]);

    logActivity("Deleted Draft Purchase Order {$po['po_no']}", 'purchase_orders', $id);

    setFlash('success', "Draft Purchase Order {$po['po_no']} was deleted successfully.");
    redirect('modules/purchase_orders/index.php');

} catch (Exception $e) {
    error_log('Delete PO Error: ' . $e->getMessage());
    setFlash('error', 'Error deleting Purchase Order: ' . $e->getMessage());
    redirect('modules/purchase_orders/view.php?id=' . $id);
}
