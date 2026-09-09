-- ==============================================================================
-- Procurement Management CMS - Phase 06 Database Schema
-- Module: System Settings & Reports Foundation
-- Database: procurement_mgt
--
-- IMPORT ORDER:
-- 1. database/01_auth_schema.sql
-- 2. database/02_purchase_requests_schema.sql
-- 3. database/03_suppliers_quotations_schema.sql
-- 4. database/04_purchase_orders_schema.sql
-- 5. database/05_goods_receiving_schema.sql
-- 6. database/06_reports_settings_schema.sql
-- ==============================================================================

USE `procurement_mgt`;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Table: settings
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
-- 2. SEED DATA: Default System Settings
-- ------------------------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('company_name', 'ProcureCMS Enterprise Ltd.', 'text'),
('company_email', 'procurement@example.com', 'email'),
('company_phone', '+1 (555) 019-2834', 'text'),
('company_address', '100 Enterprise Way, Suite 400, New York, NY 10001', 'textarea'),
('company_logo', NULL, 'image'),
('currency', '$', 'text'),
('date_format', 'd M Y', 'text'),
('timezone', 'Asia/Dhaka', 'text');

-- Initial Activity Log for Settings Schema
INSERT INTO `activity_logs` (`user_id`, `action`, `description`, `ip_address`, `user_agent`) VALUES
(1, 'System Initialized', 'Phase 06 System Settings and Reports foundation schema successfully configured.', '127.0.0.1', 'CLI-Installer');

SET FOREIGN_KEY_CHECKS = 1;
