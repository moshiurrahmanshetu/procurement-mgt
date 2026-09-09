<?php
/**
 * Global Utility Functions
 * Procurement Management CMS
 */

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/database.php';

/**
 * Escapes a string for safe HTML output.
 *
 * @param mixed $value
 * @return string
 */
function e($value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generates an absolute application URL for a relative path.
 *
 * @param string $path
 * @return string
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . $path;
}

/**
 * Generates an absolute URL for an asset.
 *
 * @param string $path
 * @return string
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    return BASE_URL . 'assets/' . $path;
}

/**
 * Safely redirects to a given URL and terminates script execution.
 *
 * @param string $path URL or relative path
 */
function redirect(string $path): void
{
    // If it's a relative path, resolve it with url()
    if (!preg_match('#^https?://#i', $path)) {
        $path = url($path);
    }

    if (!headers_sent()) {
        header('Location: ' . $path);
        exit;
    }

    echo '<script>window.location.href=' . json_encode($path) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

/**
 * Returns the client's IP address safely.
 *
 * @return string
 */
function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Returns the client's User-Agent safely.
 *
 * @return string
 */
function getClientUserAgent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Logs a system or user action into the activity_logs table.
 *
 * @param int|null $userId
 * @param string $action
 * @param string|null $description
 * @return bool
 */
function logActivity(?int $userId, string $action, ?string $description = null): bool
{
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (:user_id, :action, :description, :ip_address, :user_agent, NOW())
        ");
        return $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => $action,
            ':description' => $description,
            ':ip_address'  => getClientIp(),
            ':user_agent'  => substr(getClientUserAgent(), 0, 500)
        ]);
    } catch (Exception $e) {
        error_log('Activity Log Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Returns uppercase initials from a person's full name.
 *
 * @param string $name
 * @return string
 */
function getUserInitials(string $name): string
{
    $name = trim($name);
    if (empty($name)) {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }

    return strtoupper(mb_substr($name, 0, 2));
}

/**
 * Renders user avatar img tag or initials avatar fallback element.
 *
 * @param array|null $user
 * @param int $size
 * @param string $classes
 * @return string
 */
function renderAvatar(?array $user, int $size = 40, string $classes = ''): string
{
    $avatarFile = $user['avatar'] ?? null;
    $fullName = $user['full_name'] ?? ($user['username'] ?? 'User');
    $initials = getUserInitials($fullName);

    // Check if custom avatar exists on disk
    if (!empty($avatarFile)) {
        $filePath = AVATAR_UPLOAD_DIR . $avatarFile;
        if (file_exists($filePath)) {
            $avatarUrl = BASE_URL . 'assets/uploads/avatars/' . $avatarFile;
            return sprintf(
                '<img src="%s" alt="%s" class="avatar-img rounded-circle %s" style="width:%dpx;height:%dpx;object-fit:cover;">',
                htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($classes, ENT_QUOTES, 'UTF-8'),
                $size,
                $size
            );
        }
    }

    // Dynamic color calculation based on user name hash
    $colors = ['#2563eb', '#0d9488', '#d97706', '#7c3aed', '#dc2626', '#0284c7', '#4f46e5'];
    $colorIndex = abs(crc32($fullName)) % count($colors);
    $bgColor = $colors[$colorIndex];
    $fontSize = (int)round($size * 0.42);

    return sprintf(
        '<div class="avatar-initials rounded-circle d-inline-flex align-items-center justify-content-center text-white fw-semibold shadow-sm %s" style="width:%dpx;height:%dpx;background-color:%s;font-size:%dpx;user-select:none;" title="%s">%s</div>',
        htmlspecialchars($classes, ENT_QUOTES, 'UTF-8'),
        $size,
        $size,
        $bgColor,
        $fontSize,
        htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Formats a timestamp/datetime string safely using system date format setting.
 *
 * @param string|null $date
 * @param string|null $format
 * @return string
 */
function formatDate(?string $date, ?string $format = null): string
{
    if (empty($date) || $date === '0000-00-00 00:00:00' || $date === '0000-00-00') {
        return 'Never';
    }

    if ($format === null) {
        $format = getSetting('date_format', 'd M Y, h:i A');
    }

    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Sanitizes input string (trims whitespace).
 *
 * @param mixed $data
 * @return string
 */
function sanitizeInput($data): string
{
    if (is_array($data)) {
        return '';
    }
    return trim((string)$data);
}

/**
 * Formats a monetary amount into standard currency string using system currency setting.
 *
 * @param mixed $amount
 * @param string|null $symbol
 * @return string
 */
function formatCurrency($amount, ?string $symbol = null): string
{
    if ($symbol === null) {
        $symbol = getSetting('currency', '$');
    }
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Retrieves a configuration setting value from the settings table.
 * Caches loaded settings in-memory to prevent repeated database queries.
 *
 * @param string $key
 * @param mixed $default
 * @param bool $forceRefresh
 * @return mixed
 */
function getSetting(string $key, $default = null, bool $forceRefresh = false)
{
    if (!isset($GLOBALS['__system_settings_cache']) || $forceRefresh) {
        $GLOBALS['__system_settings_cache'] = [];
        try {
            $db = getDb();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    $GLOBALS['__system_settings_cache'][$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            error_log('Error loading system settings: ' . $e->getMessage());
        }
    }

    return $GLOBALS['__system_settings_cache'][$key] ?? $default;
}

/**
 * Retrieves all configuration settings as a key-value associative array.
 *
 * @return array
 */
function getAllSettings(): array
{
    $settings = [];
    try {
        $db = getDb();
        $stmt = $db->query("SELECT * FROM settings ORDER BY id ASC");
        if ($stmt) {
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row;
            }
        }
    } catch (Exception $e) {
        error_log('Error fetching all settings: ' . $e->getMessage());
    }
    return $settings;
}

/**
 * Updates or inserts a configuration setting in the database.
 *
 * @param string $key
 * @param mixed $value
 * @param string $type
 * @return bool
 */
function updateSetting(string $key, $value, string $type = 'text'): bool
{
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type, updated_at)
            VALUES (:k1, :v1, :t1, NOW())
            ON DUPLICATE KEY UPDATE setting_value = :v2, updated_at = NOW()
        ");
        $result = $stmt->execute([
            ':k1' => $key,
            ':v1' => $value,
            ':t1' => $type,
            ':v2' => $value
        ]);
        if ($result) {
            if (!isset($GLOBALS['__system_settings_cache'])) {
                $GLOBALS['__system_settings_cache'] = [];
            }
            $GLOBALS['__system_settings_cache'][$key] = $value;
        }
        return $result;
    } catch (Exception $e) {
        error_log('Error updating setting ' . $key . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Renders company logo image tag or styled fallback text.
 *
 * @param int $maxHeight
 * @param string $classes
 * @return string
 */
function renderCompanyLogo(int $maxHeight = 36, string $classes = ''): string
{
    $logoFile = getSetting('company_logo');
    $companyName = getSetting('company_name', APP_NAME);

    if (!empty($logoFile)) {
        $filePath = LOGO_UPLOAD_DIR . $logoFile;
        if (file_exists($filePath)) {
            $logoUrl = BASE_URL . 'assets/uploads/logos/' . $logoFile;
            return sprintf(
                '<img src="%s" alt="%s" class="%s" style="max-height:%dpx;max-width:180px;object-fit:contain;">',
                htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($classes, ENT_QUOTES, 'UTF-8'),
                $maxHeight
            );
        }
    }

    return sprintf(
        '<span class="fw-bold text-dark %s">%s</span>',
        htmlspecialchars($classes, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Returns a styled Bootstrap badge for purchase request statuses.
 *
 * @param string $status
 * @return string
 */
function getStatusBadge(string $status): string
{
    $statusMap = [
        'draft'            => ['class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle', 'icon' => 'bi-file-earmark', 'label' => 'Draft'],
        'pending_approval' => ['class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle', 'icon' => 'bi-hourglass-split', 'label' => 'Pending Approval'],
        'approved'         => ['class' => 'bg-success-subtle text-success border border-success-subtle', 'icon' => 'bi-check-circle-fill', 'label' => 'Approved'],
        'rejected'         => ['class' => 'bg-danger-subtle text-danger border border-danger-subtle', 'icon' => 'bi-x-circle-fill', 'label' => 'Rejected'],
        'cancelled'        => ['class' => 'bg-dark-subtle text-dark border border-dark-subtle', 'icon' => 'bi-slash-circle', 'label' => 'Cancelled'],
        'completed'        => ['class' => 'bg-primary-subtle text-primary border border-primary-subtle', 'icon' => 'bi-patch-check-fill', 'label' => 'Completed']
    ];

    $item = $statusMap[$status] ?? ['class' => 'bg-light text-dark border', 'icon' => 'bi-circle', 'label' => ucfirst(str_replace('_', ' ', $status))];

    return sprintf(
        '<span class="badge %s px-2 py-1 small fw-semibold d-inline-flex align-items-center gap-1"><i class="bi %s"></i> %s</span>',
        $item['class'],
        $item['icon'],
        htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Returns a styled Bootstrap badge for purchase request priorities.
 *
 * @param string $priority
 * @return string
 */
function getPriorityBadge(string $priority): string
{
    $priorityMap = [
        'low'    => ['class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle', 'label' => 'Low'],
        'medium' => ['class' => 'bg-info-subtle text-info-emphasis border border-info-subtle', 'label' => 'Medium'],
        'high'   => ['class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle', 'label' => 'High'],
        'urgent' => ['class' => 'bg-danger-subtle text-danger border border-danger-subtle', 'label' => 'Urgent']
    ];

    $item = $priorityMap[$priority] ?? ['class' => 'bg-light text-dark border', 'label' => ucfirst($priority)];

    return sprintf(
        '<span class="badge %s px-2 py-1 small fw-semibold">%s</span>',
        $item['class'],
        htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Returns a styled Bootstrap badge for quotation statuses.
 *
 * @param string $status
 * @return string
 */
function getQuotationStatusBadge(string $status): string
{
    $statusMap = [
        'draft'        => ['class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle', 'icon' => 'bi-file-earmark', 'label' => 'Draft'],
        'submitted'    => ['class' => 'bg-info-subtle text-info-emphasis border border-info-subtle', 'icon' => 'bi-send-fill', 'label' => 'Submitted'],
        'under_review' => ['class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle', 'icon' => 'bi-search', 'label' => 'Under Review'],
        'selected'     => ['class' => 'bg-success-subtle text-success border border-success-subtle', 'icon' => 'bi-trophy-fill', 'label' => 'Selected (Awarded)'],
        'rejected'     => ['class' => 'bg-danger-subtle text-danger border border-danger-subtle', 'icon' => 'bi-x-circle-fill', 'label' => 'Rejected'],
        'expired'      => ['class' => 'bg-dark-subtle text-dark border border-dark-subtle', 'icon' => 'bi-calendar-x-fill', 'label' => 'Expired'],
        'cancelled'    => ['class' => 'bg-secondary-subtle text-muted border border-secondary-subtle', 'icon' => 'bi-slash-circle', 'label' => 'Cancelled']
    ];

    $item = $statusMap[$status] ?? ['class' => 'bg-light text-dark border', 'icon' => 'bi-circle', 'label' => ucfirst(str_replace('_', ' ', $status))];

    return sprintf(
        '<span class="badge %s px-2 py-1 small fw-semibold d-inline-flex align-items-center gap-1"><i class="bi %s"></i> %s</span>',
        $item['class'],
        $item['icon'],
        htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Returns a styled Bootstrap badge for supplier statuses.
 *
 * @param string $status
 * @return string
 */
function getSupplierStatusBadge(string $status): string
{
    $statusMap = [
        'active'      => ['class' => 'bg-success-subtle text-success border border-success-subtle', 'icon' => 'bi-check-circle-fill', 'label' => 'Active'],
        'inactive'    => ['class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle', 'icon' => 'bi-pause-circle-fill', 'label' => 'Inactive'],
        'blacklisted' => ['class' => 'bg-danger-subtle text-danger border border-danger-subtle', 'icon' => 'bi-slash-circle-fill', 'label' => 'Blacklisted']
    ];

    $item = $statusMap[$status] ?? ['class' => 'bg-light text-dark border', 'icon' => 'bi-circle', 'label' => ucfirst($status)];

    return sprintf(
        '<span class="badge %s px-2 py-1 small fw-semibold d-inline-flex align-items-center gap-1"><i class="bi %s"></i> %s</span>',
        $item['class'],
        $item['icon'],
        htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Returns a styled Bootstrap badge for purchase order statuses.
 *
 * @param string $status
 * @return string
 */
function getPoStatusBadge(string $status): string
{
    $statusMap = [
        'draft'              => ['class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle', 'icon' => 'bi-file-earmark', 'label' => 'Draft'],
        'pending_approval'   => ['class' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle', 'icon' => 'bi-hourglass-split', 'label' => 'Pending Approval'],
        'approved'           => ['class' => 'bg-success-subtle text-success border border-success-subtle', 'icon' => 'bi-check-circle-fill', 'label' => 'Approved'],
        'sent'               => ['class' => 'bg-info-subtle text-info-emphasis border border-info-subtle', 'icon' => 'bi-send-check-fill', 'label' => 'Sent to Supplier'],
        'partially_received' => ['class' => 'bg-primary-subtle text-primary border border-primary-subtle', 'icon' => 'bi-box-seam', 'label' => 'Partially Received'],
        'fully_received'     => ['class' => 'bg-success text-white', 'icon' => 'bi-box2-fill', 'label' => 'Fully Received'],
        'cancelled'          => ['class' => 'bg-danger-subtle text-danger border border-danger-subtle', 'icon' => 'bi-x-circle-fill', 'label' => 'Cancelled'],
        'closed'             => ['class' => 'bg-dark-subtle text-dark border border-dark-subtle', 'icon' => 'bi-lock-fill', 'label' => 'Closed']
    ];

    $item = $statusMap[$status] ?? ['class' => 'bg-light text-dark border', 'icon' => 'bi-circle', 'label' => ucfirst(str_replace('_', ' ', $status))];

    return sprintf(
        '<span class="badge %s px-2 py-1 small fw-semibold d-inline-flex align-items-center gap-1"><i class="bi %s"></i> %s</span>',
        $item['class'],
        $item['icon'],
        htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
    );
}

/**
 * Returns a styled Bootstrap badge for goods receipt (GRN) statuses.
 *
 * @param string $status
 * @return string
 */
function getGrnStatusBadge(string $status): string
{
    $statusMap = [
        'draft'     => ['class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle', 'icon' => 'bi-file-earmark', 'label' => 'Draft'],
        'posted'    => ['class' => 'bg-success-subtle text-success border border-success-subtle', 'icon' => 'bi-check-circle-fill', 'label' => 'Posted (Locked)'],
        'cancelled' => ['class' => 'bg-danger-subtle text-danger border border-danger-subtle', 'icon' => 'bi-slash-circle', 'label' => 'Cancelled']
    ];

    $item = $statusMap[$status] ?? ['class' => 'bg-light text-dark border', 'icon' => 'bi-circle', 'label' => ucfirst(str_replace('_', ' ', $status))];

    return sprintf(
        '<span class="badge %s px-2 py-1 small fw-semibold d-inline-flex align-items-center gap-1"><i class="bi %s"></i> %s</span>',
        $item['class'],
        $item['icon'],
        htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
    );
}


