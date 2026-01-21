<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once 'admin_auth.php';

// Admin kontrolü
requireAdminAuth();

$db = Database::getInstance()->getConnection();

// ads tablosu oluştur (yoksa) veya position sütununu güncelle
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS ads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            link_url VARCHAR(500) DEFAULT NULL,
            position VARCHAR(50) DEFAULT 'carousel',
            is_active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
}

try {
    // Mevcut ENUM sütununu VARCHAR'a çevir (eski kurulumlar için)
    $db->exec("ALTER TABLE ads MODIFY COLUMN position VARCHAR(50) DEFAULT 'carousel'");
} catch (Exception $e) {
}

try {
    // Boş position değerlerini 'carousel' olarak düzelt
    $db->exec("UPDATE ads SET position = 'carousel' WHERE position = '' OR position IS NULL");
} catch (Exception $e) {
}

$pageTitle = 'Reklam Yönetimi';
$username = $_SESSION['admin_username'];
$message = '';
$error = '';

// Yükleme klasörü - mutlak yol kullan
$uploadDir = dirname(__DIR__) . '/uploads/ads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Aksiyon işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';

        // Yeni reklam ekle
        if ($action === 'add') {
            $title = trim($_POST['title'] ?? '');
            $linkUrl = trim($_POST['link_url'] ?? '');
            $position = $_POST['position'] ?? 'carousel';
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);

            if (empty($title)) {
                $error = 'Başlık gerekli.';
            } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Görsel yüklenemedi.';
            } else {
                // Güvenli resim yükleme sınıfı
                require_once dirname(__DIR__) . '/../includes/ImageUploader.php';

                $uploader = new ImageUploader();
                $result = $uploader->upload($_FILES['image'], 'ads', [
                    'maxSize' => 5 * 1024 * 1024,
                    'outputFormat' => 'webp',
                    'quality' => 90,
                    'maxWidth' => 1200,
                    'maxHeight' => 800
                ]);

                if (!$result['success']) {
                    $error = $result['error'];
                } else {
                    $imageUrl = $result['path'];
                    $stmt = $db->prepare("INSERT INTO ads (title, image_url, link_url, position, sort_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $imageUrl, $linkUrl, $position, $sortOrder]);
                    // PRG Pattern - Redirect to prevent duplicate on refresh
                    $_SESSION['flash_message'] = 'Reklam başarıyla eklendi.';
                    header('Location: ads');
                    exit;
                }
            }
        }

        if ($action === 'delete') {
            $adId = (int) ($_POST['ad_id'] ?? 0);
            if ($adId > 0) {
                // Dosyayı sil
                $adStmt = $db->prepare("SELECT image_url FROM ads WHERE id = ?");
                $adStmt->execute([$adId]);
                $ad = $adStmt->fetch();
                $deleteFilePath = dirname(__DIR__) . '/' . $ad['image_url'];
                if ($ad && file_exists($deleteFilePath)) {
                    unlink($deleteFilePath);
                }

                $db->prepare("DELETE FROM ads WHERE id = ?")->execute([$adId]);
                // PRG Pattern - Redirect to prevent duplicate on refresh
                $_SESSION['flash_message'] = 'Reklam silindi.';
                header('Location: ads');
                exit;
            }
        }

        // Aktif/Pasif yap
        if ($action === 'toggle') {
            $adId = (int) ($_POST['ad_id'] ?? 0);
            if ($adId > 0) {
                $db->prepare("UPDATE ads SET is_active = NOT is_active WHERE id = ?")->execute([$adId]);
                // PRG Pattern - Redirect to prevent duplicate on refresh
                $_SESSION['flash_message'] = 'Durum güncellendi.';
                header('Location: ads');
                exit;
            }
        }
    }
}

// Flash mesaj kontrolü
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Reklamları çek
$carouselAds = $db->query("SELECT * FROM ads WHERE position = 'carousel' ORDER BY sort_order, id DESC")->fetchAll();
$sidebarLeftAds = $db->query("SELECT * FROM ads WHERE position = 'sidebar_left' ORDER BY sort_order, id DESC")->fetchAll();
$sidebarRightAds = $db->query("SELECT * FROM ads WHERE (position = 'sidebar_right' OR position = 'sidebar') ORDER BY sort_order, id DESC")->fetchAll();
$footerAds = $db->query("SELECT * FROM ads WHERE position = 'footer' ORDER BY sort_order, id DESC")->fetchAll();

// Onay bekleyen mekan sayısı (sidebar için)
$pendingVenues = $db->query("SELECT COUNT(*) FROM venues WHERE is_active = 0")->fetchColumn();

// CSRF Token oluştur
$csrfToken = generateCsrfToken();
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
            margin-bottom: 24px;
        }

        .admin-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .add-form {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
        }

        .add-form h3 {
            margin-bottom: 20px;
            font-size: 1.1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
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

        .form-group input[type="file"] {
            padding: 10px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--orange-primary);
            color: white;
        }

        .ads-section {
            margin-bottom: 30px;
        }

        .ads-section h3 {
            font-size: 1.1rem;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .ads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .ad-card {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
        }

        .ad-card.inactive {
            opacity: 0.5;
        }

        .ad-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .ad-info {
            padding: 16px;
        }

        .ad-title {
            font-weight: 600;
            margin-bottom: 8px;
        }

        .ad-link {
            font-size: 0.8rem;
            color: var(--text-subtle);
            word-break: break-all;
            margin-bottom: 12px;
        }

        .ad-actions {
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
        }

        .action-btn.toggle {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }

        .action-btn.danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .status-badge.active {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
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

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
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
                <a href="ads" class="admin-nav-item active">
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
                <h1>📢 Reklam Yönetimi</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo escape($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <!-- Reklam Ekle Formu -->
            <div class="add-form">
                <h3>➕ Yeni Reklam Ekle</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="add">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Başlık *</label>
                            <input type="text" name="title" required placeholder="Reklam başlığı">
                        </div>
                        <div class="form-group">
                            <label>Link URL</label>
                            <input type="url" name="link_url" placeholder="https://...">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Konum *</label>
                            <select name="position" required>
                                <option value="carousel">Carousel (Sağ üst, dönen)</option>
                                <option value="sidebar_left">Sol Sidebar</option>
                                <option value="sidebar_right">Sağ Sidebar</option>
                                <option value="footer">Footer Banner</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sıralama</label>
                            <input type="number" name="sort_order" value="0" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Görsel * (Max 5MB, JPG/PNG/GIF/WEBP)</label>
                        <input type="file" name="image" required accept="image/*">
                    </div>

                    <button type="submit" class="btn btn-primary">Reklam Ekle</button>
                </form>
            </div>

            <!-- Carousel Reklamları -->
            <div class="ads-section">
                <h3>🎠 Carousel Reklamları (<?php echo count($carouselAds); ?>)</h3>

                <?php if (empty($carouselAds)): ?>
                    <div class="empty-state">Henüz carousel reklamı yok.</div>
                <?php else: ?>
                    <div class="ads-grid">
                        <?php foreach ($carouselAds as $ad): ?>
                            <div class="ad-card <?php echo !$ad['is_active'] ? 'inactive' : ''; ?>">
                                <img src="<?php echo BASE_URL . '/' . escape($ad['image_url']); ?>" alt="" class="ad-image">
                                <div class="ad-info">
                                    <div class="ad-title">
                                        <?php echo escape($ad['title']); ?>
                                        <span class="status-badge <?php echo $ad['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $ad['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </div>
                                    <?php if ($ad['link_url']): ?>
                                        <div class="ad-link"><?php echo escape($ad['link_url']); ?></div>
                                    <?php endif; ?>
                                    <div class="ad-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="action-btn toggle">
                                                <?php echo $ad['is_active'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="action-btn danger">Sil</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sol Sidebar Reklamları -->
            <div class="ads-section">
                <h3>⬅️ Sol Sidebar Reklamları (<?php echo count($sidebarLeftAds); ?>)</h3>

                <?php if (empty($sidebarLeftAds)): ?>
                    <div class="empty-state">Henüz sol sidebar reklamı yok.</div>
                <?php else: ?>
                    <div class="ads-grid">
                        <?php foreach ($sidebarLeftAds as $ad): ?>
                            <div class="ad-card <?php echo !$ad['is_active'] ? 'inactive' : ''; ?>">
                                <img src="<?php echo BASE_URL . '/' . escape($ad['image_url']); ?>" alt="" class="ad-image">
                                <div class="ad-info">
                                    <div class="ad-title">
                                        <?php echo escape($ad['title']); ?>
                                        <span class="status-badge <?php echo $ad['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $ad['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </div>
                                    <?php if ($ad['link_url']): ?>
                                        <div class="ad-link"><?php echo escape($ad['link_url']); ?></div>
                                    <?php endif; ?>
                                    <div class="ad-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="action-btn toggle">
                                                <?php echo $ad['is_active'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="action-btn danger">Sil</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sağ Sidebar Reklamları -->
            <div class="ads-section">
                <h3>➡️ Sağ Sidebar Reklamları (<?php echo count($sidebarRightAds); ?>)</h3>

                <?php if (empty($sidebarRightAds)): ?>
                    <div class="empty-state">Henüz sağ sidebar reklamı yok.</div>
                <?php else: ?>
                    <div class="ads-grid">
                        <?php foreach ($sidebarRightAds as $ad): ?>
                            <div class="ad-card <?php echo !$ad['is_active'] ? 'inactive' : ''; ?>">
                                <img src="<?php echo BASE_URL . '/' . escape($ad['image_url']); ?>" alt="" class="ad-image">
                                <div class="ad-info">
                                    <div class="ad-title">
                                        <?php echo escape($ad['title']); ?>
                                        <span class="status-badge <?php echo $ad['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $ad['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </div>
                                    <?php if ($ad['link_url']): ?>
                                        <div class="ad-link"><?php echo escape($ad['link_url']); ?></div>
                                    <?php endif; ?>
                                    <div class="ad-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="action-btn toggle">
                                                <?php echo $ad['is_active'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="action-btn danger">Sil</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer Reklamları -->
            <div class="ads-section">
                <h3>📎 Footer Banner Reklamları (<?php echo count($footerAds); ?>)</h3>

                <?php if (empty($footerAds)): ?>
                    <div class="empty-state">Henüz footer reklamı yok.</div>
                <?php else: ?>
                    <div class="ads-grid">
                        <?php foreach ($footerAds as $ad): ?>
                            <div class="ad-card <?php echo !$ad['is_active'] ? 'inactive' : ''; ?>">
                                <img src="<?php echo BASE_URL . '/' . escape($ad['image_url']); ?>" alt="" class="ad-image">
                                <div class="ad-info">
                                    <div class="ad-title">
                                        <?php echo escape($ad['title']); ?>
                                        <span class="status-badge <?php echo $ad['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $ad['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                        </span>
                                    </div>
                                    <?php if ($ad['link_url']): ?>
                                        <div class="ad-link"><?php echo escape($ad['link_url']); ?></div>
                                    <?php endif; ?>
                                    <div class="ad-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="action-btn toggle">
                                                <?php echo $ad['is_active'] ? 'Pasif Yap' : 'Aktif Yap'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="ad_id" value="<?php echo $ad['id']; ?>">
                                            <button type="submit" class="action-btn danger">Sil</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

</body>

</html>