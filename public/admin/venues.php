<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once 'admin_auth.php';

// Admin kontrolü
requireAdminAuth();

$db = Database::getInstance()->getConnection();

$pageTitle = 'Mekan Yönetimi';
$username = $_SESSION['admin_username'];
$message = '';
$error = '';

// Aksiyon işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası (CSRF).';
    } else {
        $action = $_POST['action'] ?? '';
        $venueId = (int)($_POST['venue_id'] ?? 0);
        
        if ($venueId > 0) {
            if ($action === 'approve') {
                $stmt = $db->prepare("UPDATE venues SET is_active = 1 WHERE id = ?");
                $stmt->execute([$venueId]);
                $message = 'Mekan onaylandı ve yayına alındı.';
            } elseif ($action === 'reject') {
                $stmt = $db->prepare("UPDATE venues SET is_active = 0 WHERE id = ?");
                $stmt->execute([$venueId]);
                $message = 'Mekan reddedildi.';
            } elseif ($action === 'delete') {
                // Mekan ve ilişkili verileri sil
                $db->prepare("DELETE FROM checkins WHERE venue_id = ?")->execute([$venueId]);
                $db->prepare("DELETE FROM venues WHERE id = ?")->execute([$venueId]);
                $message = 'Mekan silindi.';
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

if ($filter === 'pending') {
    $whereClause .= " AND is_active = 0";
} elseif ($filter === 'active') {
    $whereClause .= " AND is_active = 1";
}

if ($search) {
    $whereClause .= " AND (name LIKE ? OR address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Toplam sayı
$countStmt = $db->prepare("SELECT COUNT(*) FROM venues WHERE $whereClause");
$countStmt->execute($params);
$totalVenues = $countStmt->fetchColumn();

// Mekanları çek
$venuesStmt = $db->prepare("
    SELECT v.*, 
           (SELECT COUNT(*) FROM checkins WHERE venue_id = v.id) as checkin_count
    FROM venues v 
    WHERE $whereClause
    ORDER BY v.is_active ASC, v.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$venuesStmt->execute($params);
$venues = $venuesStmt->fetchAll();
$totalPages = ceil($totalVenues / $perPage);

// Onay bekleyen mekan sayısı
$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE is_active = 0")->fetchColumn();
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
            padding: 14px 16px;
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
        
        .status-badge.active {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }
        
        .status-badge.pending {
            background: rgba(234, 179, 8, 0.2);
            color: #fde047;
        }
        
        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
        }
        
        .action-btn.approve {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }
        
        .action-btn.reject {
            background: rgba(234, 179, 8, 0.2);
            color: #fde047;
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
        
        .venue-name {
            max-width: 200px;
        }
        
        .venue-address {
            font-size: 0.8rem;
            color: var(--text-subtle);
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
                <a href="venues" class="admin-nav-item active">
                    <span class="admin-nav-icon">📍</span>
                    <span>Mekanlar</span>
                    <?php if ($pendingCount > 0): ?>
                    <span class="admin-nav-badge"><?php echo $pendingCount; ?></span>
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
                <h1>📍 Mekan Yönetimi (<?php echo $totalVenues; ?>)</h1>
                
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">Tümü</a>
                    <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">Bekleyen (<?php echo $pendingCount; ?>)</a>
                    <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">Aktif</a>
                </div>
                
                <form class="search-box" method="GET">
                    <input type="hidden" name="filter" value="<?php echo escape($filter); ?>">
                    <input type="text" name="search" placeholder="Mekan ara..." value="<?php echo escape($search); ?>">
                    <button type="submit">Ara</button>
                </form>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo escape($message); ?></div>
            <?php endif; ?>

            <div class="admin-section">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mekan Adı</th>
                            <th>Adres</th>
                            <th>Check-in</th>
                            <th>Tarih</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($venues as $v): ?>
                        <tr>
                            <td>#<?php echo $v['id']; ?></td>
                            <td class="venue-name"><strong><?php echo escape($v['name']); ?></strong></td>
                            <td class="venue-address" title="<?php echo escape($v['address']); ?>"><?php echo escape($v['address'] ?: '-'); ?></td>
                            <td><?php echo $v['checkin_count']; ?></td>
                            <td><?php echo formatDate($v['created_at'], true); ?></td>
                            <td>
                                <?php if ($v['is_active']): ?>
                                <span class="status-badge active">Aktif</span>
                                <?php else: ?>
                                <span class="status-badge pending">Bekliyor</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Emin misiniz?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="venue_id" value="<?php echo $v['id']; ?>">
                                    <div class="action-btns">
                                        <?php if (!$v['is_active']): ?>
                                        <button type="submit" name="action" value="approve" class="action-btn approve">✓ Onayla</button>
                                        <?php else: ?>
                                        <button type="submit" name="action" value="reject" class="action-btn reject">⨉ Kaldır</button>
                                        <?php endif; ?>
                                        <button type="submit" name="action" value="delete" class="action-btn danger">Sil</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

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

