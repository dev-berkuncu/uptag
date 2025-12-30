-- Ödeme Sistemi Tabloları
-- Bu dosyayı MySQL'de çalıştırın

-- Cüzdan tablosu (kullanıcı bakiyeleri)
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `balance` DECIMAL(15,2) DEFAULT 0.00,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İşlem geçmişi
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` ENUM('deposit', 'withdraw', 'transfer_in', 'transfer_out') NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `reference_id` VARCHAR(100) DEFAULT NULL,
    `from_user_id` INT DEFAULT NULL,
    `to_user_id` INT DEFAULT NULL,
    `description` TEXT,
    `status` ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_reference` (`reference_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
