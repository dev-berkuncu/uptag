<?php
/**
 * Takip Sistemi Migration
 * Follows tablosunu oluşturur
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Takip Sistemi Migration ===\n\n";

$db = Database::getInstance()->getConnection();

try {
    // Follows tablosunu oluştur
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_follows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT NOT NULL,
            following_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_follower (follower_id),
            INDEX idx_following (following_id),
            UNIQUE KEY unique_follow (follower_id, following_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ user_follows tablosu oluşturuldu\n";
    
    echo "\n=== Migration Tamamlandı! ===\n";
    
} catch (PDOException $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>

