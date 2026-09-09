<?php
/**
 * Store Supplier Contact Handler (POST)
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/suppliers/index.php');
}

// Permission check
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to manage supplier contacts.';
    redirect('modules/suppliers/index.php');
}

$supplierId = (int)($_POST['supplier_id'] ?? 0);
if ($supplierId <= 0) {
    $_SESSION['flash_error'] = 'Invalid supplier ID.';
    redirect('modules/suppliers/index.php');
}

// Verify CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token).';
    redirect('modules/suppliers/view.php?id=' . $supplierId);
}

$user = currentUser();
$db = getDb();

// Check if supplier exists
$supplier = null;
try {
    $stmt = $db->prepare("SELECT id, name, supplier_code FROM suppliers WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error finding supplier for contact: ' . $e->getMessage());
}

if (!$supplier) {
    $_SESSION['flash_error'] = 'Supplier record not found.';
    redirect('modules/suppliers/index.php');
}

$name = sanitizeInput($_POST['name'] ?? '');
$jobTitle = sanitizeInput($_POST['job_title'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$mobile = sanitizeInput($_POST['mobile'] ?? '');
$isPrimary = isset($_POST['is_primary']) && $_POST['is_primary'] == '1' ? 1 : 0;

if (empty($name)) {
    $_SESSION['flash_error'] = 'Contact person name is required.';
    redirect('modules/suppliers/view.php?id=' . $supplierId);
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Invalid email address for contact person.';
    redirect('modules/suppliers/view.php?id=' . $supplierId);
}

try {
    $db->beginTransaction();

    // If set as primary, reset other contacts for this supplier
    if ($isPrimary === 1) {
        $resetStmt = $db->prepare("UPDATE supplier_contacts SET is_primary = 0 WHERE supplier_id = :sid");
        $resetStmt->execute([':sid' => $supplierId]);
    } else {
        // If this is the FIRST contact for this supplier, automatically make it primary
        $countStmt = $db->prepare("SELECT COUNT(*) FROM supplier_contacts WHERE supplier_id = :sid");
        $countStmt->execute([':sid' => $supplierId]);
        if ((int)$countStmt->fetchColumn() === 0) {
            $isPrimary = 1;
        }
    }

    $insertStmt = $db->prepare("
        INSERT INTO supplier_contacts (
            supplier_id, name, job_title, email, phone, mobile, is_primary, created_at
        ) VALUES (
            :supplier_id, :name, :job_title, :email, :phone, :mobile, :is_primary, NOW()
        )
    ");

    $insertStmt->execute([
        ':supplier_id' => $supplierId,
        ':name'        => $name,
        ':job_title'   => !empty($jobTitle) ? $jobTitle : null,
        ':email'       => !empty($email) ? $email : null,
        ':phone'       => !empty($phone) ? $phone : null,
        ':mobile'      => !empty($mobile) ? $mobile : null,
        ':is_primary'  => $isPrimary
    ]);

    $db->commit();

    logActivity($user['id'], 'add_supplier_contact', "Added contact {$name} for supplier {$supplier['supplier_code']}");

    $_SESSION['flash_success'] = "Contact person '{$name}' added successfully.";
    redirect('modules/suppliers/view.php?id=' . $supplierId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error adding supplier contact: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error adding contact: ' . $e->getMessage();
    redirect('modules/suppliers/view.php?id=' . $supplierId);
}
