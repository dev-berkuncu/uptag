<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once 'admin_auth.php';

// Admin kontrolü
requireAdminAuth();

$db = Database::getInstance()->getConnection();

$pageTitle = 'Gönderi Yönetimi';
$username = $_SESSION['admin_username'];
$message = '';

// Aksiyon işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Güvenlik hatası (CSRF).';
    } else {
        $action = $_POST['action'] ?? '';
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if ($postId > 0) {
        if ($action === 'flag') {
            $stmt = $db->prepare("UPDATE checkins SET is_flagged = 1 WHERE id = ?");
            $stmt->execute([$postId]);
            $message = 'Gönderi işaretlendi ve gizlendi.';
        } elseif ($action === 'unflag') {
            $stmt = $db->prepare("UPDATE checkins SET is_flagged = 0 WHERE id = ?");
            $stmt->execute([$postId]);
            $message = 'Gönderi işareti kaldırıldı.';
        } elseif ($action === 'delete') {
            // Gönderi ve ilişkili verileri sil
            $db->prepare("DELETE FROM post_likes WHERE checkin_id = ?")->execute([$postId]);
            $db->prepare("DELETE FROM post_comments WHERE checkin_id = ?")->execute([$postId]);
            $db->prepare("DELETE FROM post_reposts WHERE checkin_id = ?")->execute([$postId]);
            $db->prepare("DELETE FROM checkins WHERE id = ?")->execute([$postId]);
            $message = 'Gönderi silindi.';
        }
    }
}
}
// Filtre
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Sorgu oluştur
$whereClause = "1=1";
$params = [];

if ($filter === 'flagged') {
    $whereClause .= " AND c.is_flagged = 1";
}

if ($search) {
    $whereClause .= " AND (c.note LIKE ? OR u.username LIKE ? OR v.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Toplam sayı
$countStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM checkins c 
    JOIN users u ON c.user_id = u.id 
    JOIN venues v ON c.venue_id = v.id 
    WHERE $whereClause
");
$countStmt->execute($params);
$totalPosts = $countStmt->fetchColumn();

// Check-in'leri çek
$postsStmt = $db->prepare("
    SELECT c.*, 
           u.username,
           v.name as venue_name,
           (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
           (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
           (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count
    FROM checkins c 
    JOIN users u ON c.user_id = u.id 
    JOIN venues v ON c.venue_id = v.id 
    WHERE $whereClause
    ORDER BY c.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$postsStmt->execute($params);
$posts = $postsStmt->fetchAll();
$totalPages = ceil($totalPosts / $perPage);

// İşaretli gönderi sayısı
$flaggedCount = $db->query("SELECT COUNT(*) FROM checkins WHERE is_flagged = 1")->fetchColumn();

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
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .admin-header h1 { font-size: 1.5rem; font-weight: 700; }
        
        .filter-tabs {
            display: flex;
            gap: 8px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .filter-tab:hover,
        .filter-tab.active {
            background: var(--orange-primary);
            color: white;
            border-color: var(--orange-primary);
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
            width: 200px;
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
        
        /* Post Cards */
        .posts-grid {
            display: grid;
            gap: 16px;
        }
        
        .post-card {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px;
        }
        
        .post-card.flagged {
            border-color: rgba(239, 68, 68, 0.5);
            background: rgba(239, 68, 68, 0.05);
        }
        
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .post-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--orange-primary), var(--orange-deeper));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
        }
        
        .post-user-info strong {
            display: block;
            font-size: 0.95rem;
        }
        
        .post-user-info span {
            font-size: 0.8rem;
            color: var(--text-subtle);
        }
        
        .post-id {
            font-size: 0.75rem;
            color: var(--text-subtle);
        }
        
        .post-content {
            margin-bottom: 12px;
            line-height: 1.5;
        }
        
        .post-venue {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(192, 57, 1, 0.15);
            color: var(--orange-accent);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 12px;
        }
        
        .post-stats {
            display: flex;
            gap: 16px;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        
        .post-stat {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .post-actions {
            display: flex;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .action-btn {
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
        }
        
        .action-btn.flag {
            background: rgba(234, 179, 8, 0.2);
            color: #fde047;
        }
        
        .action-btn.unflag {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }
        
        .action-btn.danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }
        
        .action-btn:hover {
            transform: scale(1.05);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .status-badge.flagged {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
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
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .empty-state h3 {
            margin-bottom: 8px;
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
                <a href="posts" class="admin-nav-item active">
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
                <h1>📝 Gönderi Yönetimi (<?php echo $totalPosts; ?>)</h1>
                
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">Tümü</a>
                    <a href="?filter=flagged" class="filter-tab <?php echo $filter === 'flagged' ? 'active' : ''; ?>">İşaretli (<?php echo $flaggedCount; ?>)</a>
                </div>
                
                <form class="search-box" method="GET">
                    <input type="hidden" name="filter" value="<?php echo escape($filter); ?>">
                    <input type="text" name="search" placeholder="Gönderi ara..." value="<?php echo escape($search); ?>">
                    <button type="submit">Ara</button>
                </form>
            </div>

            <?php if ($message): ?>
            <div class="alert"><?php echo escape($message); ?></div>
            <?php endif; ?>

            <?php if (empty($posts)): ?>
            <div class="empty-state">
                <h3>Gönderi bulunamadı</h3>
                <p>Filtreleri değiştirerek tekrar deneyin.</p>
            </div>
            <?php else: ?>
            <div class="posts-grid">
                <?php foreach ($posts as $p): ?>
                <div class="post-card <?php echo $p['is_flagged'] ? 'flagged' : ''; ?>">
                    <div class="post-header">
                        <div class="post-user">
                            <div class="post-avatar"><?php echo strtoupper(substr($p['username'], 0, 1)); ?></div>
                            <div class="post-user-info">
                                <strong>
                                    <?php echo escape($p['username']); ?>
                                    <?php if ($p['is_flagged']): ?>
                                    <span class="status-badge flagged">🚩 İşaretli</span>
                                    <?php endif; ?>
                                </strong>
                                <span><?php echo formatDate($p['created_at'], true); ?></span>
                            </div>
                        </div>
                        <div class="post-id">#<?php echo $p['id']; ?></div>
                    </div>
                    
                    <div class="post-venue">📍 <?php echo escape($p['venue_name']); ?></div>
                    
                    <?php if ($p['note']): ?>
                    <div class="post-content"><?php echo nl2br(escape($p['note'])); ?></div>
                    <?php endif; ?>
                    
                    <div class="post-stats">
                        <span class="post-stat">❤️ <?php echo $p['like_count']; ?></span>
                        <span class="post-stat">💬 <?php echo $p['comment_count']; ?></span>
                        <span class="post-stat">🔄 <?php echo $p['repost_count']; ?></span>
                    </div>
                    
                    <div class="post-actions">
                        <form method="POST" style="display: contents;" onsubmit="return confirm('Emin misiniz?');">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="post_id" value="<?php echo $p['id']; ?>">
                            <?php if ($p['is_flagged']): ?>
                            <button type="submit" name="action" value="unflag" class="action-btn unflag">✓ İşareti Kaldır</button>
                            <?php else: ?>
                            <button type="submit" name="action" value="flag" class="action-btn flag">🚩 İşaretle</button>
                            <?php endif; ?>
                            <button type="submit" name="action" value="delete" class="action-btn danger">🗑️ Sil</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                <a href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>" 
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

