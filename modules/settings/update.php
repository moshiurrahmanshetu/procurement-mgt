<?php
/**
 * System Settings Update Action (Administrator Only)
 * Procurement Management CMS - Phase 06
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireRole('administrator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/settings/index.php');
}

// 1. Verify CSRF Token
if (!validateCsrfToken()) {
    setFlash('error', 'Invalid security token or session expired. Please try again.');
    redirect('modules/settings/index.php');
}

$currentUserId = currentUserId();
$section = sanitizeInput($_POST['section'] ?? 'general');

// ==============================================================================
// 2. Handle Logo Upload / Removal Section
// ==============================================================================
if ($section === 'logo' || isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';

    // Handle Logo Removal
    if ($action === 'remove_logo') {
        $oldLogo = getSetting('company_logo');
        if (!empty($oldLogo)) {
            $oldFilePath = LOGO_UPLOAD_DIR . $oldLogo;
            if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                @unlink($oldFilePath);
            }
        }

        updateSetting('company_logo', null, 'image');
        logActivity($currentUserId, 'Settings Updated', 'Company logo was removed.');
        setFlash('success', 'Company logo removed successfully.');
        redirect('modules/settings/index.php');
    }

    // Handle Logo File Upload
    if (!isset($_FILES['company_logo']) || $_FILES['company_logo']['error'] === UPLOAD_ERR_NO_FILE) {
        setFlash('error', 'Please select a logo image to upload.');
        redirect('modules/settings/index.php');
    }

    $file = $_FILES['company_logo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Logo upload encountered an error (Code: ' . $file['error'] . ').');
        redirect('modules/settings/index.php');
    }

    if ($file['size'] > LOGO_MAX_SIZE) {
        setFlash('error', 'Uploaded logo exceeds the maximum allowed file size of 2MB.');
        redirect('modules/settings/index.php');
    }

    // Verify MIME Type with finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_LOGO_MIMES, true)) {
        setFlash('error', 'Invalid image format (' . htmlspecialchars($mimeType) . '). Permitted: JPG, PNG, WEBP, SVG.');
        redirect('modules/settings/index.php');
    }

    // Verify File Extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_LOGO_EXTS, true)) {
        setFlash('error', 'Invalid file extension. Permitted: .jpg, .jpeg, .png, .webp, .svg');
        redirect('modules/settings/index.php');
    }

    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }

    // Ensure Target Directory Exists
    if (!is_dir(LOGO_UPLOAD_DIR)) {
        if (!mkdir(LOGO_UPLOAD_DIR, 0755, true) && !is_dir(LOGO_UPLOAD_DIR)) {
            setFlash('error', 'Failed to initialize logo storage directory.');
            redirect('modules/settings/index.php');
        }
    }

    // Generate Safe Unique Filename
    $newFileName = sprintf('logo_%d_%s.%s', time(), bin2hex(random_bytes(6)), $ext);
    $destinationPath = LOGO_UPLOAD_DIR . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        setFlash('error', 'Failed to store uploaded logo. Check folder write permissions.');
        redirect('modules/settings/index.php');
    }

    // Remove Previous Logo File from Disk
    $oldLogo = getSetting('company_logo');
    if (!empty($oldLogo)) {
        $oldFilePath = LOGO_UPLOAD_DIR . $oldLogo;
        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
            @unlink($oldFilePath);
        }
    }

    // Save Setting
    updateSetting('company_logo', $newFileName, 'image');
    logActivity($currentUserId, 'Settings Updated', 'Company logo uploaded and updated.');
    setFlash('success', 'Company logo updated successfully.');
    redirect('modules/settings/index.php');
}

// ==============================================================================
// 3. Handle General & Localization Settings
// ==============================================================================
$companyName    = sanitizeInput($_POST['company_name'] ?? '');
$companyEmail   = sanitizeInput($_POST['company_email'] ?? '');
$companyPhone   = sanitizeInput($_POST['company_phone'] ?? '');
$companyAddress = sanitizeInput($_POST['company_address'] ?? '');
$currency       = sanitizeInput($_POST['currency'] ?? '$');
$dateFormat     = sanitizeInput($_POST['date_format'] ?? 'd M Y');
$timezone       = sanitizeInput($_POST['timezone'] ?? 'Asia/Dhaka');

// Validation
$errors = [];

if (empty($companyName)) {
    $errors[] = 'Company / Organization Name is required.';
}

if (empty($companyEmail) || !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid company procurement email address is required.';
}

if (empty($currency)) {
    $errors[] = 'Currency symbol is required.';
}

if (empty($dateFormat)) {
    $errors[] = 'System date format is required.';
}

// Validate Timezone
if (!in_array($timezone, timezone_identifiers_list(), true)) {
    $errors[] = 'Selected timezone identifier is invalid.';
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    redirect('modules/settings/index.php');
}

// Update Settings in Database
updateSetting('company_name', $companyName, 'text');
updateSetting('company_email', $companyEmail, 'email');
updateSetting('company_phone', $companyPhone, 'text');
updateSetting('company_address', $companyAddress, 'textarea');
updateSetting('currency', $currency, 'text');
updateSetting('date_format', $dateFormat, 'text');
updateSetting('timezone', $timezone, 'text');

logActivity($currentUserId, 'Settings Updated', 'System company and localization settings modified.');
setFlash('success', 'System settings saved and applied successfully.');
redirect('modules/settings/index.php');
