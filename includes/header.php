<?php
/**
 * Master Header Component
 * Procurement Management CMS
 */

require_once __DIR__ . '/init.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$currentUser = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/bootstrap-icons.css') ?>">

    <!-- Core App CSS -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">

    <?php if (!empty($extraCss)): ?>
        <?php if (is_array($extraCss)): ?>
            <?php foreach ($extraCss as $cssUrl): ?>
                <link rel="stylesheet" href="<?= e($cssUrl) ?>">
            <?php endforeach; ?>
        <?php else: ?>
            <link rel="stylesheet" href="<?= e($extraCss) ?>">
        <?php endif; ?>
    <?php endif; ?>
</head>
<body>
<div class="app-wrapper">
    <!-- Sidebar Component -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Mobile Backdrop -->
    <div id="sidebarBackdrop" class="sidebar-backdrop"></div>

    <!-- Main Content Container -->
    <div class="app-main">
        <!-- Top Navigation Bar -->
        <?php require_once __DIR__ . '/navbar.php'; ?>

        <!-- Content Area -->
        <main class="app-content">
            <!-- Flash Message Alerts -->
            <div class="flash-messages-container">
                <?= renderFlashMessages() ?>
            </div>
