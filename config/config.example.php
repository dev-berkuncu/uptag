<?php
/**
 * Uptag - Örnek Yapılandırma Dosyası
 * 
 * Bu dosya config.php'nin içeriğini gösterir.
 * Hassas bilgiler .env dosyasından okunur.
 * 
 * Kurulum:
 * 1. .env.example dosyasını .env olarak kopyalayın
 * 2. .env dosyasındaki değerleri düzenleyin
 * 3. Bu dosyayı config.php olarak kopyalayın (veya olduğu gibi bırakın)
 */

// Environment loader'ı yükle
require_once __DIR__ . '/env.php';

// .env dosyasını yükle
loadEnv(dirname(__DIR__) . '/.env');

// Environment'a göre hata raporlama
$isProd = env('APP_ENV', 'production') === 'production';
if ($isProd) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Zaman dilimi ayarı
date_default_timezone_set('Europe/Istanbul');

// Oturum başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Veritabanı ayarları (zorunlu)
define('DB_HOST', env_required('DB_HOST'));
define('DB_NAME', env_required('DB_NAME'));
define('DB_USER', env_required('DB_USER'));
define('DB_PASS', env_required('DB_PASS'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Site ayarları
define('SITE_NAME', env('SITE_NAME', 'Uptag'));
define('BASE_URL', env_required('BASE_URL'));

// Güvenlik ayarları
define('SESSION_LIFETIME', (int) env('SESSION_LIFETIME', 86400)); // 24 saat

// OAuth Ayarları (GTA World)
define('OAUTH_CLIENT_ID', env_required('OAUTH_CLIENT_ID'));
define('OAUTH_CLIENT_SECRET', env_required('OAUTH_CLIENT_SECRET'));
define('OAUTH_REDIRECT_URI', BASE_URL . '/oauth-callback');

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
