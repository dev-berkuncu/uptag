-- Uptag Check-in Uygulaması Veritabanı Şeması
-- MySQL 5.7+ uyumlu

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Veritabanı oluştur
CREATE DATABASE IF NOT EXISTS `uptag` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `uptag`;

-- Kullanıcılar tablosu
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `tag` varchar(30) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `banned_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `tag` (`tag`),
  UNIQUE KEY `email` (`email`),
  KEY `is_active` (`is_active`),
  KEY `is_admin` (`is_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mekanlar tablosu
CREATE TABLE IF NOT EXISTS `venues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text,
  `address` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`),
  KEY `name` (`name`),
  FULLTEXT KEY `name_search` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Check-in'ler tablosu
CREATE TABLE IF NOT EXISTS `checkins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_excluded_from_leaderboard` tinyint(1) NOT NULL DEFAULT 0,
  `is_flagged` tinyint(1) NOT NULL DEFAULT 0,
  `flagged_reason` text,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `venue_id` (`venue_id`),
  KEY `created_at` (`created_at`),
  KEY `is_excluded` (`is_excluded_from_leaderboard`),
  KEY `user_venue_time` (`user_id`, `venue_id`, `created_at`),
  CONSTRAINT `checkins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `checkins_ibfk_2` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin işlem logları tablosu
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `action_type` (`action_type`),
  KEY `target_type` (`target_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sistem ayarları tablosu
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` text,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan sistem ayarları
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('leaderboard_top_users', '20', 'Haftalık leaderboard\'da gösterilecek top kullanıcı sayısı'),
('leaderboard_top_venues', '20', 'Haftalık leaderboard\'da gösterilecek top mekan sayısı'),
('checkin_cooldown_seconds', '300', 'Aynı mekana tekrar check-in için bekleme süresi (saniye)'),
('checkin_rate_limit_count', '10', 'Rate limit için maksimum check-in sayısı'),
('checkin_rate_limit_window_seconds', '3600', 'Rate limit zaman penceresi (saniye)'),
('site_name', 'Uptag', 'Site adı'),
('contact_email', 'admin@uptag.com', 'İletişim e-postası'),
('week_start_day', 'monday', 'Haftanın başlangıç günü (monday/sunday)'),
('timezone', 'Europe/Istanbul', 'Zaman dilimi');

-- Varsayılan admin kullanıcı (şifre: admin123 - üretimde değiştirilmeli!)
-- Şifre hash'i: password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO `users` (`username`, `email`, `password_hash`, `is_admin`, `is_active`) VALUES
('admin', 'admin@uptag.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1);

