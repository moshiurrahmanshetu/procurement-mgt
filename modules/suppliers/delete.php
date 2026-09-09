<?php
/**
 * Delete Supplier Handler (Soft Delete POST)
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/suppliers/index.php');
}

// Permission check: Admin, Manager, Procurement Officer
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to delete suppliers.';
    redirect('modules/suppliers/index.php');
}

// Verify CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
    redirect('modules/suppliers/index.php');
}

$supplierId = (int)($_POST['id'] ?? 0);
if ($supplierId <= 0) {
    $_SESSION['flash_error'] = 'Invalid supplier ID.';
    redirect('modules/suppliers/index.php');
}

$user = currentUser();
$db = getDb();

try {
    $stmt = $db->prepare("SELECT id, supplier_code, name FROM suppliers WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch();

    if (!$supplier) {
        $_SESSION['flash_error'] = 'Supplier record not found or already deleted.';
        redirect('modules/suppliers/index.php');
    }

    // Soft delete
    $delStmt = $db->prepare("UPDATE suppliers SET deleted_at = NOW() WHERE id = :id");
    $delStmt->execute([':id' => $supplierId]);

    logActivity($user['id'], 'delete_supplier', "Soft deleted supplier {$supplier['supplier_code']} - {$supplier['name']}");

    $_SESSION['flash_success'] = "Supplier '{$supplier['name']}' ({$supplier['supplier_code']}) deleted successfully.";
    redirect('modules/suppliers/index.php');

} catch (Exception $e) {
    error_log('Error deleting supplier: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error deleting supplier: ' . $e->getMessage();
    redirect('modules/suppliers/index.php');
}
