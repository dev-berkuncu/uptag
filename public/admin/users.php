<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once 'admin_auth.php';

// Admin kontrolü
requireAdminAuth();

$db = Database::getInstance()->getConnection();

$pageTitle = 'Kullanıcı Yönetimi';
$username = $_SESSION['admin_username'];
$message = '';
$error = '';

// Aksiyon işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası (CSRF).';
    } else {
        $action = $_POST['action'] ?? '';
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        
        if ($targetUserId > 0 && $targetUserId != $_SESSION['admin_id']) {
        if ($action === 'make_admin') {
            $stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $message = 'Kullanıcı admin yapıldı.';
        } elseif ($action === 'remove_admin') {
            $stmt = $db->prepare("UPDATE users SET is_admin = 0 WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $message = 'Admin yetkisi kaldırıldı.';
        } elseif ($action === 'delete') {
            // Kullanıcıyı ve ilgili verileri sil
            $db->prepare("DELETE FROM post_likes WHERE user_id = ?")->execute([$targetUserId]);
            $db->prepare("DELETE FROM post_comments WHERE user_id = ?")->execute([$targetUserId]);
            $db->prepare("DELETE FROM post_reposts WHERE user_id = ?")->execute([$targetUserId]);
            $db->prepare("DELETE FROM user_follows WHERE follower_id = ? OR following_id = ?")->execute([$targetUserId, $targetUserId]);
            $db->prepare("DELETE FROM checkins WHERE user_id = ?")->execute([$targetUserId]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetUserId]);
            $message = 'Kullanıcı silindi.';
        }
    }
}
}
// Arama
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Kullanıcıları çek
if ($search) {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username LIKE ? AND username != 'GTAW'");
    $countStmt->execute(["%$search%"]);
    $totalUsers = $countStmt->fetchColumn();
    
    $usersStmt = $db->prepare("
        SELECT u.id, u.username, u.created_at, u.is_admin, 
               (SELECT COUNT(*) FROM checkins WHERE user_id = u.id) as checkin_count,
               (SELECT COUNT(*) FROM user_follows WHERE following_id = u.id) as follower_count
        FROM users u 
        WHERE username LIKE ? AND username != 'GTAW'
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $usersStmt->execute(["%$search%", $perPage, $offset]);
} else {
    $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE username != 'GTAW'")->fetchColumn();
    
    $usersStmt = $db->prepare("
        SELECT u.id, u.username, u.created_at, u.is_admin, 
               (SELECT COUNT(*) FROM checkins WHERE user_id = u.id) as checkin_count,
               (SELECT COUNT(*) FROM user_follows WHERE following_id = u.id) as follower_count
        FROM users u 
        WHERE username != 'GTAW'
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $usersStmt->execute([$perPage, $offset]);
}

$users = $usersStmt->fetchAll();
$totalPages = ceil($totalUsers / $perPage);

// Onay bekleyen mekan sayısı (sidebar için)
$pendingVenues = $db->query("SELECT COUNT(*) FROM venues WHERE is_active = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($pageTitle); ?> - Sociaera Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            transition: all 0.2s ease;
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
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .admin-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            padding: 10px 16px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: var(--text-white);
            font-size: 0.9rem;
            width: 250px;
        }
        
        .search-box button {
            padding: 10px 20px;
            background: var(--orange-primary);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }
        
        .admin-section {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 14px 20px;
            text-align: left;
        }
        
        .admin-table th {
            background: rgba(0, 0, 0, 0.3);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-subtle);
        }
        
        .admin-table td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.9rem;
        }
        
        .admin-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.admin {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
        }
        
        .status-badge.active {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .action-btn.primary {
            background: var(--orange-primary);
            color: white;
        }
        
        .action-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-white);
        }
        
        .action-btn.danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }
        
        .action-btn:hover {
            transform: scale(1.05);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }
        
        .pagination a {
            padding: 8px 14px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .pagination a:hover,
        .pagination a.active {
            background: var(--orange-primary);
            color: white;
            border-color: var(--orange-primary);
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
                <a href="users" class="admin-nav-item active">
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
                <a href="settings" class="admin-nav-item">
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
                <h1>👥 Kullanıcı Yönetimi (<?php echo $totalUsers; ?>)</h1>
                <form class="search-box" method="GET">
                    <input type="text" name="search" placeholder="Kullanıcı ara..." value="<?php echo escape($search); ?>">
                    <button type="submit">Ara</button>
                </form>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo escape($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <div class="admin-section">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Check-in</th>
                            <th>Takipçi</th>
                            <th>Kayıt</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td><strong><?php echo escape($u['username']); ?></strong></td>
                            <td><?php echo $u['checkin_count']; ?></td>
                            <td><?php echo $u['follower_count']; ?></td>
                            <td><?php echo formatDate($u['created_at'], true); ?></td>
                            <td>
                                <?php if ($u['is_admin']): ?>
                                <span class="status-badge admin">Admin</span>
                                <?php else: ?>
                                <span class="status-badge active">Üye</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['id'] != $_SESSION['admin_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Emin misiniz?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <div class="action-btns">
                                        <?php if ($u['is_admin']): ?>
                                        <button type="submit" name="action" value="remove_admin" class="action-btn secondary">Adminliği Kaldır</button>
                                        <?php else: ?>
                                        <button type="submit" name="action" value="make_admin" class="action-btn primary">Admin Yap</button>
                                        <?php endif; ?>
                                        <button type="submit" name="action" value="delete" class="action-btn danger">Sil</button>
                                    </div>
                                </form>
                                <?php else: ?>
                                <span style="color: var(--text-subtle);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="<?php echo $i === $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>

