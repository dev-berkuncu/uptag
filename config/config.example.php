<?php
/**
 * Uptag - Ana Yapılandırma Dosyası
 * 
 * Bu dosyayı config.php olarak kopyalayın ve kendi ayarlarınızı girin.
 */

// Hata raporlama (geliştirme ortamı için)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zaman dilimi ayarı
date_default_timezone_set('Europe/Istanbul');

// Oturum başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Veritabanı ayarları - KENDİ BİLGİLERİNİZİ GİRİN
define('DB_HOST', 'localhost');
define('DB_NAME', 'veritabani_adi');
define('DB_USER', 'kullanici_adi');
define('DB_PASS', 'sifre');
define('DB_CHARSET', 'utf8mb4');

// Site ayarları - KENDİ DOMAİNİNİZİ GİRİN
define('SITE_NAME', 'Uptag');
define('BASE_URL', 'https://siteniz.com');

// Güvenlik ayarları
define('SESSION_LIFETIME', 3600 * 24); // 24 saat

// Dosya yolları
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CLASSES_PATH', ROOT_PATH . '/classes');

// Veritabanı bağlantı sınıfı
require_once __DIR__ . '/database.php';

// Autoloader
spl_autoload_register(function ($class) {
    $file = CLASSES_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Yardımcı fonksiyonlar
require_once INCLUDES_PATH . '/functions.php';
