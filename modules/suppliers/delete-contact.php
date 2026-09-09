<?php
/**
 * Delete Supplier Contact Handler (POST)
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/suppliers/index.php');
}

// Permission check
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to delete supplier contacts.';
    redirect('modules/suppliers/index.php');
}

$supplierId = (int)($_POST['supplier_id'] ?? 0);
$contactId = (int)($_POST['contact_id'] ?? 0);

if ($supplierId <= 0 || $contactId <= 0) {
    $_SESSION['flash_error'] = 'Invalid contact parameters.';
    redirect('modules/suppliers/index.php');
}

// Verify CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
    redirect('modules/suppliers/view.php?id=' . $supplierId);
}

$user = currentUser();
$db = getDb();

try {
    // Check if contact exists
    $stmt = $db->prepare("SELECT * FROM supplier_contacts WHERE id = :cid AND supplier_id = :sid LIMIT 1");
    $stmt->execute([':cid' => $contactId, ':sid' => $supplierId]);
    $contact = $stmt->fetch();

    if (!$contact) {
        $_SESSION['flash_error'] = 'Contact person not found.';
        redirect('modules/suppliers/view.php?id=' . $supplierId);
    }

    $db->beginTransaction();

    $delStmt = $db->prepare("DELETE FROM supplier_contacts WHERE id = :cid AND supplier_id = :sid");
    $delStmt->execute([':cid' => $contactId, ':sid' => $supplierId]);

    // If deleted contact was primary, promote another contact if available
    if ((int)$contact['is_primary'] === 1) {
        $promoteStmt = $db->prepare("UPDATE supplier_contacts SET is_primary = 1 WHERE supplier_id = :sid ORDER BY id ASC LIMIT 1");
        $promoteStmt->execute([':sid' => $supplierId]);
    }

    $db->commit();

    logActivity($user['id'], 'delete_supplier_contact', "Deleted contact {$contact['name']} from supplier ID {$supplierId}");

    $_SESSION['flash_success'] = "Contact '{$contact['name']}' removed successfully.";
    redirect('modules/suppliers/view.php?id=' . $supplierId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error deleting supplier contact: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error removing contact: ' . $e->getMessage();
    redirect('modules/suppliers/view.php?id=' . $supplierId);
}
