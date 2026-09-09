-- ==============================================================================
-- Procurement Management CMS — Complete Master Installation Schema
-- Version: 1.0.0 (Enterprise Marketplace Edition)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Table: roles
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table: users
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `avatar` VARCHAR(255) NULL DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `last_login` DATETIME NULL DEFAULT NULL,
    `last_activity` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_users_status` (`status`),
    INDEX `idx_users_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Table: user_roles
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE `user_roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_role` (`user_id`, `role_id`),
    CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Table: password_resets
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_password_resets_token` (`token`),
    CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Table: activity_logs
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_activity_logs_user` (`user_id`),
    INDEX `idx_activity_logs_action` (`action`),
    CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. Table: departments
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
-- 7. Table: purchase_requests
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
-- 8. Table: purchase_request_items
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
-- 9. Table: purchase_request_history
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
-- 10. Table: suppliers
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
-- 11. Table: supplier_contacts
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
-- 12. Table: quotations
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
-- 13. Table: quotation_items
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
-- 14. Table: quotation_history
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
-- 15. Table: purchase_orders
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `po_no` VARCHAR(50) NOT NULL UNIQUE,
    `purchase_request_id` INT UNSIGNED NOT NULL,
    `quotation_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `po_date` DATE NOT NULL,
    `expected_delivery_date` DATE NULL DEFAULT NULL,
    `delivery_address` TEXT NULL,
    `payment_terms` VARCHAR(255) NULL DEFAULT NULL,
    `delivery_terms` VARCHAR(255) NULL DEFAULT NULL,
    `notes` TEXT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `status` ENUM(
        'draft',
        'pending_approval',
        'approved',
        'sent',
        'partially_received',
        'fully_received',
        'cancelled',
        'closed'
    ) NOT NULL DEFAULT 'draft',
    `created_by` INT UNSIGNED NOT NULL,
    `approved_by` INT UNSIGNED NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `sent_at` DATETIME NULL DEFAULT NULL,
    `cancelled_by` INT UNSIGNED NULL DEFAULT NULL,
    `cancelled_at` DATETIME NULL DEFAULT NULL,
    `cancellation_reason` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_po_status` (`status`),
    INDEX `idx_po_pr_id` (`purchase_request_id`),
    INDEX `idx_po_quotation_id` (`quotation_id`),
    INDEX `idx_po_supplier_id` (`supplier_id`),
    INDEX `idx_po_created_by` (`created_by`),
    INDEX `idx_po_approved_by` (`approved_by`),
    INDEX `idx_po_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_po_pr` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_po_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_po_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_po_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_po_canceller` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 16. Table: purchase_order_items
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `quotation_item_id` INT UNSIGNED NULL DEFAULT NULL,
    `purchase_request_item_id` INT UNSIGNED NULL DEFAULT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `quantity` DECIMAL(15,2) NOT NULL,
    `unit` VARCHAR(50) NOT NULL,
    `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `tax_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `line_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_poi_po_id` (`purchase_order_id`),
    INDEX `idx_poi_qi_id` (`quotation_item_id`),
    INDEX `idx_poi_pri_id` (`purchase_request_item_id`),
    CONSTRAINT `fk_poi_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_poi_qi` FOREIGN KEY (`quotation_item_id`) REFERENCES `quotation_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_poi_pri` FOREIGN KEY (`purchase_request_item_id`) REFERENCES `purchase_request_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 17. Table: purchase_order_history
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_order_history`;
CREATE TABLE `purchase_order_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_status` VARCHAR(50) NULL DEFAULT NULL,
    `new_status` VARCHAR(50) NULL DEFAULT NULL,
    `comments` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_poh_po_id` (`purchase_order_id`),
    INDEX `idx_poh_user_id` (`user_id`),
    CONSTRAINT `fk_poh_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_poh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 18. Table: goods_receipts
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `goods_receipts`;
CREATE TABLE `goods_receipts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `grn_no` VARCHAR(50) NOT NULL UNIQUE,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `supplier_id` INT UNSIGNED NOT NULL,
    `receipt_date` DATE NOT NULL,
    `delivery_note_no` VARCHAR(100) NULL DEFAULT NULL,
    `received_by` INT UNSIGNED NOT NULL,
    `notes` TEXT NULL,
    `status` ENUM('draft', 'posted', 'cancelled') NOT NULL DEFAULT 'draft',
    `posted_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_grn_status` (`status`),
    INDEX `idx_grn_po_id` (`purchase_order_id`),
    INDEX `idx_grn_supplier_id` (`supplier_id`),
    INDEX `idx_grn_received_by` (`received_by`),
    INDEX `idx_grn_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_grn_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_grn_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_grn_receiver` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 19. Table: goods_receipt_items
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `goods_receipt_items`;
CREATE TABLE `goods_receipt_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `goods_receipt_id` INT UNSIGNED NOT NULL,
    `purchase_order_item_id` INT UNSIGNED NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `ordered_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `previously_received_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `received_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `rejected_qty` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(50) NOT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    INDEX `idx_gri_grn_id` (`goods_receipt_id`),
    INDEX `idx_gri_poi_id` (`purchase_order_item_id`),
    CONSTRAINT `fk_gri_grn` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_gri_poi` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 20. Table: goods_receipt_history
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `goods_receipt_history`;
CREATE TABLE `goods_receipt_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `goods_receipt_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `old_status` VARCHAR(50) NULL DEFAULT NULL,
    `new_status` VARCHAR(50) NULL DEFAULT NULL,
    `comments` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_grh_grn_id` (`goods_receipt_id`),
    INDEX `idx_grh_user_id` (`user_id`),
    CONSTRAINT `fk_grh_grn` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_grh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 21. Table: settings
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `setting_type` VARCHAR(50) NOT NULL DEFAULT 'text',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- STATIC SEED DATA
-- ------------------------------------------------------------------------------

-- Seed Roles
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `status`) VALUES
(1, 'Administrator', 'administrator', 'Full system access, user administration, and system configuration', 'active'),
(2, 'Procurement Officer', 'procurement-officer', 'Manages purchase requests, vendor RFQs, quotations, and purchase orders', 'active'),
(3, 'Manager', 'manager', 'Approves requisitions, evaluates purchase orders, and reviews budgets', 'active'),
(4, 'Requester', 'requester', 'Creates item purchase requisitions and tracks approval status', 'active');

-- Seed Standard Enterprise Departments
INSERT INTO `departments` (`id`, `name`, `code`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Administration', 'ADM', 'General administration and office management', 'active', NOW(), NOW()),
(2, 'Finance', 'FIN', 'Financial operations, budgets, and treasury management', 'active', NOW(), NOW()),
(3, 'Human Resources', 'HR', 'Talent acquisition, employee relations, and HR ops', 'active', NOW(), NOW()),
(4, 'IT', 'IT', 'Information technology, infrastructure, hardware and software', 'active', NOW(), NOW()),
(5, 'Operations', 'OPS', 'Day-to-day business operations and logistics', 'active', NOW(), NOW()),
(6, 'Procurement', 'PROC', 'Procurement, vendor management, and sourcing', 'active', NOW(), NOW());

-- Seed Default System Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('company_name', 'ProcureCMS Enterprise Ltd.', 'text'),
('company_email', 'procurement@example.com', 'email'),
('company_phone', '+1 (555) 019-2834', 'text'),
('company_address', '100 Enterprise Way, Suite 400, New York, NY 10001', 'textarea'),
('company_logo', NULL, 'image'),
('currency', '$', 'text'),
('date_format', 'd M Y', 'text'),
('timezone', 'Asia/Dhaka', 'text');

SET FOREIGN_KEY_CHECKS = 1;
