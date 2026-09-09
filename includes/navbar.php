<?php
/**
 * Master Top Navbar Component
 * Procurement Management CMS
 */

require_once __DIR__ . '/init.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$user = currentUser();
?>
<header class="app-navbar">
    <!-- Left Section: Toggle & Title -->
    <div class="d-flex align-items-center gap-3">
        <button type="button" id="sidebarToggle" class="navbar-toggle-btn" aria-label="Toggle Sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div>
            <h1 class="page-breadcrumb-title"><?= e($pageTitle) ?></h1>
            <?php if (!empty($pageSubtitle)): ?>
                <div class="breadcrumb-subtext"><?= e($pageSubtitle) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Section: User Profile Dropdown -->
    <div class="d-flex align-items-center gap-3">
        <div class="dropdown navbar-user-dropdown">
            <a href="#" class="dropdown-toggle" id="userDropdownMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <?= renderAvatar($user, 36) ?>
                <div class="text-start d-none d-md-block">
                    <div class="navbar-user-name"><?= e($user['full_name'] ?? 'Account') ?></div>
                    <div class="navbar-user-role"><?= e($user['role_names'] ?? 'User') ?></div>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="userDropdownMenu" style="min-width: 200px;">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-semibold text-dark small"><?= e($user['full_name'] ?? '') ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.75rem;"><?= e($user['email'] ?? '') ?></div>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="<?= url('modules/profile/index.php') ?>">
                        <i class="bi bi-person-gear text-muted"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="<?= url('auth/logout.php') ?>">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
