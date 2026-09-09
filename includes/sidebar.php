<?php
/**
 * Master Sidebar Component
 * Procurement Management CMS - Phase 06
 */

require_once __DIR__ . '/init.php';

$activeNav = $activeNav ?? '';
$user = currentUser();
$isAdmin = userHasRole('administrator');
$isManager = userHasRole('manager');
$isOfficer = userHasRole('procurement-officer');
$canViewSystem = $isAdmin || $isManager;
?>
<aside class="app-sidebar">
    <!-- Brand Logo -->
    <a href="<?= url('modules/dashboard/index.php') ?>" class="sidebar-brand">
        <!-- Expanded Full Logo -->
        <svg class="brand-logo-full" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 48" fill="none">
            <rect width="42" height="42" rx="10" fill="#2563eb" />
            <path d="M14 26L21 16H28L21 26H14Z" fill="#ffffff" fill-opacity="0.9" />
            <path d="M21 26L28 16H35L28 26H21Z" fill="#60a5fa" />
            <path d="M14 31H28C30.2 31 32 29.2 32 27V25H28V27H14V31Z" fill="#ffffff" />
            <text x="54" y="27" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="20" font-weight="700" fill="#f8fafc" letter-spacing="-0.5">Procure<tspan fill="#3b82f6">CMS</tspan></text>
            <text x="54" y="38" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="9" font-weight="600" fill="#94a3b8" letter-spacing="1.2">ENTERPRISE</text>
        </svg>

        <!-- Collapsed Icon Only -->
        <svg class="brand-logo-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" fill="none">
            <rect width="48" height="48" rx="12" fill="#2563eb" />
            <path d="M16 28L24 16H32L24 28H16Z" fill="#ffffff" fill-opacity="0.9" />
            <path d="M24 28L32 16H40L32 28H24Z" fill="#93c5fd" />
            <path d="M16 34H32C34.2 34 36 32.2 36 30V28H32V30H16V34Z" fill="#ffffff" />
        </svg>
    </a>

    <!-- Sidebar Navigation -->
    <div class="sidebar-nav">
        <!-- Main Navigation -->
        <div class="nav-section-title">Main</div>
        <ul class="sidebar-menu">
            <li class="nav-item">
                <a href="<?= url('modules/dashboard/index.php') ?>" class="nav-link <?= ($activeNav === 'dashboard') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <!-- Procurement Section -->
        <div class="nav-section-title mt-3">Procurement</div>
        <ul class="sidebar-menu">
            <li class="nav-item">
                <a href="<?= url('modules/purchase_requests/index.php') ?>" class="nav-link <?= ($activeNav === 'purchase_requests') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Purchase Requests">
                    <i class="bi bi-cart-check-fill"></i>
                    <span>Purchase Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('modules/suppliers/index.php') ?>" class="nav-link <?= ($activeNav === 'suppliers') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Suppliers Directory">
                    <i class="bi bi-building"></i>
                    <span>Suppliers</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('modules/quotations/index.php') ?>" class="nav-link <?= ($activeNav === 'quotations') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Supplier Quotations">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>Quotations</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('modules/purchase_orders/index.php') ?>" class="nav-link <?= ($activeNav === 'purchase_orders') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Purchase Orders">
                    <i class="bi bi-file-earmark-check-fill"></i>
                    <span>Purchase Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('modules/goods_receiving/index.php') ?>" class="nav-link <?= ($activeNav === 'goods_receiving') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Goods Receiving (GRN)">
                    <i class="bi bi-box-seam-fill"></i>
                    <span>Goods Receiving</span>
                </a>
            </li>
        </ul>

        <!-- Reports Section -->
        <div class="nav-section-title mt-3">Reports</div>
        <ul class="sidebar-menu">
            <li class="nav-item">
                <a href="<?= url('modules/reports/index.php') ?>" class="nav-link <?= ($activeNav === 'reports') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Reports Hub">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>

        <?php if ($canViewSystem): ?>
            <!-- System Administration -->
            <div class="nav-section-title mt-3">System</div>
            <ul class="sidebar-menu">
                <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a href="<?= url('modules/users/index.php') ?>" class="nav-link <?= ($activeNav === 'users') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="User Management">
                            <i class="bi bi-people-fill"></i>
                            <span>Users</span>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a href="<?= url('modules/activity_logs/index.php') ?>" class="nav-link <?= ($activeNav === 'activity_logs') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Activity Logs">
                        <i class="bi bi-clock-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>

                <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a href="<?= url('modules/settings/index.php') ?>" class="nav-link <?= ($activeNav === 'settings') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="System Settings">
                            <i class="bi bi-gear-fill"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

        <!-- Account Section -->
        <div class="nav-section-title mt-3">Account</div>
        <ul class="sidebar-menu">
            <li class="nav-item">
                <a href="<?= url('modules/profile/index.php') ?>" class="nav-link <?= ($activeNav === 'profile') ? 'active' : '' ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="My Profile">
                    <i class="bi bi-person-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= url('auth/logout.php') ?>" class="nav-link text-danger-hover" data-bs-toggle="tooltip" data-bs-placement="right" title="Logout">
                    <i class="bi bi-box-arrow-right text-danger"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Sidebar User Footer -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2 overflow-hidden w-100">
            <?= renderAvatar($user, 36) ?>
            <div class="user-details overflow-hidden flex-grow-1">
                <div class="text-white text-truncate fw-semibold font-monospace" style="font-size: 0.8125rem;">
                    <?= e($user['full_name'] ?? 'User') ?>
                </div>
                <div class="text-muted text-truncate" style="font-size: 0.6875rem;">
                    <?= e($user['role_names'] ?? 'Member') ?>
                </div>
            </div>
        </div>
    </div>
</aside>
