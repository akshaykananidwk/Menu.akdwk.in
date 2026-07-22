-- =============================================================================
-- AK Menu System — Migration 1.3.0  (SEO & Discoverability foundation)
-- =============================================================================
-- Idempotent. Uses `{PREFIX}`; the runner replaces it with the live DB_PREFIX.
-- =============================================================================

ALTER TABLE `{PREFIX}tenants`
    ADD COLUMN IF NOT EXISTS `allow_indexing` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`;

CREATE TABLE IF NOT EXISTS `{PREFIX}seo_cities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `city_name` VARCHAR(120) NOT NULL,
  `city_name_gu` VARCHAR(120) DEFAULT NULL,
  `state` VARCHAR(80) DEFAULT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `population_tier` TINYINT DEFAULT 3,
  `meta_title` VARCHAR(200) DEFAULT NULL,
  `meta_description` VARCHAR(300) DEFAULT NULL,
  `h1` VARCHAR(200) DEFAULT NULL,
  `content_html` MEDIUMTEXT DEFAULT NULL,
  `hero_image` VARCHAR(255) DEFAULT NULL,
  `latitude` DECIMAL(10,6) DEFAULT NULL,
  `longitude` DECIMAL(10,6) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `priority` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_city_slug` (`slug`),
  KEY `idx_city_state` (`state`),
  KEY `idx_city_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}seo_pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_key` VARCHAR(80) NOT NULL,
  `url_path` VARCHAR(190) NOT NULL,
  `meta_title` VARCHAR(200) DEFAULT NULL,
  `meta_description` VARCHAR(300) DEFAULT NULL,
  `h1` VARCHAR(200) DEFAULT NULL,
  `canonical` VARCHAR(255) DEFAULT NULL,
  `robots` VARCHAR(40) DEFAULT 'index,follow',
  `og_image` VARCHAR(255) DEFAULT NULL,
  `schema_json` MEDIUMTEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_page_key` (`page_key`),
  KEY `idx_page_path` (`url_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}seo_keywords` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `keyword` VARCHAR(190) NOT NULL,
  `type` VARCHAR(30) DEFAULT 'primary',
  `target_url` VARCHAR(255) DEFAULT NULL,
  `search_volume` INT DEFAULT NULL,
  `current_rank` INT DEFAULT NULL,
  `last_checked` DATE DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kw_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}blog_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` VARCHAR(300) DEFAULT NULL,
  `meta_title` VARCHAR(200) DEFAULT NULL,
  `meta_description` VARCHAR(300) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bcat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}blog_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `excerpt` VARCHAR(400) DEFAULT NULL,
  `content` MEDIUMTEXT DEFAULT NULL,
  `featured_image` VARCHAR(255) DEFAULT NULL,
  `image_alt` VARCHAR(255) DEFAULT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `tags` VARCHAR(255) DEFAULT NULL,
  `meta_title` VARCHAR(200) DEFAULT NULL,
  `meta_description` VARCHAR(300) DEFAULT NULL,
  `author` VARCHAR(120) DEFAULT NULL,
  `status` ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME DEFAULT NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `reading_time` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_slug` (`slug`),
  KEY `idx_post_status` (`status`),
  KEY `idx_post_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}redirects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_url` VARCHAR(255) NOT NULL,
  `to_url` VARCHAR(255) NOT NULL,
  `type` ENUM('301','302') NOT NULL DEFAULT '301',
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_from` (`from_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}error_404_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `url` VARCHAR(255) NOT NULL,
  `referrer` VARCHAR(255) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_404_url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
