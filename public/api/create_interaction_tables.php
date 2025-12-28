<?php
/**
 * Post Etkileşimleri Tablolarını Oluştur
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

$db = Database::getInstance()->getConnection();

try {
    // post_likes tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS post_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            checkin_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_like (user_id, checkin_id),
            INDEX idx_checkin (checkin_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ post_likes tablosu oluşturuldu\n";
    
    // post_reposts tablosu (quote repost desteği ile)
    $db->exec("
        CREATE TABLE IF NOT EXISTS post_reposts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            checkin_id INT NOT NULL,
            quote TEXT NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_checkin (checkin_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ post_reposts tablosu oluşturuldu\n";
    
    // post_comments tablosu
    $db->exec("
        CREATE TABLE IF NOT EXISTS post_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            checkin_id INT NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_checkin (checkin_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ post_comments tablosu oluşturuldu\n";
    
    echo "\n🎉 Tüm tablolar başarıyla oluşturuldu!\n";
    
} catch (PDOException $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
