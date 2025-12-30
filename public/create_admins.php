<?php
require_once '../config/config.php';
require_once '../config/database.php';

$db = Database::getInstance()->getConnection();

$accounts = [
    [
        'username' => 'Gabriel Cruz',
        'email' => 'gabriel@Sociaera.local',
        'password' => 'Gabriel!' . rand(1000, 9999)
    ],
    [
        'username' => 'GTAW',
        'email' => 'gtaw@Sociaera.local',
        'password' => 'GTAW!' . rand(1000, 9999)
    ]
];

$results = [];

foreach ($accounts as $acc) {
    try {
        $check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$acc['username']]);
        
        if (!$check->fetch()) {
            $hash = password_hash($acc['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, is_admin, created_at) VALUES (?, ?, ?, 1, NOW())");
            $stmt->execute([$acc['username'], $acc['email'], $hash]);
            $results[] = "✅ '{$acc['username']}' oluşturuldu - Şifre: <strong>{$acc['password']}</strong>";
        } else {
            // Zaten varsa admin yap ve şifreyi güncelle (opsiyonel ama kullanıcı istediği için temiz olsun)
            $hash = password_hash($acc['password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, is_admin = 1 WHERE username = ?");
            $stmt->execute([$hash, $acc['username']]);
            $results[] = "🔄 '{$acc['username']}' güncellendi (Admin yapıldı ve şifre sıfırlandı) - Şifre: <strong>{$acc['password']}</strong>";
        }
    } catch (Exception $e) {
        $results[] = "❌ '{$acc['username']}' hatası: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Hesap Oluşturucu</title>
    <style>
        body { font-family: sans-serif; background: #000; color: #fff; padding: 50px; text-align: center; }
        .box { background: #222; padding: 20px; border-radius: 10px; display: inline-block; text-align: left; }
        .result { margin-bottom: 10px; }
        strong { color: #2ecc71; }
        .warning { color: #e74c3c; margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Admin Hesapları</h1>
        <?php foreach ($results as $r): ?>
            <div class="result"><?php echo $r; ?></div>
        <?php endforeach; ?>
        <p class="warning">⚠️ LÜTFEN BU ŞİFREYİ KAYDEDİN VE BU DOSYAYI SİSTEMDEN SİLİN!</p>
        <a href="admin/login.php" style="color: #3498db;">Admin Girişine Git</a>
    </div>
</body>
</html>

