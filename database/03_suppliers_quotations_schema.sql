-- ==============================================================================
-- Procurement Management CMS - Phase 03 Database Schema
-- Module: Supplier Management & Quotation Management
-- Database: procurement_mgt
-- ==============================================================================

USE `procurement_mgt`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Table: suppliers
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `supplier_code` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(200) NOT NULL,
    `company_name` VARCHAR(200) NULL,
    `email` VARCHAR(150) NULL DEFAULT NULL,
    `phone` VARCHAR(50) NULL DEFAULT NULL,
    `address` TEXT NULL,
    `city` VARCHAR(100) NULL DEFAULT NULL,
    `state` VARCHAR(100) NULL DEFAULT NULL,
    `country` VARCHAR(100) NULL DEFAULT NULL,
    `postal_code` VARCHAR(20) NULL DEFAULT NULL,
    `tax_number` VARCHAR(100) NULL DEFAULT NULL,
    `website` VARCHAR(255) NULL DEFAULT NULL,
    `bank_name` VARCHAR(150) NULL DEFAULT NULL,
    `bank_account_name` VARCHAR(150) NULL DEFAULT NULL,
    `bank_account_number` VARCHAR(100) NULL DEFAULT NULL,
    `bank_routing` VARCHAR(100) NULL DEFAULT NULL,
    `payment_terms` VARCHAR(255) NULL DEFAULT NULL,
    `notes` TEXT NULL,
    `status` ENUM('active', 'inactive', 'blacklisted') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_suppliers_status` (`status`),
    INDEX `idx_suppliers_name` (`name`),
    INDEX `idx_suppliers_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table: supplier_contacts
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `supplier_contacts`;
CREATE TABLE `supplier_contacts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `supplier_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `contact_name` VARCHAR(150) NULL,
    `job_title` VARCHAR(150) NULL DEFAULT NULL,
    `designation` VARCHAR(150) NULL DEFAULT NULL,
    `email` VARCHAR(150) NULL DEFAULT NULL,
    `phone` VARCHAR(50) NULL DEFAULT NULL,
    `mobile` VARCHAR(50) NULL DEFAULT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_sc_supplier_id` (`supplier_id`),
    CONSTRAINT `fk_sc_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Table: quotations
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quotation_no` VARCHAR(50) NOT NULL UNIQUE,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `quotation_date` DATE NOT NULL,
    `valid_until` DATE NULL DEFAULT NULL,
    `reference_number` VARCHAR(100) NULL DEFAULT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `shipping_cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `other_charges` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `payment_terms` VARCHAR(255) NULL DEFAULT NULL,
    `delivery_terms` VARCHAR(255) NULL DEFAULT NULL,
    `notes` TEXT NULL,
    `status` ENUM(
        'draft',
        'submitted',
        'under_review',
        'selected',
        'rejected',
        'expired',
        'cancelled'
    ) NOT NULL DEFAULT 'draft',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_quote_status` (`status`),
    INDEX `idx_quote_pr_id` (`purchase_request_id`),
    INDEX `idx_quote_supplier_id` (`supplier_id`),
    INDEX `idx_quote_created_by` (`created_by`),
    INDEX `idx_quote_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_quote_pr` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_quote_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_quote_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Table: quotation_items
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `quotation_items`;
CREATE TABLE `quotation_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quotation_id` INT UNSIGNED NOT NULL,
    `purchase_request_item_id` INT UNSIGNED NULL DEFAULT NULL,
    `quantity` DECIMAL(15,2) NOT NULL,
    `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `remarks` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_qi_quotation_id` (`quotation_id`),
    INDEX `idx_qi_pr_item_id` (`purchase_request_item_id`),
    CONSTRAINT `fk_qi_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_qi_pr_item` FOREIGN KEY (`purchase_request_item_id`) REFERENCES `purchase_request_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Table: quotation_history
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `quotation_history`;
CREATE TABLE `quotation_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `quotation_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `from_status` VARCHAR(50) NULL DEFAULT NULL,
    `to_status` VARCHAR(50) NULL DEFAULT NULL,
    `old_status` VARCHAR(50) NULL DEFAULT NULL,
    `new_status` VARCHAR(50) NULL DEFAULT NULL,
    `comments` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_qh_quotation_id` (`quotation_id`),
    INDEX `idx_qh_user_id` (`user_id`),
    CONSTRAINT `fk_qh_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_qh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- SEED DATA: suppliers & initial contacts
-- ------------------------------------------------------------------------------
INSERT INTO `suppliers` (`id`, `supplier_code`, `name`, `company_name`, `email`, `phone`, `website`, `address`, `city`, `state`, `country`, `postal_code`, `tax_number`, `bank_name`, `bank_account_name`, `bank_account_number`, `bank_routing`, `payment_terms`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 'SUP-000001', 'Dell Enterprise Direct', 'Dell Enterprise Direct', 'procurement@dellenterprise.com', '+1 (555) 234-5678', 'https://www.dell.com', 'One Dell Way', 'Round Rock', 'TX', 'United States', '78682', 'US-TX-9988221', 'JPMorgan Chase', 'Dell Enterprise Direct', '110022334455', 'CHASUS33', 'Net 30 Days', 'Primary hardware and workstation systems vendor', 'active', NOW(), NOW()),
(2, 'SUP-000002', 'Office Depot Business Solutions', 'Office Depot Business Solutions', 'orders@officedepotb2b.com', '+1 (555) 345-6789', 'https://www.officedepot.com', '6600 North Military Trail', 'Boca Raton', 'FL', 'United States', '33496', 'US-FL-3344551', 'Bank of America', 'Office Depot B2B Solutions', '998877665544', 'BOFAUS3N', 'Net 15 Days', 'General stationery, office furniture, and consumable supplies', 'active', NOW(), NOW()),
(3, 'SUP-000003', 'Apex Industrial Supply Co.', 'Apex Industrial Supply Co.', 'sales@apexsupply.com', '+1 (555) 456-7890', 'https://www.apexsupply.example.com', '1200 Industrial Parkway', 'Scranton', 'PA', 'United States', '18504', 'US-PA-7788990', 'PNC Bank', 'Apex Industrial Supply Co.', '445566778899', 'PNCBUS22', 'Net 45 Days', 'Industrial tools, safety equipment, and warehouse machinery', 'active', NOW(), NOW()),
(4, 'SUP-000004', 'Global Logistics & Network Hub', 'Global Logistics & Network Hub', 'info@globallogistics.example.com', '+1 (555) 567-8901', 'https://www.globallogistics.example.com', '450 Harbor Boulevard', 'Jersey City', 'NJ', 'United States', '07302', 'US-NJ-1122334', 'Wells Fargo', 'Global Logistics & Network Hub', '332211009988', 'WFBIUS6S', 'Net 30 Days', 'Networking equipment, cabling, and logistics solutions', 'active', NOW(), NOW());

INSERT INTO `supplier_contacts` (`supplier_id`, `name`, `contact_name`, `job_title`, `designation`, `phone`, `email`, `is_primary`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Michael Scott', 'Michael Scott', 'Key Account Director', 'Key Account Director', '+1 (555) 234-5678', 'michael.scott@dellenterprise.com', 1, 'Main contact for enterprise quotes', NOW(), NOW()),
(1, 'Jim Vance', 'Jim Vance', 'Technical Sales Engineer', 'Technical Sales Engineer', '+1 (555) 234-5679', 'jim.vance@dellenterprise.com', 0, 'Technical hardware queries', NOW(), NOW()),
(2, 'Pam Beesly', 'Pam Beesly', 'Regional Sales Manager', 'Regional Sales Manager', '+1 (555) 345-6789', 'pam.beesly@officedepotb2b.com', 1, 'Primary representative', NOW(), NOW()),
(3, 'Dwight Schrute', 'Dwight Schrute', 'Head of Sales & Distribution', 'Head of Sales & Distribution', '+1 (555) 456-7890', 'dwight.schrute@apexsupply.com', 1, 'Lead contact for orders', NOW(), NOW()),
(4, 'Jim Halpert', 'Jim Halpert', 'Business Development Lead', 'Business Development Lead', '+1 (555) 567-8901', 'jim.halpert@globallogistics.example.com', 1, 'Primary account manager', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
