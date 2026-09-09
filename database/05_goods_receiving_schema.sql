-- ==============================================================================
-- Procurement Management CMS - Phase 05 Database Schema
-- Module: Goods Receiving / GRN Management
-- Database: procurement_mgt
--
-- IMPORT ORDER:
-- 1. database/01_auth_schema.sql
-- 2. database/02_purchase_requests_schema.sql
-- 3. database/03_suppliers_quotations_schema.sql
-- 4. database/04_purchase_orders_schema.sql
-- 5. database/05_goods_receiving_schema.sql
-- ==============================================================================

USE `procurement_mgt`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Table: goods_receipts
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
-- 2. Table: goods_receipt_items
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
-- 3. Table: goods_receipt_history
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

SET FOREIGN_KEY_CHECKS = 1;
