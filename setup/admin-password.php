<?php
/**
 * Admin Şifre Hash Oluşturucu
 * 
 * Bu script, admin kullanıcısı için yeni bir şifre hash'i oluşturur.
 * Kullanım: php setup/admin-password.php
 * 
 * VEYA tarayıcıdan: http://localhost/Sociaera/setup/admin-password.php?password=YENI_SIFRE
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// Güvenlik: Sadece CLI veya GET parametresi ile çalışsın
$password = null;

if (php_sapi_name() === 'cli') {
    // CLI modu
    if (isset($argv[1])) {
        $password = $argv[1];
    } else {
        echo "Kullanım: php admin-password.php YENI_SIFRE\n";
        exit(1);
    }
} else {
    // Web modu
    $password = $_GET['password'] ?? null;
    if (!$password) {
        die("Kullanım: ?password=YENI_SIFRE");
    }
}

if (strlen($password) < 6) {
    die("Şifre en az 6 karakter olmalıdır.\n");
}

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Şifre Hash'i:\n";
echo $hash . "\n\n";

// Veritabanında güncelle (opsiyonel)
if (php_sapi_name() !== 'cli') {
    echo "Veritabanında güncellemek ister misiniz? (Y/N): ";
    // Web modunda otomatik güncelle
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
        $stmt->execute([$hash]);
        echo "\n✓ Admin şifresi başarıyla güncellendi!\n";
    } catch (Exception $e) {
        echo "\n✗ Hata: " . $e->getMessage() . "\n";
    }
} else {
    echo "Veritabanında güncellemek için SQL komutu:\n";
    echo "UPDATE users SET password_hash = '$hash' WHERE username = 'admin';\n";
}


