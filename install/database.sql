-- ============================================================
-- AK Menu System - Database Schema
-- Multi-tenant SaaS Digital Restaurant Menu & Ordering System
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- NOTE: {PREFIX} is replaced by the installer with the chosen table prefix.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- Drop any existing tables (makes the installer safely re-runnable if a
-- previous attempt half-completed). FK checks are disabled above so order
-- does not matter.
DROP TABLE IF EXISTS `{PREFIX}super_admins`;
DROP TABLE IF EXISTS `{PREFIX}plans`;
DROP TABLE IF EXISTS `{PREFIX}templates`;
DROP TABLE IF EXISTS `{PREFIX}standee_templates`;
DROP TABLE IF EXISTS `{PREFIX}tenants`;
DROP TABLE IF EXISTS `{PREFIX}staff`;
DROP TABLE IF EXISTS `{PREFIX}categories`;
DROP TABLE IF EXISTS `{PREFIX}items`;
DROP TABLE IF EXISTS `{PREFIX}item_variants`;
DROP TABLE IF EXISTS `{PREFIX}item_addons`;
DROP TABLE IF EXISTS `{PREFIX}tables`;
DROP TABLE IF EXISTS `{PREFIX}orders`;
DROP TABLE IF EXISTS `{PREFIX}order_items`;
DROP TABLE IF EXISTS `{PREFIX}feedback`;
DROP TABLE IF EXISTS `{PREFIX}invoices`;
DROP TABLE IF EXISTS `{PREFIX}tickets`;
DROP TABLE IF EXISTS `{PREFIX}ticket_replies`;
DROP TABLE IF EXISTS `{PREFIX}notices`;
DROP TABLE IF EXISTS `{PREFIX}settings`;
DROP TABLE IF EXISTS `{PREFIX}ai_logs`;
DROP TABLE IF EXISTS `{PREFIX}activity_logs`;
DROP TABLE IF EXISTS `{PREFIX}login_attempts`;
DROP TABLE IF EXISTS `{PREFIX}migrations`;
DROP TABLE IF EXISTS `{PREFIX}update_logs`;
DROP TABLE IF EXISTS `{PREFIX}backups`;
DROP TABLE IF EXISTS `{PREFIX}whatsapp_settings`;
DROP TABLE IF EXISTS `{PREFIX}whatsapp_templates`;
DROP TABLE IF EXISTS `{PREFIX}whatsapp_logs`;
DROP TABLE IF EXISTS `{PREFIX}whatsapp_inbox`;
DROP TABLE IF EXISTS `{PREFIX}otp_verifications`;

-- ------------------------------------------------------------
-- Super Admins
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}super_admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Plans
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `validity_days` INT NOT NULL DEFAULT 365,
  `max_items` INT NOT NULL DEFAULT 100,
  `max_categories` INT NOT NULL DEFAULT 20,
  `max_outlets` INT NOT NULL DEFAULT 1,
  `max_waiters` INT NOT NULL DEFAULT 3,
  `max_tables` INT NOT NULL DEFAULT 20,
  `ai_credits` INT NOT NULL DEFAULT 5,
  `features_json` TEXT DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Templates (menu design)
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `folder` VARCHAR(80) NOT NULL,
  `preview_image` VARCHAR(255) DEFAULT NULL,
  `category` VARCHAR(60) DEFAULT 'Modern',
  `is_premium` TINYINT(1) NOT NULL DEFAULT 0,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tpl_folder` (`folder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Standee Templates
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}standee_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `folder` VARCHAR(80) NOT NULL,
  `preview_image` VARCHAR(255) DEFAULT NULL,
  `size` VARCHAR(30) DEFAULT 'A4',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tenants (restaurants)
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}tenants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_name` VARCHAR(160) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `owner_name` VARCHAR(120) DEFAULT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(80) DEFAULT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `cover_image` VARCHAR(255) DEFAULT NULL,
  `plan_id` INT UNSIGNED DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `ai_credits_used` INT NOT NULL DEFAULT 0,
  `ordering_mode` ENUM('direct','waiter','view_only') NOT NULL DEFAULT 'view_only',
  `template_id` INT UNSIGNED DEFAULT NULL,
  `primary_color` VARCHAR(20) DEFAULT '#e63946',
  `secondary_color` VARCHAR(20) DEFAULT '#1d3557',
  `accent_color` VARCHAR(20) DEFAULT '#f1a208',
  `font_family` VARCHAR(80) DEFAULT 'Poppins',
  `language` VARCHAR(5) DEFAULT 'en',
  `banner_image` VARCHAR(255) DEFAULT NULL,
  `show_prices` TINYINT(1) NOT NULL DEFAULT 1,
  `show_images` TINYINT(1) NOT NULL DEFAULT 1,
  `show_veg_marker` TINYINT(1) NOT NULL DEFAULT 1,
  `show_descriptions` TINYINT(1) NOT NULL DEFAULT 1,
  `gst_no` VARCHAR(30) DEFAULT NULL,
  `cgst` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `sgst` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `service_charge` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `min_order_value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(6) DEFAULT '₹',
  `google_review_url` VARCHAR(255) DEFAULT NULL,
  `whatsapp_no` VARCHAR(20) DEFAULT NULL,
  `maps_url` VARCHAR(255) DEFAULT NULL,
  `facebook_url` VARCHAR(255) DEFAULT NULL,
  `instagram_url` VARCHAR(255) DEFAULT NULL,
  `opening_time` TIME DEFAULT '09:00:00',
  `closing_time` TIME DEFAULT '23:00:00',
  `onboarded` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','suspended','expired') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_slug` (`slug`),
  KEY `idx_tenant_plan` (`plan_id`),
  KEY `idx_tenant_status` (`status`),
  KEY `idx_tenant_expiry` (`expiry_date`),
  CONSTRAINT `fk_tenant_plan` FOREIGN KEY (`plan_id`) REFERENCES `{PREFIX}plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tenant_template` FOREIGN KEY (`template_id`) REFERENCES `{PREFIX}templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Staff (waiter / kitchen)
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}staff` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `pin` VARCHAR(255) NOT NULL,
  `role` ENUM('waiter','kitchen') NOT NULL DEFAULT 'waiter',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_tenant` (`tenant_id`),
  CONSTRAINT `fk_staff_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Categories
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `name_gu` VARCHAR(160) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `icon` VARCHAR(60) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `available_from` TIME DEFAULT NULL,
  `available_to` TIME DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_tenant` (`tenant_id`),
  CONSTRAINT `fk_cat_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Items
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(160) NOT NULL,
  `name_gu` VARCHAR(200) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `description_gu` TEXT DEFAULT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount_price` DECIMAL(10,2) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `is_veg` TINYINT(1) NOT NULL DEFAULT 1,
  `is_jain` TINYINT(1) NOT NULL DEFAULT 0,
  `spice_level` TINYINT NOT NULL DEFAULT 0,
  `prep_time` INT DEFAULT NULL,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `is_bestseller` TINYINT(1) NOT NULL DEFAULT 0,
  `is_new` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `tags` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item_tenant` (`tenant_id`),
  KEY `idx_item_cat` (`category_id`),
  CONSTRAINT `fk_item_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_cat` FOREIGN KEY (`category_id`) REFERENCES `{PREFIX}categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Item Variants
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}item_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_var_item` (`item_id`),
  CONSTRAINT `fk_var_item` FOREIGN KEY (`item_id`) REFERENCES `{PREFIX}items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Item Add-ons / Modifiers
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}item_addons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_addon_item` (`item_id`),
  CONSTRAINT `fk_addon_item` FOREIGN KEY (`item_id`) REFERENCES `{PREFIX}items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tables / Rooms
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}tables` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `table_no` VARCHAR(40) NOT NULL,
  `section` VARCHAR(60) DEFAULT NULL,
  `qr_token` VARCHAR(64) NOT NULL,
  `status` ENUM('free','occupied','billed') NOT NULL DEFAULT 'free',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_table_token` (`qr_token`),
  KEY `idx_table_tenant` (`tenant_id`),
  CONSTRAINT `fk_table_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Orders
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `order_no` VARCHAR(40) NOT NULL,
  `table_id` INT UNSIGNED DEFAULT NULL,
  `staff_id` INT UNSIGNED DEFAULT NULL,
  `customer_name` VARCHAR(120) DEFAULT NULL,
  `customer_mobile` VARCHAR(20) DEFAULT NULL,
  `order_type` ENUM('dinein','takeaway','delivery') NOT NULL DEFAULT 'dinein',
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `service_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_mode` VARCHAR(20) NOT NULL DEFAULT 'cash',
  `payment_status` ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  `payment_ref` VARCHAR(120) DEFAULT NULL,
  `status` ENUM('new','accepted','preparing','ready','served','completed','cancelled') NOT NULL DEFAULT 'new',
  `notes` VARCHAR(255) DEFAULT NULL,
  `coupon_code` VARCHAR(40) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_tenant` (`tenant_id`),
  KEY `idx_order_status` (`status`),
  KEY `idx_order_table` (`table_id`),
  KEY `idx_order_created` (`created_at`),
  CONSTRAINT `fk_order_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Order Items
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED DEFAULT NULL,
  `item_name` VARCHAR(160) NOT NULL,
  `variant_label` VARCHAR(80) DEFAULT NULL,
  `addons_json` TEXT DEFAULT NULL,
  `qty` INT NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `notes` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oi_order` (`order_id`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `{PREFIX}orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Feedback
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED DEFAULT NULL,
  `customer_name` VARCHAR(120) DEFAULT NULL,
  `mobile` VARCHAR(20) DEFAULT NULL,
  `rating` TINYINT NOT NULL DEFAULT 5,
  `comment` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fb_tenant` (`tenant_id`),
  CONSTRAINT `fk_fb_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Coupons / Promo codes
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}coupons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(40) NOT NULL,
  `type` ENUM('flat','percent') NOT NULL DEFAULT 'flat',
  `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `usage_limit` INT NOT NULL DEFAULT 0,
  `used_count` INT NOT NULL DEFAULT 0,
  `expiry_date` DATE DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`tenant_id`, `code`),
  KEY `idx_coupon_tenant` (`tenant_id`),
  CONSTRAINT `fk_coupon_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Invoices
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `invoice_no` VARCHAR(40) NOT NULL,
  `plan_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `paid_on` DATE DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inv_tenant` (`tenant_id`),
  CONSTRAINT `fk_inv_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Plan Requests (client subscription upgrade/renewal requests)
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}plan_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `method` ENUM('offline','online') NOT NULL DEFAULT 'offline',
  `txn_ref` VARCHAR(120) DEFAULT NULL,
  `screenshot` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pr_tenant` (`tenant_id`),
  KEY `idx_pr_status` (`status`),
  CONSTRAINT `fk_pr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Support Tickets
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tk_tenant` (`tenant_id`),
  CONSTRAINT `fk_tk_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `{PREFIX}tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{PREFIX}ticket_replies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id` INT UNSIGNED NOT NULL,
  `sender` ENUM('client','admin') NOT NULL DEFAULT 'client',
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tr_ticket` (`ticket_id`),
  CONSTRAINT `fk_tr_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `{PREFIX}tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Notices (broadcast)
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}notices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Global Settings (white-label)
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- AI Logs
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}ai_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `type` VARCHAR(60) DEFAULT 'menu_ocr',
  `tokens` INT DEFAULT 0,
  `status` VARCHAR(20) DEFAULT 'success',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Activity Logs
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` VARCHAR(30) DEFAULT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_act_user` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Login attempts (rate limiting)
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier` VARCHAR(160) NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login_ident` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Update system tables
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(20) NOT NULL,
  `file_name` VARCHAR(160) NOT NULL,
  `executed_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mig_file` (`file_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{PREFIX}update_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_version` VARCHAR(20) DEFAULT NULL,
  `to_version` VARCHAR(20) DEFAULT NULL,
  `status` VARCHAR(20) DEFAULT 'pending',
  `log_text` LONGTEXT DEFAULT NULL,
  `performed_by` VARCHAR(120) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{PREFIX}backups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_name` VARCHAR(200) NOT NULL,
  `type` ENUM('db','files','full') NOT NULL DEFAULT 'db',
  `size` BIGINT DEFAULT 0,
  `version` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- WhatsApp tables
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}whatsapp_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` LONGTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wa_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{PREFIX}whatsapp_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trigger_key` VARCHAR(60) NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `message_en` TEXT DEFAULT NULL,
  `message_gu` TEXT DEFAULT NULL,
  `media_type` VARCHAR(20) DEFAULT 'text',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wa_trigger` (`trigger_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{PREFIX}whatsapp_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `trigger_key` VARCHAR(60) DEFAULT NULL,
  `number` VARCHAR(20) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `media_url` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `response` TEXT DEFAULT NULL,
  `retry_count` INT NOT NULL DEFAULT 0,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wa_status` (`status`),
  KEY `idx_wa_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{PREFIX}whatsapp_inbox` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED DEFAULT NULL,
  `number` VARCHAR(20) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `media_url` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wain_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `{PREFIX}otp_verifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mobile` VARCHAR(20) NOT NULL,
  `otp` VARCHAR(10) NOT NULL,
  `purpose` VARCHAR(40) DEFAULT 'login',
  `attempts` INT NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `is_used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_mobile` (`mobile`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Menu views (scan analytics — aggregated per tenant per day)
-- ------------------------------------------------------------
CREATE TABLE `{PREFIX}menu_views` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `view_date` DATE NOT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_date` (`tenant_id`, `view_date`),
  KEY `idx_view_date` (`view_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default super admin (password: Admin@123)
INSERT INTO `{PREFIX}super_admins` (`name`, `email`, `password`, `mobile`, `status`) VALUES
('Super Admin', 'admin@akdwk.in', '{ADMIN_HASH}', '9978123146', 1);

-- Sample plans
INSERT INTO `{PREFIX}plans` (`name`, `price`, `validity_days`, `max_items`, `max_categories`, `max_outlets`, `max_waiters`, `max_tables`, `ai_credits`, `features_json`, `status`) VALUES
('Free Trial', 0.00, 7, 100000, 10000, 50, 100, 500, 50, '{"direct_ordering":true,"waiter_ordering":true,"kot_screen":true,"payment_gateway":true,"analytics":true,"whatsapp":true,"multi_language":true,"remove_branding":false,"custom_domain":true,"ai_photo":true}', 1),
('Starter', 999.00, 365, 50, 10, 1, 2, 10, 3, '{"direct_ordering":false,"waiter_ordering":false,"kot_screen":false,"payment_gateway":false,"analytics":false,"whatsapp":true,"multi_language":true,"remove_branding":false,"custom_domain":false,"ai_photo":false}', 1),
('Professional', 2499.00, 365, 200, 30, 2, 8, 40, 15, '{"direct_ordering":true,"waiter_ordering":true,"kot_screen":true,"payment_gateway":false,"analytics":true,"whatsapp":true,"multi_language":true,"remove_branding":false,"custom_domain":false,"ai_photo":true}', 1),
('Enterprise', 4999.00, 365, 1000, 100, 10, 50, 200, 60, '{"direct_ordering":true,"waiter_ordering":true,"kot_screen":true,"payment_gateway":true,"analytics":true,"whatsapp":true,"multi_language":true,"remove_branding":true,"custom_domain":true,"ai_photo":true}', 1);

-- Sample menu templates
INSERT INTO `{PREFIX}templates` (`name`, `folder`, `preview_image`, `category`, `is_premium`, `is_default`, `status`) VALUES
('Modern', 'modern', 'assets/img/tpl-modern.png', 'Modern', 0, 1, 1),
('Classic', 'classic', 'assets/img/tpl-classic.png', 'Classic', 0, 0, 1),
('Cafe', 'cafe', 'assets/img/tpl-cafe.png', 'Cafe', 0, 0, 1),
('Fine Dine', 'finedine', 'assets/img/tpl-finedine.png', 'Fine-dine', 1, 0, 1),
('Fast Food', 'fastfood', 'assets/img/tpl-fastfood.png', 'Fast Food', 0, 0, 1);

-- Sample standee templates
INSERT INTO `{PREFIX}standee_templates` (`name`, `folder`, `preview_image`, `size`, `status`) VALUES
('Classic A4 Standee', 'a4', 'assets/img/std-a4.png', 'A4', 1),
('Compact A5 Poster', 'a5', 'assets/img/std-a5.png', 'A5', 1),
('Table Tent 4x6', 'tent', 'assets/img/std-tent.png', 'Table Tent 4x6', 1);

-- Default global settings
INSERT INTO `{PREFIX}settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'AK Menu System'),
('tagline', 'Digital Restaurant Menu & Ordering'),
('logo', ''),
('favicon', ''),
('primary_color', '#e63946'),
('secondary_color', '#1d3557'),
('accent_color', '#f1a208'),
('theme_mode', 'light'),
('font_family', 'Poppins'),
('footer_text', '© AK Menu System'),
('powered_by', 'Powered by AK Computer, Dwarka'),
('support_whatsapp', '9978123146'),
('contact_email', 'support@akdwk.in'),
('currency', '₹'),
('timezone', 'Asia/Kolkata'),
('date_format', 'd-m-Y'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_name', 'AK Menu System'),
('gemini_api_key', ''),
('gemini_model', 'gemini-2.5-flash'),
('gemini_monthly_limit', '1000'),
('razorpay_key_id', ''),
('razorpay_secret', ''),
('github_owner', 'akshaykananidwk'),
('github_repo', 'menu.akdwk.in'),
('github_branch', 'main'),
('github_token', ''),
('update_channel', 'manual'),
('auto_backup', '1'),
('app_version', '1.0.0'),
('maintenance_mode', '0'),
('terms_content', ''),
('privacy_content', ''),
('cron_secret', '{CRON_SECRET}'),
('allow_signup', '1'),
('signup_default_ordering', 'view_only');

-- WhatsApp settings (demo credentials)
INSERT INTO `{PREFIX}whatsapp_settings` (`setting_key`, `setting_value`) VALUES
('enabled', '1'),
('base_url', 'https://bulk.akdwk.in/api.php'),
('api_key', '7016034943'),
('session_id', '9978123146'),
('webhook_url', 'https://bulk.akdwk.in/api/webhook_inbound.php'),
('send_delay', '3'),
('daily_limit', '500');

-- WhatsApp templates
INSERT INTO `{PREFIX}whatsapp_templates` (`trigger_key`, `title`, `message_en`, `message_gu`, `media_type`, `is_active`) VALUES
('signup_welcome', 'Restaurant Signup - Welcome', '🎉 Hello {owner_name}!\n\n{restaurant_name} digital menu is ready ✅\n\n🔗 Your menu link:\n{menu_url}\n\n👤 Login details:\nPanel: https://menu.akdwk.in/client\nUsername: {mobile}\nPassword: {password}\n\n📦 Plan: {plan_name}\n📅 Valid until: {expiry_date}\n\nNeed help? Reply here.\n\n— Powered by AK Computer, Dwarka', '🎉 નમસ્તે {owner_name}!\n\n{restaurant_name} નું ડિજિટલ મેનુ તૈયાર છે ✅\n\n🔗 તમારી મેનુ લિંક:\n{menu_url}\n\n👤 લોગિન વિગત:\nપેનલ: https://menu.akdwk.in/client\nયુઝરનેમ: {mobile}\nપાસવર્ડ: {password}\n\n📦 પ્લાન: {plan_name}\n📅 વેલિડિટી: {expiry_date} સુધી\n\nકોઈ મદદ જોઈએ? જવાબમાં લખો.\n\n— Powered by AK Computer, Dwarka', 'text', 1),
('signup_qr', 'Restaurant Signup - QR Code', 'Your QR Code', 'તમારો QR કોડ', 'image', 1),
('signup_standee', 'Restaurant Signup - Standee', 'Print-ready standee', 'પ્રિન્ટ કરવા માટે તૈયાર સ્ટેન્ડી', 'document', 1),
('menu_published', 'Menu Published', 'Your updated menu is live: {menu_url}', 'તમારું અપડેટ કરેલું મેનુ લાઈવ છે: {menu_url}', 'text', 1),
('new_order', 'New Order', 'New order {order_no} on table {table_no}\nItems: {items}\nTotal: {total}', 'નવો ઓર્ડર {order_no} ટેબલ {table_no}\nઆઈટમ: {items}\nકુલ: {total}', 'text', 1),
('order_ready', 'Order Ready', 'Your order {order_no} is ready!', 'તમારો ઓર્ડર {order_no} તૈયાર છે!', 'text', 1),
('order_completed', 'Order Completed', 'Thank you! Your bill: {invoice_url}', 'આભાર! તમારું બિલ: {invoice_url}', 'document', 1),
('plan_expiring', 'Plan Expiring', 'Your plan expires on {expiry_date}. Please renew.', 'તમારો પ્લાન {expiry_date} ના રોજ સમાપ્ત થાય છે. કૃપા કરીને રિન્યુ કરો.', 'text', 1),
('plan_expired', 'Plan Expired', 'Your service is paused. Please renew to continue.', 'તમારી સેવા થોભાવવામાં આવી છે. ચાલુ રાખવા રિન્યુ કરો.', 'text', 1),
('payment_received', 'Payment Received', 'Payment received. Invoice: {invoice_url}', 'ચુકવણી પ્રાપ્ત થઈ. ઇન્વૉઇસ: {invoice_url}', 'document', 1),
('otp', 'OTP', 'Your OTP is {otp}. Valid for 5 minutes.', 'તમારો OTP છે {otp}. 5 મિનિટ માટે માન્ય.', 'text', 1),
('ticket_reply', 'Support Reply', 'We replied to your ticket. Login to view.', 'અમે તમારી ટિકિટનો જવાબ આપ્યો છે. જોવા લોગિન કરો.', 'text', 1),
('daily_summary', 'Daily Summary', 'Today: {order_no} orders, revenue {total}, top item {items}', 'આજે: {order_no} ઓર્ડર, આવક {total}, ટોપ આઈટમ {items}', 'text', 1);
