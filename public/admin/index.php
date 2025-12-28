<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Admin kontrolü
requireLogin();

$db = Database::getInstance()->getConnection();

// Kullanıcının admin olup olmadığını kontrol et
$adminCheck = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$adminCheck->execute([$_SESSION['user_id']]);
$user = $adminCheck->fetch();

// is_admin kolonu yoksa oluştur
if ($user === false || !isset($user['is_admin'])) {
    try {
        $db->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
        // İlk kullanıcıyı admin yap
        $db->exec("UPDATE users SET is_admin = 1 WHERE id = 1");
        $user = ['is_admin' => ($_SESSION['user_id'] == 1) ? 1 : 0];
    } catch (PDOException $e) {
        // Zaten varsa devam et
    }
}

if (!$user || !$user['is_admin']) {
    // Admin yetkisi yoksa ve ?fix_admin=1 parametresi gönderilmişse yetki ver
    if (isset($_GET['fix_admin']) && $_GET['fix_admin'] == '1') {
        $db->prepare("UPDATE users SET is_admin = 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
        $_SESSION['is_admin'] = 1;
        header('Location: index');
        exit;
    }
    header('Location: ../dashboard');
    exit;
}

$pageTitle = 'Admin Panel';
$username = $_SESSION['username'];

// İstatistikler
$stats = [];

// Toplam kullanıcı
$userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['users'] = $userCount;

// Toplam mekan
$venueCount = $db->query("SELECT COUNT(*) FROM venues")->fetchColumn();
$stats['venues'] = $venueCount;

// Onay bekleyen mekanlar
$pendingVenues = $db->query("SELECT COUNT(*) FROM venues WHERE is_active = 0")->fetchColumn();
$stats['pending_venues'] = $pendingVenues;

// Toplam check-in
$checkinCount = $db->query("SELECT COUNT(*) FROM checkins")->fetchColumn();
$stats['checkins'] = $checkinCount;

// Bugünkü check-in'ler
$todayCheckins = $db->query("SELECT COUNT(*) FROM checkins WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$stats['today_checkins'] = $todayCheckins;

// Bu haftaki check-in'ler
$weeklyCheckins = $db->query("SELECT COUNT(*) FROM checkins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$stats['weekly_checkins'] = $weeklyCheckins;

// Son kayıt olan kullanıcılar (e-posta hariç - gizlilik)
$recentUsers = $db->query("SELECT id, username, created_at, is_admin FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Son eklenen mekanlar (onay bekleyenler önce)
$recentVenues = $db->query("SELECT id, name, address, is_active, created_at FROM venues ORDER BY is_active ASC, created_at DESC LIMIT 5")->fetchAll();

// Son check-in'ler
$recentCheckins = $db->query("
    SELECT c.id, c.note, c.created_at, u.username, v.name as venue_name
    FROM checkins c
    JOIN users u ON c.user_id = u.id
    JOIN venues v ON c.venue_id = v.id
    ORDER BY c.created_at DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
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
            overflow-y: auto;
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
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .admin-nav-icon {
            font-size: 1.25rem;
        }
        
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
            margin-bottom: 30px;
        }
        
        .admin-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .admin-header p {
            color: var(--text-muted);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--orange-primary);
        }
        
        .stat-card-icon {
            font-size: 2rem;
            margin-bottom: 12px;
        }
        
        .stat-card-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--orange-accent);
            line-height: 1;
            margin-bottom: 4px;
        }
        
        .stat-card-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        /* Tables */
        .admin-section {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .admin-section-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .admin-section-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .admin-section-header a {
            color: var(--orange-accent);
            font-size: 0.9rem;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 14px 24px;
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
        
        .status-badge.admin {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                width: 200px;
            }
            .admin-content {
                margin-left: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                display: none;
            }
            .admin-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
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
        <!-- Admin Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-header">
                <h2>⚙️ Admin Panel</h2>
            </div>
            
            <nav class="admin-nav">
                <a href="index" class="admin-nav-item active">
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
                    <?php if ($stats['pending_venues'] > 0): ?>
                    <span class="admin-nav-badge"><?php echo $stats['pending_venues']; ?></span>
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

        <!-- Admin Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>📊 Dashboard</h1>
                <p>Uptag yönetim paneline hoş geldiniz</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-icon">👥</div>
                    <div class="stat-card-value"><?php echo number_format($stats['users']); ?></div>
                    <div class="stat-card-label">Toplam Kullanıcı</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">📍</div>
                    <div class="stat-card-value"><?php echo number_format($stats['venues']); ?></div>
                    <div class="stat-card-label">Toplam Mekan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">⏳</div>
                    <div class="stat-card-value"><?php echo number_format($stats['pending_venues']); ?></div>
                    <div class="stat-card-label">Onay Bekleyen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">📝</div>
                    <div class="stat-card-value"><?php echo number_format($stats['checkins']); ?></div>
                    <div class="stat-card-label">Toplam Check-in</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">📅</div>
                    <div class="stat-card-value"><?php echo number_format($stats['today_checkins']); ?></div>
                    <div class="stat-card-label">Bugün</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-icon">📈</div>
                    <div class="stat-card-value"><?php echo number_format($stats['weekly_checkins']); ?></div>
                    <div class="stat-card-label">Bu Hafta</div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="admin-section">
                <div class="admin-section-header">
                    <h3>👥 Son Kayıt Olan Kullanıcılar</h3>
                    <a href="users">Tümünü Gör →</a>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı Adı</th>
                            <th>Kayıt Tarihi</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td>#<?php echo $u['id']; ?></td>
                            <td><?php echo escape($u['username']); ?></td>
                            <td><?php echo formatDate($u['created_at'], true); ?></td>
                            <td>
                                <?php if ($u['is_admin']): ?>
                                <span class="status-badge admin">Admin</span>
                                <?php else: ?>
                                <span class="status-badge active">Üye</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Venues -->
            <div class="admin-section">
                <div class="admin-section-header">
                    <h3>📍 Son Eklenen Mekanlar</h3>
                    <a href="venues">Tümünü Gör →</a>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mekan Adı</th>
                            <th>Adres</th>
                            <th>Tarih</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentVenues as $v): ?>
                        <tr>
                            <td>#<?php echo $v['id']; ?></td>
                            <td><?php echo escape($v['name']); ?></td>
                            <td><?php echo escape($v['address'] ?: '-'); ?></td>
                            <td><?php echo formatDate($v['created_at'], true); ?></td>
                            <td>
                                <?php if ($v['is_active']): ?>
                                <span class="status-badge active">Aktif</span>
                                <?php else: ?>
                                <span class="status-badge pending">Bekliyor</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Check-ins -->
            <div class="admin-section">
                <div class="admin-section-header">
                    <h3>📝 Son Check-in'ler</h3>
                    <a href="posts">Tümünü Gör →</a>
                </div>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kullanıcı</th>
                            <th>Mekan</th>
                            <th>Not</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCheckins as $c): ?>
                        <tr>
                            <td>#<?php echo $c['id']; ?></td>
                            <td><?php echo escape($c['username']); ?></td>
                            <td><?php echo escape($c['venue_name']); ?></td>
                            <td><?php echo escape(substr($c['note'] ?: '-', 0, 50)); ?></td>
                            <td><?php echo formatDate($c['created_at'], true); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

</body>
</html>
