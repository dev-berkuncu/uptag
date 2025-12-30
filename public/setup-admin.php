<?php
/**
 * Admin Hesap Kurulum Scripti
 * Bu scripti bir kez çalıştırın, sonra silin!
 */

require_once '../config/config.php';
require_once '../config/database.php';

$db = Database::getInstance()->getConnection();

// Rastgele şifreler
$adminPassword = 'Adm1n' . rand(100, 999);
$gtawPassword = 'Gtaw' . rand(100, 999);

$results = [];

// Admin hesabı
try {
    $checkAdmin = $db->prepare("SELECT id FROM users WHERE username = ?");
    $checkAdmin->execute(['admin']);
    
    if (!$checkAdmin->fetch()) {
        $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, is_admin, created_at) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute(['admin', 'admin@uptag.local', $hash]);
        $results[] = "✅ 'admin' hesabı oluşturuldu - Şifre: <strong>$adminPassword</strong>";
    } else {
        $results[] = "⚠️ 'admin' hesabı zaten mevcut";
    }
} catch (Exception $e) {
    $results[] = "❌ Admin hesabı oluşturulamadı: " . $e->getMessage();
}

// GTAW hesabı
try {
    $checkGTAW = $db->prepare("SELECT id FROM users WHERE username = ?");
    $checkGTAW->execute(['GTAW']);
    
    if (!$checkGTAW->fetch()) {
        $hash = password_hash($gtawPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, is_admin, created_at) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute(['GTAW', 'gtaw@uptag.local', $hash]);
        $results[] = "✅ 'GTAW' hesabı oluşturuldu - Şifre: <strong>$gtawPassword</strong>";
    } else {
        $results[] = "⚠️ 'GTAW' hesabı zaten mevcut";
    }
} catch (Exception $e) {
    $results[] = "❌ GTAW hesabı oluşturulamadı: " . $e->getMessage();
}

// is_admin kolonu kontrolü
try {
    $db->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
    $results[] = "✅ is_admin kolonu eklendi";
} catch (Exception $e) {
    // Zaten var
}

// Mevcut hesapları admin yap
try {
    $db->exec("UPDATE users SET is_admin = 1 WHERE username IN ('admin', 'GTAW')");
} catch (Exception $e) {
    // Hata
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Hesap Kurulumu - Uptag</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #0a0a0a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: rgba(20, 20, 20, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 40px;
        }
        h1 {
            font-size: 1.75rem;
            margin-bottom: 24px;
            color: #ff6b35;
        }
        .result {
            padding: 16px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }
        .warning {
            margin-top: 24px;
            padding: 16px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            color: #fca5a5;
        }
        .warning p {
            margin-bottom: 8px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #c03901;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 20px;
        }
        strong { color: #22c55e; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Admin Hesap Kurulumu</h1>
        
        <?php foreach ($results as $result): ?>
        <div class="result"><?php echo $result; ?></div>
        <?php endforeach; ?>
        
        <div class="warning">
            <p><strong>⚠️ ÖNEMLİ:</strong></p>
            <p>1. Yukarıdaki şifreleri kaydedin!</p>
            <p>2. Bu dosyayı (setup-admin.php) silin!</p>
            <p>3. Şifreler sadece bir kez gösterilir.</p>
        </div>
        
        <a href="login" class="btn">Giriş Yap →</a>
    </div>
</body>
</html>
