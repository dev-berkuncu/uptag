<?php
/**
 * Repost'lara beğeni ve yorum desteği eklemek için migration
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Repost Etkileşimleri Migration ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // post_likes tablosunda repost_id var mı kontrol et
    $checkLikes = $db->query("SHOW COLUMNS FROM post_likes LIKE 'repost_id'");
    if ($checkLikes->rowCount() == 0) {
        $db->exec("ALTER TABLE post_likes ADD COLUMN repost_id INT(11) NULL DEFAULT NULL AFTER checkin_id");
        echo "✅ post_likes tablosuna repost_id kolonu eklendi\n";
        
        // Index ekle
        $db->exec("ALTER TABLE post_likes ADD KEY repost_id (repost_id)");
        echo "✅ post_likes.repost_id için index eklendi\n";
    } else {
        echo "ℹ️ post_likes.repost_id zaten mevcut\n";
    }
    
    // post_comments tablosunda repost_id var mı kontrol et
    $checkComments = $db->query("SHOW COLUMNS FROM post_comments LIKE 'repost_id'");
    if ($checkComments->rowCount() == 0) {
        $db->exec("ALTER TABLE post_comments ADD COLUMN repost_id INT(11) NULL DEFAULT NULL AFTER checkin_id");
        echo "✅ post_comments tablosuna repost_id kolonu eklendi\n";
        
        // Index ekle
        $db->exec("ALTER TABLE post_comments ADD KEY repost_id (repost_id)");
        echo "✅ post_comments.repost_id için index eklendi\n";
    } else {
        echo "ℹ️ post_comments.repost_id zaten mevcut\n";
    }
    
    echo "\n✅ Migration tamamlandı!\n";
    
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
}
