<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$pageTitle = 'Sistem Ayarları';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'leaderboard_top_users' => (int)($_POST['leaderboard_top_users'] ?? 20),
        'leaderboard_top_venues' => (int)($_POST['leaderboard_top_venues'] ?? 20),
        'checkin_cooldown_seconds' => (int)($_POST['checkin_cooldown_seconds'] ?? 300),
        'checkin_rate_limit_count' => (int)($_POST['checkin_rate_limit_count'] ?? 10),
        'checkin_rate_limit_window_seconds' => (int)($_POST['checkin_rate_limit_window_seconds'] ?? 3600),
        'site_name' => trim($_POST['site_name'] ?? 'Sociaera'),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'timezone' => trim($_POST['timezone'] ?? 'Europe/Istanbul'),
    ];
    
    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }
    
    logAdminAction('settings_update', 'system', null, 'Sistem ayarları güncellendi');
    $success = 'Ayarlar başarıyla güncellendi.';
}

// Mevcut ayarları getir
$currentSettings = [
    'leaderboard_top_users' => getSetting('leaderboard_top_users', 20),
    'leaderboard_top_venues' => getSetting('leaderboard_top_venues', 20),
    'checkin_cooldown_seconds' => getSetting('checkin_cooldown_seconds', 300),
    'checkin_rate_limit_count' => getSetting('checkin_rate_limit_count', 10),
    'checkin_rate_limit_window_seconds' => getSetting('checkin_rate_limit_window_seconds', 3600),
    'site_name' => getSetting('site_name', 'Sociaera'),
    'contact_email' => getSetting('contact_email', 'admin@Sociaera.com'),
    'timezone' => getSetting('timezone', 'Europe/Istanbul'),
];

include '../includes/header.php';
?>

<h1>Sistem Ayarları</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo escape($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo escape($success); ?></div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="">
        <h2 style="margin-bottom: 1.5rem;">Leaderboard Ayarları</h2>
        
        <div class="form-group">
            <label for="leaderboard_top_users">Top Kullanıcı Sayısı</label>
            <input type="number" id="leaderboard_top_users" name="leaderboard_top_users" 
                   value="<?php echo escape($currentSettings['leaderboard_top_users']); ?>" min="1" max="100" required>
        </div>
        
        <div class="form-group">
            <label for="leaderboard_top_venues">Top Mekan Sayısı</label>
            <input type="number" id="leaderboard_top_venues" name="leaderboard_top_venues" 
                   value="<?php echo escape($currentSettings['leaderboard_top_venues']); ?>" min="1" max="100" required>
        </div>
        
        <h2 style="margin-top: 2rem; margin-bottom: 1.5rem;">Check-in Güvenlik Ayarları</h2>
        
        <div class="form-group">
            <label for="checkin_cooldown_seconds">Cooldown Süresi (saniye)</label>
            <input type="number" id="checkin_cooldown_seconds" name="checkin_cooldown_seconds" 
                   value="<?php echo escape($currentSettings['checkin_cooldown_seconds']); ?>" min="0" required>
            <small style="color: #7f8c8d;">Aynı mekana tekrar check-in için bekleme süresi</small>
        </div>
        
        <div class="form-group">
            <label for="checkin_rate_limit_count">Rate Limit - Maksimum Check-in Sayısı</label>
            <input type="number" id="checkin_rate_limit_count" name="checkin_rate_limit_count" 
                   value="<?php echo escape($currentSettings['checkin_rate_limit_count']); ?>" min="1" required>
        </div>
        
        <div class="form-group">
            <label for="checkin_rate_limit_window_seconds">Rate Limit - Zaman Penceresi (saniye)</label>
            <input type="number" id="checkin_rate_limit_window_seconds" name="checkin_rate_limit_window_seconds" 
                   value="<?php echo escape($currentSettings['checkin_rate_limit_window_seconds']); ?>" min="1" required>
            <small style="color: #7f8c8d;">Rate limit için zaman penceresi</small>
        </div>
        
        <h2 style="margin-top: 2rem; margin-bottom: 1.5rem;">Genel Ayarlar</h2>
        
        <div class="form-group">
            <label for="site_name">Site Adı</label>
            <input type="text" id="site_name" name="site_name" 
                   value="<?php echo escape($currentSettings['site_name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="contact_email">İletişim E-postası</label>
            <input type="email" id="contact_email" name="contact_email" 
                   value="<?php echo escape($currentSettings['contact_email']); ?>">
        </div>
        
        <div class="form-group">
            <label for="timezone">Zaman Dilimi</label>
            <input type="text" id="timezone" name="timezone" 
                   value="<?php echo escape($currentSettings['timezone']); ?>" required>
            <small style="color: #7f8c8d;">Örnek: Europe/Istanbul</small>
        </div>
        
        <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
    </form>
</div>

<?php include '../includes/footer.php'; ?>


