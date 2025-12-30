<?php
/**
 * Hızlı Test Scripti
 * 
 * Bu script, uygulamanın temel fonksiyonlarını test eder.
 * Kullanım: http://localhost/Sociaera/test/quick-test.php
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sociaera - Hızlı Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .test-container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .test-item { padding: 10px; margin: 10px 0; border-left: 4px solid #ddd; }
        .test-item.pass { border-color: #27ae60; background: #d4edda; }
        .test-item.fail { border-color: #e74c3c; background: #f8d7da; }
        .test-item.warning { border-color: #f39c12; background: #fff3cd; }
        h1 { color: #2c3e50; }
        h2 { color: #34495e; margin-top: 30px; }
        .summary { padding: 15px; margin: 20px 0; border-radius: 4px; font-weight: bold; }
        .summary.pass { background: #d4edda; color: #155724; }
        .summary.fail { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🔍 Sociaera - Hızlı Test</h1>
        
        <?php
        $tests = [];
        $passed = 0;
        $failed = 0;
        
        // Test 1: Veritabanı Bağlantısı
        try {
            $db = Database::getInstance()->getConnection();
            $tests[] = ['name' => 'Veritabanı Bağlantısı', 'status' => 'pass', 'message' => 'Bağlantı başarılı'];
            $passed++;
        } catch (Exception $e) {
            $tests[] = ['name' => 'Veritabanı Bağlantısı', 'status' => 'fail', 'message' => 'Hata: ' . $e->getMessage()];
            $failed++;
        }
        
        // Test 2: Tabloların Varlığı
        if (isset($db)) {
            $requiredTables = ['users', 'venues', 'checkins', 'admin_logs', 'settings'];
            $existingTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $missingTables = array_diff($requiredTables, $existingTables);
            
            if (empty($missingTables)) {
                $tests[] = ['name' => 'Veritabanı Tabloları', 'status' => 'pass', 'message' => 'Tüm tablolar mevcut'];
                $passed++;
            } else {
                $tests[] = ['name' => 'Veritabanı Tabloları', 'status' => 'fail', 'message' => 'Eksik tablolar: ' . implode(', ', $missingTables)];
                $failed++;
            }
        }
        
        // Test 3: Admin Kullanıcısı
        if (isset($db)) {
            $admin = $db->query("SELECT * FROM users WHERE username = 'admin' AND is_admin = 1")->fetch();
            if ($admin) {
                $tests[] = ['name' => 'Admin Kullanıcısı', 'status' => 'pass', 'message' => 'Admin kullanıcısı mevcut'];
                $passed++;
            } else {
                $tests[] = ['name' => 'Admin Kullanıcısı', 'status' => 'fail', 'message' => 'Admin kullanıcısı bulunamadı'];
                $failed++;
            }
        }
        
        // Test 4: Sistem Ayarları
        if (isset($db)) {
            $settings = $db->query("SELECT COUNT(*) FROM settings")->fetchColumn();
            if ($settings > 0) {
                $tests[] = ['name' => 'Sistem Ayarları', 'status' => 'pass', 'message' => "$settings ayar mevcut"];
                $passed++;
            } else {
                $tests[] = ['name' => 'Sistem Ayarları', 'status' => 'fail', 'message' => 'Sistem ayarları bulunamadı'];
                $failed++;
            }
        }
        
        // Test 5: PHP Sınıfları
        $requiredClasses = ['User', 'Venue', 'Checkin', 'Leaderboard', 'Database'];
        $missingClasses = [];
        foreach ($requiredClasses as $class) {
            if (!class_exists($class)) {
                $missingClasses[] = $class;
            }
        }
        
        if (empty($missingClasses)) {
            $tests[] = ['name' => 'PHP Sınıfları', 'status' => 'pass', 'message' => 'Tüm sınıflar yüklendi'];
            $passed++;
        } else {
            $tests[] = ['name' => 'PHP Sınıfları', 'status' => 'fail', 'message' => 'Eksik sınıflar: ' . implode(', ', $missingClasses)];
            $failed++;
        }
        
        // Test 6: Yardımcı Fonksiyonlar
        $requiredFunctions = ['isLoggedIn', 'isAdmin', 'escape', 'getWeekRange', 'formatDate', 'getSetting'];
        $missingFunctions = [];
        foreach ($requiredFunctions as $func) {
            if (!function_exists($func)) {
                $missingFunctions[] = $func;
            }
        }
        
        if (empty($missingFunctions)) {
            $tests[] = ['name' => 'Yardımcı Fonksiyonlar', 'status' => 'pass', 'message' => 'Tüm fonksiyonlar mevcut'];
            $passed++;
        } else {
            $tests[] = ['name' => 'Yardımcı Fonksiyonlar', 'status' => 'fail', 'message' => 'Eksik fonksiyonlar: ' . implode(', ', $missingFunctions)];
            $failed++;
        }
        
        // Test 7: Zaman Dilimi
        $timezone = date_default_timezone_get();
        if ($timezone === 'Europe/Istanbul') {
            $tests[] = ['name' => 'Zaman Dilimi', 'status' => 'pass', 'message' => "Zaman dilimi: $timezone"];
            $passed++;
        } else {
            $tests[] = ['name' => 'Zaman Dilimi', 'status' => 'warning', 'message' => "Zaman dilimi: $timezone (Europe/Istanbul bekleniyor)"];
        }
        
        // Test 8: Session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $tests[] = ['name' => 'Session', 'status' => 'pass', 'message' => 'Session aktif'];
            $passed++;
        } else {
            $tests[] = ['name' => 'Session', 'status' => 'warning', 'message' => 'Session henüz başlatılmamış (normal, test scripti için)'];
        }
        
        // Test 9: Haftalık Hesaplama
        try {
            $weekRange = getWeekRange();
            if (isset($weekRange['start']) && isset($weekRange['end'])) {
                $tests[] = ['name' => 'Haftalık Hesaplama', 'status' => 'pass', 'message' => 'Haftalık aralık hesaplanabiliyor'];
                $passed++;
            } else {
                $tests[] = ['name' => 'Haftalık Hesaplama', 'status' => 'fail', 'message' => 'Haftalık aralık hesaplanamadı'];
                $failed++;
            }
        } catch (Exception $e) {
            $tests[] = ['name' => 'Haftalık Hesaplama', 'status' => 'fail', 'message' => 'Hata: ' . $e->getMessage()];
            $failed++;
        }
        
        // Test 10: BASE_URL
        if (defined('BASE_URL') && !empty(BASE_URL)) {
            $tests[] = ['name' => 'BASE_URL Ayarı', 'status' => 'pass', 'message' => 'BASE_URL tanımlı: ' . BASE_URL];
            $passed++;
        } else {
            $tests[] = ['name' => 'BASE_URL Ayarı', 'status' => 'fail', 'message' => 'BASE_URL tanımlı değil'];
            $failed++;
        }
        
        // Sonuçları Göster
        foreach ($tests as $test) {
            $statusClass = $test['status'];
            echo "<div class='test-item $statusClass'>";
            echo "<strong>{$test['name']}:</strong> {$test['message']}";
            echo "</div>";
        }
        
        // Özet
        $total = $passed + $failed;
        $summaryClass = $failed === 0 ? 'pass' : 'fail';
        echo "<div class='summary $summaryClass'>";
        echo "Toplam: $total test | ✅ Geçti: $passed | ❌ Başarısız: $failed";
        echo "</div>";
        
        if ($failed === 0) {
            echo "<h2>✅ Tüm testler başarılı! Uygulama kullanıma hazır.</h2>";
            echo "<p><a href='../index.php'>Ana Sayfaya Git</a> | <a href='../admin/index.php'>Admin Paneline Git</a></p>";
        } else {
            echo "<h2>⚠️ Bazı testler başarısız. Lütfen hataları düzeltin.</h2>";
            echo "<p>Detaylı kurulum talimatları için <a href='../INSTALL.md'>INSTALL.md</a> dosyasına bakın.</p>";
        }
        ?>
    </div>
</body>
</html>


