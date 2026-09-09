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
 * Formats a timestamp/datetime string safely.
 *
 * @param string|null $date
 * @param string $format
 * @return string
 */
function formatDate(?string $date, string $format = 'd M Y, h:i A'): string
{
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return 'Never';
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
 * Formats a monetary amount into standard currency string.
 *
 * @param mixed $amount
 * @param string $symbol
 * @return string
 */
function formatCurrency($amount, string $symbol = '$'): string
{
    return $symbol . number_format((float)$amount, 2);
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

