<?php
/**
 * Store Supplier Handler (POST)
 * Procurement Management CMS - Phase 03
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/suppliers/index.php');
}

// Permission check
if (!userHasRole('administrator') && !userHasRole('manager') && !userHasRole('procurement-officer')) {
    $_SESSION['flash_error'] = 'Access denied. You do not have permission to add suppliers.';
    redirect('modules/suppliers/index.php');
}

// Verify CSRF Token
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Security validation failed (invalid CSRF token). Please try again.';
    $_SESSION['old_input'] = $_POST;
    redirect('modules/suppliers/create.php');
}

$user = currentUser();
$db = getDb();

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

// Primary Contact Info
$contactName = sanitizeInput($_POST['contact_name'] ?? '');
$contactJobTitle = sanitizeInput($_POST['contact_job_title'] ?? '');
$contactEmail = sanitizeInput($_POST['contact_email'] ?? '');
$contactPhone = sanitizeInput($_POST['contact_phone'] ?? '');

// Validation
$errors = [];
if (empty($name)) {
    $errors[] = 'Supplier company name is required.';
}
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Company email address is invalid.';
}
if (!empty($contactEmail) && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Contact person email address is invalid.';
}
if (!in_array($status, ['active', 'inactive', 'blacklisted'])) {
    $status = 'active';
}

if (!empty($errors)) {
    $_SESSION['flash_error'] = implode('<br>', $errors);
    $_SESSION['old_input'] = $_POST;
    redirect('modules/suppliers/create.php');
}

try {
    $db->beginTransaction();

    // Generate unique Supplier Code (SUP-000001)
    $maxStmt = $db->query("SELECT MAX(id) FROM suppliers");
    $maxId = (int)$maxStmt->fetchColumn();
    $supplierCode = sprintf('SUP-%06d', $maxId + 1);

    // Double check code uniqueness
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM suppliers WHERE supplier_code = :code");
    $checkStmt->execute([':code' => $supplierCode]);
    if ($checkStmt->fetchColumn() > 0) {
        $supplierCode = sprintf('SUP-%06d', $maxId + rand(2, 99));
    }

    $insertStmt = $db->prepare("
        INSERT INTO suppliers (
            supplier_code, name, email, phone, address, city, state, country, postal_code,
            tax_number, website, bank_name, bank_account_name, bank_account_number, bank_routing,
            notes, status, created_at, updated_at
        ) VALUES (
            :code, :name, :email, :phone, :address, :city, :state, :country, :postal_code,
            :tax_number, :website, :bank_name, :bank_account_name, :bank_account_number, :bank_routing,
            :notes, :status, NOW(), NOW()
        )
    ");

    $insertStmt->execute([
        ':code'                => $supplierCode,
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

    $supplierId = (int)$db->lastInsertId();

    // Insert primary contact person if provided
    if (!empty($contactName) || !empty($contactEmail) || !empty($contactPhone)) {
        $contactStmt = $db->prepare("
            INSERT INTO supplier_contacts (
                supplier_id, name, job_title, email, phone, is_primary, created_at
            ) VALUES (
                :supplier_id, :name, :job_title, :email, :phone, 1, NOW()
            )
        ");
        $contactStmt->execute([
            ':supplier_id' => $supplierId,
            ':name'        => !empty($contactName) ? $contactName : 'Primary Contact',
            ':job_title'   => !empty($contactJobTitle) ? $contactJobTitle : null,
            ':email'       => !empty($contactEmail) ? $contactEmail : null,
            ':phone'       => !empty($contactPhone) ? $contactPhone : null
        ]);
    }

    $db->commit();

    logActivity($user['id'], 'create_supplier', "Registered supplier {$supplierCode} - {$name}");

    $_SESSION['flash_success'] = "Supplier '{$name}' ({$supplierCode}) created successfully.";
    redirect('modules/suppliers/view.php?id=' . $supplierId);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error creating supplier: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Database error creating supplier: ' . $e->getMessage();
    $_SESSION['old_input'] = $_POST;
    redirect('modules/suppliers/create.php');
}
