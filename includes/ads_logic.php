<?php
// includes/ads_logic.php
// Reklamları veritabanından çekmek için ortak mantık

// Değişkenleri başlat
$carouselAds = [];
$sidebarLeftAds = [];
$sidebarRightAds = [];
$footerAds = [];

try {
    // $db bağlantısı yoksa oluştur
    if (!isset($db)) {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        $db = Database::getInstance()->getConnection();
    }

    if (isset($db)) {
        // Ads tablosu var mı kontrol et
        $checkAds = $db->query("SHOW TABLES LIKE 'ads'");
        if ($checkAds->rowCount() > 0) {
            // Carousel reklamları (birden fazla olabilir)
            $carouselAds = $db->query("SELECT * FROM ads WHERE position = 'carousel' AND is_active = 1 ORDER BY sort_order, id DESC")->fetchAll();
            
            // Sol sidebar (sadece 1 tane)
            $sidebarLeftAds = $db->query("SELECT * FROM ads WHERE position = 'sidebar_left' AND is_active = 1 ORDER BY sort_order, id DESC LIMIT 1")->fetchAll();
            
            // Sağ sidebar (sadece 1 tane)
            // 'sidebar' eski değer, 'sidebar_right' yeni değer - ikisini de kabul et
            $sidebarRightAds = $db->query("SELECT * FROM ads WHERE (position = 'sidebar_right' OR position = 'sidebar') AND is_active = 1 ORDER BY sort_order, id DESC LIMIT 1")->fetchAll();
            
            // Footer banner (sadece 1 tane)
            $footerAds = $db->query("SELECT * FROM ads WHERE position = 'footer' AND is_active = 1 ORDER BY sort_order, id DESC LIMIT 1")->fetchAll();
        }
    }
} catch (Exception $e) {
    // Hata olursa (tablo yoksa vb.) boş array döner, sayfa patlamasın
    error_log("Ads fetch error: " . $e->getMessage());
}
?>
