-- ==============================================================================
-- Procurement Management CMS - Phase 02 Database Schema
-- Module: Purchase Requests & Departments
-- Database: procurement_mgt
-- ==============================================================================

USE `procurement_mgt`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Table: departments
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_departments_status` (`status`),
    INDEX `idx_departments_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table: purchase_requests
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_requests`;
CREATE TABLE `purchase_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_no` VARCHAR(50) NOT NULL UNIQUE,
    `requested_by` INT UNSIGNED NOT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `request_date` DATE NOT NULL,
    `required_date` DATE NULL DEFAULT NULL,
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `purpose` TEXT NOT NULL,
    `notes` TEXT NULL,
    `estimated_subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `estimated_tax` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `estimated_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status` ENUM(
        'draft',
        'pending_approval',
        'approved',
        'rejected',
        'cancelled',
        'completed'
    ) NOT NULL DEFAULT 'draft',
    `approved_by` INT UNSIGNED NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `rejection_reason` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_pr_status` (`status`),
    INDEX `idx_pr_priority` (`priority`),
    INDEX `idx_pr_requested_by` (`requested_by`),
    INDEX `idx_pr_department_id` (`department_id`),
    INDEX `idx_pr_request_date` (`request_date`),
    INDEX `idx_pr_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_pr_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pr_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_pr_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Table: purchase_request_items
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_request_items`;
CREATE TABLE `purchase_request_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `quantity` DECIMAL(15,2) NOT NULL,
    `unit` VARCHAR(50) NOT NULL,
    `estimated_unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `estimated_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_pri_request_id` (`purchase_request_id`),
    CONSTRAINT `fk_pri_request` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Table: purchase_request_history
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_request_history`;
CREATE TABLE `purchase_request_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_status` VARCHAR(50) NULL DEFAULT NULL,
    `new_status` VARCHAR(50) NULL DEFAULT NULL,
    `comments` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_prh_request_id` (`purchase_request_id`),
    INDEX `idx_prh_user_id` (`user_id`),
    CONSTRAINT `fk_prh_request` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_prh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- SEED DATA: departments
-- ------------------------------------------------------------------------------
INSERT INTO `departments` (`id`, `name`, `code`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Administration', 'ADM', 'General administration and office management', 'active', NOW(), NOW()),
(2, 'Finance', 'FIN', 'Financial operations, budgets, and treasury management', 'active', NOW(), NOW()),
(3, 'Human Resources', 'HR', 'Talent acquisition, employee relations, and HR ops', 'active', NOW(), NOW()),
(4, 'IT', 'IT', 'Information technology, infrastructure, hardware and software', 'active', NOW(), NOW()),
(5, 'Operations', 'OPS', 'Day-to-day business operations and logistics', 'active', NOW(), NOW()),
(6, 'Procurement', 'PROC', 'Procurement, vendor management, and sourcing', 'active', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
