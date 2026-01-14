<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once 'admin_auth.php';

// Admin kontrolü
requireAdminAuth();

$db = Database::getInstance()->getConnection();

$pageTitle = 'Admin Ayarları';
$username = $_SESSION['admin_username'];
$message = '';
$error = '';

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası (CSRF).';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Kendi şifreni değiştir
        if ($action === 'change_own_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Tüm alanları doldurun.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Yeni şifreler eşleşmiyor.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Şifre en az 6 karakter olmalı.';
        } else {
            $passCheck = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $passCheck->execute([$_SESSION['admin_id']]);
            $passRow = $passCheck->fetch();
            
            if (!password_verify($currentPassword, $passRow['password_hash'])) {
                $error = 'Mevcut şifre yanlış.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $_SESSION['admin_id']]);
                $message = 'Şifreniz başarıyla değiştirildi.';
            }
        }
    }
    
    // Başka kullanıcının şifresini sıfırla
    if ($action === 'reset_user_password') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';
        
        if ($targetUserId <= 0) {
            $error = 'Geçersiz kullanıcı.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Şifre en az 6 karakter olmalı.';
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $updateStmt->execute([$newHash, $targetUserId]);
            
            // Kullanıcı adını al
            $userStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $userStmt->execute([$targetUserId]);
            $targetUser = $userStmt->fetch();
            
            $message = "'" . escape($targetUser['username']) . "' kullanıcısının şifresi değiştirildi.";
        }
    }
    }
}

// Admin kullanıcıları listesi (şifre sıfırlama için)
$adminUsers = $db->query("SELECT id, username, is_admin FROM users WHERE is_admin = 1 ORDER BY username")->fetchAll();

// Onay bekleyen mekan sayısı (sidebar için)
$pendingVenues = $db->query("SELECT COUNT(*) FROM venues WHERE is_active = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($pageTitle); ?> - Uptag Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php require_once '../includes/head-bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
    <style>
        .admin-layout {
            display: flex;
            min-height: 100vh;
            padding-top: 70px;
        }
        
        .admin-sidebar {
            width: 260px;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 24px 0;
            position: fixed;
            left: 0;
            top: 70px;
            bottom: 0;
        }
        
        .admin-sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        
        .admin-sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--orange-accent);
        }
        
        .admin-nav {
            display: flex;
            flex-direction: column;
        }
        
        .admin-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            border-left: 3px solid transparent;
        }
        
        .admin-nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-white);
        }
        
        .admin-nav-item.active {
            background: rgba(192, 57, 1, 0.15);
            color: var(--orange-accent);
            border-left-color: var(--orange-accent);
        }
        
        .admin-nav-icon { font-size: 1.25rem; }
        
        .admin-nav-badge {
            margin-left: auto;
            background: var(--orange-primary);
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .admin-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            max-width: 800px;
        }
        
        .admin-header {
            margin-bottom: 30px;
        }
        
        .admin-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .settings-card {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .settings-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: var(--text-white);
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--orange-primary);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: var(--orange-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--orange-dark);
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-inner">
            <div class="nav-links left">
                <a href="../dashboard" class="nav-link">Ana Sayfa</a>
                <a href="index" class="nav-link active">Admin Panel</a>
            </div>
            <div class="nav-links right">
                <a href="../profile" class="nav-link"><?php echo escape($username); ?></a>
                <a href="../logout" class="nav-link">Çıkış</a>
            </div>
        </div>
    </nav>

    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-header">
                <h2>⚙️ Admin Panel</h2>
            </div>
            <nav class="admin-nav">
                <a href="index" class="admin-nav-item">
                    <span class="admin-nav-icon">📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="users" class="admin-nav-item">
                    <span class="admin-nav-icon">👥</span>
                    <span>Kullanıcılar</span>
                </a>
                <a href="venues" class="admin-nav-item">
                    <span class="admin-nav-icon">📍</span>
                    <span>Mekanlar</span>
                    <?php if ($pendingVenues > 0): ?>
                    <span class="admin-nav-badge"><?php echo $pendingVenues; ?></span>
                    <?php endif; ?>
                </a>
                <a href="posts" class="admin-nav-item">
                    <span class="admin-nav-icon">📝</span>
                    <span>Gönderiler</span>
                </a>
                <a href="ads" class="admin-nav-item">
                    <span class="admin-nav-icon">📢</span>
                    <span>Reklamlar</span>
                </a>
                <a href="settings" class="admin-nav-item active">
                    <span class="admin-nav-icon">⚙️</span>
                    <span>Ayarlar</span>
                </a>
                <a href="../dashboard" class="admin-nav-item">
                    <span class="admin-nav-icon">🏠</span>
                    <span>Siteye Dön</span>
                </a>
            </nav>
        </aside>

        <main class="admin-content">
            <div class="admin-header">
                <h1>⚙️ Admin Ayarları</h1>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <!-- Kendi Şifreni Değiştir -->
            <div class="settings-card">
                <h3>🔐 Şifremi Değiştir</h3>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="change_own_password">
                    
                    <div class="form-group">
                        <label>Mevcut Şifre</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Yeni Şifre</label>
                            <input type="password" name="new_password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Yeni Şifre (Tekrar)</label>
                            <input type="password" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Şifreyi Değiştir</button>
                </form>
            </div>

            <!-- Admin Şifresi Sıfırla -->
            <div class="settings-card">
                <h3>🔄 Admin Şifresi Sıfırla</h3>
                <p style="color: var(--text-muted); margin-bottom: 16px; font-size: 0.9rem;">
                    Diğer admin kullanıcılarının şifresini sıfırlayabilirsiniz.
                </p>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="reset_user_password">
                    
                    <div class="form-group">
                        <label>Kullanıcı Seç</label>
                        <select name="user_id" required>
                            <option value="">Seçin...</option>
                            <?php foreach ($adminUsers as $au): ?>
                            <option value="<?php echo $au['id']; ?>">
                                <?php echo escape($au['username']); ?>
                                <?php echo $au['id'] == $_SESSION['admin_id'] ? '(Sen)' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Yeni Şifre</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Şifreyi Sıfırla</button>
                </form>
            </div>

            <!-- Admin Listesi -->
            <div class="settings-card">
                <h3>👑 Admin Kullanıcılar</h3>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($adminUsers as $au): ?>
                    <li style="padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between;">
                        <span><?php echo escape($au['username']); ?></span>
                        <span style="color: var(--text-subtle);">ID: <?php echo $au['id']; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </main>
    </div>

</body>
</html>

