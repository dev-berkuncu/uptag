<?php
/**
 * Quote Repost Migration Script
 * Veritabanını quote repost için günceller
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Quote Repost Migration ===\n\n";

// Database bağlantısını doğru şekilde al
$db = Database::getInstance()->getConnection();

try {
    // 1. Quote kolonu var mı kontrol et
    $checkCol = $db->query("SHOW COLUMNS FROM post_reposts LIKE 'quote'");
    if ($checkCol->rowCount() == 0) {
        // Quote kolonu ekle
        $db->exec("ALTER TABLE post_reposts ADD COLUMN quote TEXT NULL DEFAULT NULL AFTER checkin_id");
        echo "✅ 'quote' kolonu eklendi\n";
    } else {
        echo "ℹ️ 'quote' kolonu zaten mevcut\n";
    }
    
    // 2. Unique constraint'i kaldır
    $checkIdx = $db->query("SHOW INDEX FROM post_reposts WHERE Key_name = 'unique_repost'");
    if ($checkIdx->rowCount() > 0) {
        $db->exec("ALTER TABLE post_reposts DROP INDEX unique_repost");
        echo "✅ 'unique_repost' constraint kaldırıldı\n";
    } else {
        echo "ℹ️ 'unique_repost' constraint zaten yok\n";
    }
    
    echo "\n=== Migration Tamamlandı! ===\n";
    echo "Artık quote repost özelliği kullanılabilir.\n";
    
} catch (PDOException $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
?>
