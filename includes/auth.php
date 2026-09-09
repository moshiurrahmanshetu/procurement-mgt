<?php
/**
 * Authentication and Role-Based Access Control Foundation
 * Procurement Management CMS
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';

/**
 * Checks if a user is currently authenticated.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        startAppSession();
    }
    return !empty($_SESSION['user_id']);
}

/**
 * Returns the current authenticated user's ID.
 *
 * @return int|null
 */
function currentUserId(): ?int
{
    if (!isLoggedIn()) {
        return null;
    }
    return (int)$_SESSION['user_id'];
}

/**
 * Retrieves the full user record and assigned roles.
 *
 * @param bool $forceRefresh
 * @return array|null
 */
function currentUser(bool $forceRefresh = false): ?array
{
    static $cachedUser = null;

    if ($forceRefresh) {
        $cachedUser = null;
    }

    if ($cachedUser !== null) {
        return $cachedUser;
    }

    $userId = currentUserId();
    if (!$userId) {
        return null;
    }

    try {
        $db = getDb();
        $stmt = $db->prepare("
            SELECT u.*, 
                   GROUP_CONCAT(r.slug SEPARATOR ',') AS role_slugs,
                   GROUP_CONCAT(r.name SEPARATOR ', ') AS role_names
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id AND r.status = 'active'
            WHERE u.id = :id AND u.deleted_at IS NULL AND u.status = 'active'
            GROUP BY u.id
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            // User was deactivated or deleted while in session
            logoutUser();
            return null;
        }

        $user['roles'] = !empty($user['role_slugs']) ? explode(',', $user['role_slugs']) : [];
        $cachedUser = $user;
        return $user;
    } catch (Exception $e) {
        error_log('Error fetching current user: ' . $e->getMessage());
        return null;
    }
}

/**
 * Returns an array of role slugs assigned to current user.
 *
 * @return array
 */
function currentUserRoles(): array
{
    $user = currentUser();
    return $user['roles'] ?? [];
}

/**
 * Checks if the current user has a specific role.
 *
 * @param string $roleSlug
 * @return bool
 */
function userHasRole(string $roleSlug): bool
{
    $roles = currentUserRoles();
    return in_array($roleSlug, $roles, true);
}

/**
 * Requires the user to be logged in; redirects to login page otherwise.
 */
function requireLogin(): void
{
    if (!isLoggedIn() || currentUser() === null) {
        setFlash('error', 'Please log in to access this page.');
        redirect('auth/login.php');
    }
}

/**
 * Requires the user to have a specific role; denies access otherwise.
 *
 * @param string|array $requiredRoles
 */
function requireRole($requiredRoles): void
{
    requireLogin();

    $required = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];
    $userRoles = currentUserRoles();

    $hasAccess = false;
    foreach ($required as $role) {
        if (in_array($role, $userRoles, true)) {
            $hasAccess = true;
            break;
        }
    }

    if (!$hasAccess) {
        // Safe access denial
        http_response_code(403);
        setFlash('error', 'Access Denied: You do not have permission to view that page.');
        redirect('auth/access-denied.php');
    }
}

/**
 * Logs in a user, regenerates session, and updates database records.
 *
 * @param array $user
 * @param bool $remember
 * @return bool
 */
function loginUser(array $user, bool $remember = false): bool
{
    startAppSession();
    regenerateAppSession();

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['last_active_time'] = time();

    try {
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE users 
            SET last_login = NOW(), last_activity = NOW() 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $user['id']]);

        logActivity((int)$user['id'], 'User Login', 'User authenticated successfully.');
        return true;
    } catch (Exception $e) {
        error_log('Error updating login info: ' . $e->getMessage());
        return false;
    }
}

/**
 * Logs out the current user, clears session, and destroys session data.
 */
function logoutUser(): void
{
    if (isLoggedIn()) {
        $userId = currentUserId();
        logActivity($userId, 'User Logout', 'User logged out of the session.');
    }

    if (session_status() === PHP_SESSION_NONE) {
        startAppSession();
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
