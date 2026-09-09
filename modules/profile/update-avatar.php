<?php
/**
 * Avatar Upload & Removal Action
 * Procurement Management CMS
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('modules/profile/index.php');
}

// 1. Verify CSRF
if (!validateCsrfToken()) {
    setFlash('error', 'Invalid security token or session expired. Please try again.');
    redirect('modules/profile/index.php');
}

$user = currentUser(true);
$userId = currentUserId();
$db = getDb();

// 2. Handle Avatar Removal Action
if (isset($_POST['action']) && $_POST['action'] === 'remove') {
    try {
        if (!empty($user['avatar'])) {
            $oldFilePath = AVATAR_UPLOAD_DIR . $user['avatar'];
            if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                @unlink($oldFilePath);
            }
        }

        $stmt = $db->prepare("UPDATE users SET avatar = NULL, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        logActivity($userId, 'Avatar Removed', 'User removed custom profile avatar.');
        setFlash('success', 'Profile photo removed successfully.');
    } catch (Exception $e) {
        error_log('Remove Avatar Error: ' . $e->getMessage());
        setFlash('error', 'Failed to remove avatar. Please try again.');
    }
    redirect('modules/profile/index.php');
}

// 3. Handle Avatar File Upload
if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
    setFlash('error', 'Please select an image file to upload.');
    redirect('modules/profile/index.php');
}

$file = $_FILES['avatar'];

// Check upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('error', 'File upload error code: ' . $file['error'] . '. Please try again.');
    redirect('modules/profile/index.php');
}

// Validate file size (2MB max)
if ($file['size'] > AVATAR_MAX_SIZE) {
    setFlash('error', 'The uploaded image exceeds the maximum allowed file size of 2MB.');
    redirect('modules/profile/index.php');
}

// Validate MIME type with finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, ALLOWED_AVATAR_MIMES, true)) {
    setFlash('error', 'Invalid file type (' . htmlspecialchars($mimeType) . '). Only JPG, PNG, and WEBP images are allowed.');
    redirect('modules/profile/index.php');
}

// Validate extension
$originalName = $file['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($ext, ALLOWED_AVATAR_EXTS, true)) {
    setFlash('error', 'Invalid file extension. Only .jpg, .jpeg, .png, and .webp are permitted.');
    redirect('modules/profile/index.php');
}

// Normalize extension
if ($ext === 'jpeg') {
    $ext = 'jpg';
}

// Ensure target directory exists and is writable
if (!is_dir(AVATAR_UPLOAD_DIR)) {
    if (!mkdir(AVATAR_UPLOAD_DIR, 0755, true) && !is_dir(AVATAR_UPLOAD_DIR)) {
        setFlash('error', 'Failed to initialize avatar storage directory.');
        redirect('modules/profile/index.php');
    }
}

// Generate secure random unique filename
$newFileName = sprintf('avatar_%d_%d_%s.%s', $userId, time(), bin2hex(random_bytes(8)), $ext);
$destinationPath = AVATAR_UPLOAD_DIR . $newFileName;

// Move uploaded file safely
if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
    setFlash('error', 'Failed to save uploaded image. Please check directory permissions.');
    redirect('modules/profile/index.php');
}

// Safely delete previous custom avatar file if present
if (!empty($user['avatar'])) {
    $oldFilePath = AVATAR_UPLOAD_DIR . $user['avatar'];
    if (file_exists($oldFilePath) && is_file($oldFilePath)) {
        @unlink($oldFilePath);
    }
}

// Update database record
try {
    $updateStmt = $db->prepare("UPDATE users SET avatar = :avatar, updated_at = NOW() WHERE id = :id");
    $updateStmt->execute([
        ':avatar' => $newFileName,
        ':id'     => $userId
    ]);

    logActivity($userId, 'Avatar Changed', 'User updated profile photo.');
    setFlash('success', 'Profile photo updated successfully.');
} catch (Exception $e) {
    // Clean up uploaded file on database error
    if (file_exists($destinationPath)) {
        @unlink($destinationPath);
    }
    error_log('Update Avatar DB Error: ' . $e->getMessage());
    setFlash('error', 'Failed to update avatar in database.');
}

redirect('modules/profile/index.php');
