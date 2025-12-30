<?php
/**
 * Veritabanı Kurulum Scripti
 * Bu script veritabanını ve tabloları otomatik olarak oluşturur
 */

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Veritabanı ayarları
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'uptag';

echo "<h1>Uptag Veritabanı Kurulumu</h1>";
echo "<pre>";

try {
    // Veritabanı bağlantısı (veritabanı olmadan)
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ MySQL bağlantısı başarılı\n";
    
    // Veritabanını oluştur
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Veritabanı '$db_name' oluşturuldu\n";
    
    // Veritabanını seç
    $pdo->exec("USE `$db_name`");
    echo "✓ Veritabanı seçildi\n";
    
    // Schema dosyasını oku
    $schema_file = __DIR__ . '/database/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("Schema dosyası bulunamadı: $schema_file");
    }
    
    $schema = file_get_contents($schema_file);
    
    // CREATE DATABASE ve USE komutlarını kaldır (zaten yaptık)
    $schema = preg_replace('/CREATE DATABASE.*?;/i', '', $schema);
    $schema = preg_replace('/USE.*?;/i', '', $schema);
    
    // SQL komutlarını ayır ve çalıştır
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // IF NOT EXISTS kullandığımız için bazı hatalar normal olabilir
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠ Uyarı: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "✓ Tablolar oluşturuldu\n";
    echo "✓ Varsayılan ayarlar eklendi\n";
    echo "✓ Admin kullanıcı oluşturuldu\n\n";
    
    echo "<h2>Kurulum Tamamlandı!</h2>\n";
    echo "<p><strong>Admin Bilgileri:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Kullanıcı adı: <strong>admin</strong></li>\n";
    echo "<li>Şifre: <strong>admin123</strong></li>\n";
    echo "</ul>\n";
    echo "<p style='color: red;'><strong>⚠ ÖNEMLİ:</strong> Üretim ortamında mutlaka bu şifreyi değiştirin!</p>\n";
    echo "<p><a href='index.php'>Ana Sayfaya Git</a> | <a href='admin/index.php'>Admin Paneline Git</a></p>\n";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Hata!</h2>\n";
    echo "<p>Veritabanı bağlantı hatası: " . $e->getMessage() . "</p>\n";
    echo "<p><strong>Kontrol Edin:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>XAMPP Control Panel'de MySQL servisinin çalıştığından emin olun</li>\n";
    echo "<li>Veritabanı kullanıcı adı ve şifresinin doğru olduğundan emin olun</li>\n";
    echo "</ul>\n";
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Hata!</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
}

echo "</pre>";
?>

