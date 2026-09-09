<?php
/**
 * Update Supplier Handler (POST)
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/suppliers/index.php');
}

// Permission check
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to edit suppliers.';
    redirect('modules/suppliers/index.php');
}

$supplierId = (int)($_POST['id'] ?? 0);
if ($supplierId <= 0) {
    $_SESSION['flash_error'] = 'Invalid supplier ID.';
    redirect('modules/suppliers/index.php');
}

// Verify CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
    $_SESSION['old_input'] = $_POST;
    redirect('modules/suppliers/edit.php?id=' . $supplierId);
}

$user = currentUser();
$db = getDb();

// Check if supplier exists and is not soft deleted
$supplier = null;
try {
    $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = :id AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch();
} catch (Exception $e) {
    error_log('Error finding supplier: ' . $e->getMessage());
}

if (!$supplier) {
    $_SESSION['flash_error'] = 'Supplier record not found or has been deleted.';
    redirect('modules/suppliers/index.php');
}

// Capture and sanitize input
$name = sanitizeInput($_POST['name'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$taxNumber = sanitizeInput($_POST['tax_number'] ?? '');
$website = sanitizeInput($_POST['website'] ?? '');
$address = sanitizeInput($_POST['address'] ?? '');
$city = sanitizeInput($_POST['city'] ?? '');
$state = sanitizeInput($_POST['state'] ?? '');
$country = sanitizeInput($_POST['country'] ?? '');
$postalCode = sanitizeInput($_POST['postal_code'] ?? '');
$bankName = sanitizeInput($_POST['bank_name'] ?? '');
$bankAccountName = sanitizeInput($_POST['bank_account_name'] ?? '');
$bankAccountNumber = sanitizeInput($_POST['bank_account_number'] ?? '');
$bankRouting = sanitizeInput($_POST['bank_routing'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');
$status = sanitizeInput($_POST['status'] ?? 'active');

// Validation
$errors = [];
if (empty($name)) {
    $errors[] = 'Supplier company name is required.';
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Company email address is invalid.';
}
if (!in_array($status, ['active', 'inactive', 'blacklisted'])) {
    $status = 'active';
}

if (!empty($errors)) {
    $_SESSION['flash_error'] = implode('<br>', $errors);
    $_SESSION['old_input'] = $_POST;
    redirect('modules/suppliers/edit.php?id=' . $supplierId);
}

try {
    $updateStmt = $db->prepare("
        UPDATE suppliers SET
            name = :name,
            email = :email,
            phone = :phone,
            address = :address,
            city = :city,
            state = :state,
            country = :country,
            postal_code = :postal_code,
            tax_number = :tax_number,
            website = :website,
            bank_name = :bank_name,
            bank_account_name = :bank_account_name,
            bank_account_number = :bank_account_number,
            bank_routing = :bank_routing,
            notes = :notes,
            status = :status,
            updated_at = NOW()
        WHERE id = :id AND deleted_at IS NULL
    ");

    $updateStmt->execute([
        ':id'                  => $supplierId,
        ':name'                => $name,
        ':email'               => !empty($email) ? $email : null,
        ':phone'               => !empty($phone) ? $phone : null,
        ':address'             => !empty($address) ? $address : null,
        ':city'                => !empty($city) ? $city : null,
        ':state'               => !empty($state) ? $state : null,
        ':country'             => !empty($country) ? $country : null,
        ':postal_code'         => !empty($postalCode) ? $postalCode : null,
        ':tax_number'          => !empty($taxNumber) ? $taxNumber : null,
        ':website'             => !empty($website) ? $website : null,
        ':bank_name'           => !empty($bankName) ? $bankName : null,
        ':bank_account_name'   => !empty($bankAccountName) ? $bankAccountName : null,
        ':bank_account_number' => !empty($bankAccountNumber) ? $bankAccountNumber : null,
        ':bank_routing'        => !empty($bankRouting) ? $bankRouting : null,
        ':notes'               => !empty($notes) ? $notes : null,
        ':status'              => $status
    ]);

    logActivity($user['id'], 'update_supplier', "Updated supplier {$supplier['supplier_code']} - {$name}");

    $_SESSION['flash_success'] = "Supplier '{$name}' ({$supplier['supplier_code']}) updated successfully.";
    redirect('modules/suppliers/view.php?id=' . $supplierId);

} catch (Exception $e) {
    error_log('Error updating supplier: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error updating supplier: ' . $e->getMessage();
    $_SESSION['old_input'] = $_POST;
    redirect('modules/suppliers/edit.php?id=' . $supplierId);
}
