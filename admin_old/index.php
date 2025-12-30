<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$pageTitle = 'Admin Paneli';

// İstatistikler
$db = Database::getInstance()->getConnection();

$stats = [
    'total_users' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'total_venues' => $db->query("SELECT COUNT(*) FROM venues")->fetchColumn(),
    'active_venues' => $db->query("SELECT COUNT(*) FROM venues WHERE is_active = 1")->fetchColumn(),
    'total_checkins' => $db->query("SELECT COUNT(*) FROM checkins")->fetchColumn(),
    'today_checkins' => $db->query("SELECT COUNT(*) FROM checkins WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
];

$weekRange = getWeekRange();
$weekCheckins = $db->prepare("SELECT COUNT(*) FROM checkins WHERE created_at >= ? AND created_at <= ?");
$weekCheckins->execute([$weekRange['start'], $weekRange['end']]);
$stats['week_checkins'] = $weekCheckins->fetchColumn();

include '../includes/header.php';
?>

<h1>Admin Paneli</h1>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
    <div class="card">
        <div class="card-header">Kullanıcılar</div>
        <div style="font-size: 2rem; font-weight: bold; color: #3498db;"><?php echo $stats['total_users']; ?></div>
        <p style="color: #7f8c8d;">Aktif: <?php echo $stats['active_users']; ?></p>
    </div>
    
    <div class="card">
        <div class="card-header">Mekanlar</div>
        <div style="font-size: 2rem; font-weight: bold; color: #27ae60;"><?php echo $stats['total_venues']; ?></div>
        <p style="color: #7f8c8d;">Aktif: <?php echo $stats['active_venues']; ?></p>
    </div>
    
    <div class="card">
        <div class="card-header">Check-in'ler</div>
        <div style="font-size: 2rem; font-weight: bold; color: #e74c3c;"><?php echo $stats['total_checkins']; ?></div>
        <p style="color: #7f8c8d;">Bugün: <?php echo $stats['today_checkins']; ?> | Bu Hafta: <?php echo $stats['week_checkins']; ?></p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 2rem 0;">
    <a href="users.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
        <h3>👥 Kullanıcı Yönetimi</h3>
    </a>
    
    <a href="venues.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
        <h3>📍 Mekan Yönetimi</h3>
    </a>
    
    <a href="checkins.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
        <h3>✅ Check-in Yönetimi</h3>
    </a>
    
    <a href="leaderboard.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
        <h3>🏆 Leaderboard Kontrolü</h3>
    </a>
    
    <a href="settings.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
        <h3>⚙️ Sistem Ayarları</h3>
    </a>
    
    <a href="logs.php" class="card" style="text-decoration: none; text-align: center; padding: 2rem;">
        <h3>📋 İşlem Logları</h3>
    </a>
</div>

<?php include '../includes/footer.php'; ?>

