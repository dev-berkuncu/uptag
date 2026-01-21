<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Dashboard sadece giriş yapan kullanıcıya açık
requireLogin();

$pageTitle = 'Dashboard';
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Leaderboard verileri
$leaderboard = new Leaderboard();
$weekInfo = $leaderboard->getWeekInfo();
$topUsers = $leaderboard->getTopUsers(5, $weekInfo['start'], $weekInfo['end']);
$topVenues = $leaderboard->getTopVenues(5, $weekInfo['start'], $weekInfo['end']);

// Kullanıcının haftalık check-in sayısı
$db = Database::getInstance()->getConnection();
$weeklyStmt = $db->prepare("
    SELECT COUNT(*) as count FROM checkins 
    WHERE user_id = ? AND created_at >= ? AND created_at <= ?
");
$weeklyStmt->execute([$userId, $weekInfo['start'], $weekInfo['end']]);
$weeklyCheckins = $weeklyStmt->fetch()['count'] ?? 0;

// Toplam check-in sayısı
$totalStmt = $db->prepare("SELECT COUNT(*) as count FROM checkins WHERE user_id = ?");
$totalStmt->execute([$userId]);
$totalCheckins = $totalStmt->fetch()['count'] ?? 0;

// Kullanıcının haftalık sıralaması
$userRank = 0;
foreach ($topUsers as $index => $user) {
    if ($user['id'] == $userId) {
        $userRank = $index + 1;
        break;
    }
}

// Kullanıcı avatar ve admin durumu
$avatarUrl = null;
$isAdmin = false;
try {
    $userStmt = $db->prepare("SELECT avatar, tag, is_admin FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userInfo = $userStmt->fetch();
    // Avatar varsa tam URL oluştur
    if (!empty($userInfo['avatar'])) {
        $avatarUrl = 'uploads/avatars/' . $userInfo['avatar'];
    }
    $isAdmin = isset($userInfo['is_admin']) && $userInfo['is_admin'] == 1;
} catch (PDOException $e) {
    // avatar veya is_admin kolonu yoksa devam et
    $avatarUrl = null;
    $isAdmin = false;
}

// Feed filtresi - "following" (takip edilenler) veya "all" (tüm gönderiler)
$feedFilter = isset($_GET['feed']) ? $_GET['feed'] : 'following';
if (!in_array($feedFilter, ['following', 'all'])) {
    $feedFilter = 'following';
}

// Son global check-in'ler ve repostlar (feed için) - etkileşim sayılarıyla birlikte
$feedPosts = [];
try {
    // Tablolar ve kolonlar artık her zaman mevcut kabul ediliyor (performans için)
    // SHOW TABLES/COLUMNS sorguları kaldırıldı
    $hasFollowsTable = true;
    $hasQuoteColumn = true;

    // Minimum beğeni sayısı (popüler post eşiği)
    $minLikes = 3;

    // "all" filtresi seçildiyse tüm postları göster (takip filtresi olmadan)
    if ($feedFilter === 'all' && $hasQuoteColumn) {
        // Tüm gönderiler sorgusu
        $feedStmt = $db->prepare("
            (
                SELECT 
                    c.id, c.user_id, c.venue_id, c.note, c.created_at, c.is_flagged, c.image,
                    u.username, u.tag as user_tag, u.avatar as user_avatar, v.name as venue_name,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
                    (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted,
                    0 as is_repost,
                    NULL as repost_user_id,
                    NULL as repost_username,
                    NULL as repost_quote,
                    c.created_at as sort_date
                FROM checkins c 
                JOIN users u ON c.user_id = u.id 
                JOIN venues v ON c.venue_id = v.id 
                WHERE c.is_flagged = 0
            )
            UNION ALL
            (
                SELECT 
                    c.id, c.user_id, c.venue_id, c.note, c.created_at, c.is_flagged, c.image,
                    u.username, u.tag as user_tag, u.avatar as user_avatar, v.name as venue_name,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
                    (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted,
                    1 as is_repost,
                    r.user_id as repost_user_id,
                    ru.username as repost_username,
                    r.quote as repost_quote,
                    r.created_at as sort_date
                FROM post_reposts r
                JOIN checkins c ON r.checkin_id = c.id
                JOIN users u ON c.user_id = u.id
                JOIN users ru ON r.user_id = ru.id
                JOIN venues v ON c.venue_id = v.id 
                WHERE c.is_flagged = 0
            )
            ORDER BY sort_date DESC 
            LIMIT 50
        ");
        $feedStmt->execute([$userId, $userId, $userId, $userId]);
    } elseif ($hasQuoteColumn && $hasFollowsTable) {
        // Takip edilen + popüler postlar sorgusu
        $feedStmt = $db->prepare("
            (
                SELECT 
                    c.id, c.user_id, c.venue_id, c.note, c.created_at, c.is_flagged, c.image,
                    u.username, u.tag as user_tag, u.avatar as user_avatar, v.name as venue_name,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
                    (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted,
                    NULL as is_repost,
                    NULL as repost_user_id,
                    NULL as repost_username,
                    NULL as repost_quote,
                    c.created_at as sort_date
                FROM checkins c 
                JOIN users u ON c.user_id = u.id 
                JOIN venues v ON c.venue_id = v.id 
                WHERE c.is_flagged = 0
                AND (
                    c.user_id = ?
                    OR c.user_id IN (SELECT following_id FROM user_follows WHERE follower_id = ?)
                    OR (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) >= ?
                )
            )
            UNION ALL
            (
                SELECT 
                    c.id, c.user_id, c.venue_id, c.note, c.created_at, c.is_flagged, c.image,
                    u.username, u.tag as user_tag, u.avatar as user_avatar, v.name as venue_name,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
                    (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted,
                    1 as is_repost,
                    r.user_id as repost_user_id,
                    ru.username as repost_username,
                    r.quote as repost_quote,
                    r.created_at as sort_date
                FROM post_reposts r
                JOIN checkins c ON r.checkin_id = c.id
                JOIN users u ON c.user_id = u.id
                JOIN users ru ON r.user_id = ru.id
                JOIN venues v ON c.venue_id = v.id 
                WHERE c.is_flagged = 0
                AND (
                    r.user_id = ?
                    OR r.user_id IN (SELECT following_id FROM user_follows WHERE follower_id = ?)
                )
            )
            ORDER BY sort_date DESC 
            LIMIT 30
        ");
        $feedStmt->execute([$userId, $userId, $userId, $userId, $minLikes, $userId, $userId, $userId, $userId]);
    } elseif ($hasQuoteColumn) {
        // Quote destekli ama follows tablosu yok - tüm postları göster
        $feedStmt = $db->prepare("
            (
                SELECT 
                    c.id, c.user_id, c.venue_id, c.note, c.created_at, c.is_flagged, c.image,
                    u.username, u.tag as user_tag, u.avatar as user_avatar, v.name as venue_name,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
                    (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted,
                    NULL as is_repost,
                    NULL as repost_user_id,
                    NULL as repost_username,
                    NULL as repost_quote,
                    c.created_at as sort_date
                FROM checkins c 
                JOIN users u ON c.user_id = u.id 
                JOIN venues v ON c.venue_id = v.id 
                WHERE c.is_flagged = 0
            )
            UNION ALL
            (
                SELECT 
                    c.id, c.user_id, c.venue_id, c.note, c.created_at, c.is_flagged, c.image,
                    u.username, u.tag as user_tag, u.avatar as user_avatar, v.name as venue_name,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
                    (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
                    (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
                    (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted,
                    1 as is_repost,
                    r.user_id as repost_user_id,
                    ru.username as repost_username,
                    r.quote as repost_quote,
                    r.created_at as sort_date
                FROM post_reposts r
                JOIN checkins c ON r.checkin_id = c.id
                JOIN users u ON c.user_id = u.id
                JOIN users ru ON r.user_id = ru.id
                JOIN venues v ON c.venue_id = v.id 
                WHERE c.is_flagged = 0
            )
            ORDER BY sort_date DESC 
            LIMIT 30
        ");
        $feedStmt->execute([$userId, $userId, $userId, $userId]);
    } else {
        // Basit sorgu (quote kolonu yoksa)
        $feedStmt = $db->prepare("
            SELECT c.*, u.username, u.tag as user_tag, u.avatar as user_avatar, v.name as venue_name,
                   (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
                   (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
                   (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
                   (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
                   (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted,
                   NULL as is_repost,
                   NULL as repost_user_id,
                   NULL as repost_username,
                   NULL as repost_quote
            FROM checkins c 
            JOIN users u ON c.user_id = u.id 
            JOIN venues v ON c.venue_id = v.id 
            WHERE c.is_flagged = 0 
            ORDER BY c.created_at DESC 
            LIMIT 30
        ");
        $feedStmt->execute([$userId, $userId]);
    }
    $feedPosts = $feedStmt->fetchAll();

    // @mention kullanıcılarını toplu olarak çek (performans için)
    $mentionedTags = [];
    foreach ($feedPosts as $post) {
        if (!empty($post['note'])) {
            preg_match_all('/@([a-zA-Z0-9_]+)/', $post['note'], $matches);
            if (!empty($matches[1])) {
                $mentionedTags = array_merge($mentionedTags, $matches[1]);
            }
        }
    }
    $mentionedTags = array_unique($mentionedTags);

    // Mention cache'i oluştur
    $GLOBALS['mentionCache'] = [];
    if (!empty($mentionedTags)) {
        $placeholders = str_repeat('?,', count($mentionedTags) - 1) . '?';
        $mentionStmt = $db->prepare("SELECT id, tag, LOWER(username) as username_lower FROM users WHERE tag IN ($placeholders) OR LOWER(username) IN ($placeholders)");
        $params = array_merge($mentionedTags, array_map('strtolower', $mentionedTags));
        $mentionStmt->execute($params);
        $mentionUsers = $mentionStmt->fetchAll();
        foreach ($mentionUsers as $mu) {
            if ($mu['tag']) {
                $GLOBALS['mentionCache'][strtolower($mu['tag'])] = $mu['id'];
            }
            $GLOBALS['mentionCache'][$mu['username_lower']] = $mu['id'];
        }
    }
} catch (PDOException $e) {
    // Hata durumunda debug için
    error_log("Feed query error: " . $e->getMessage());
    $feedPosts = [];
    $GLOBALS['mentionCache'] = [];
}

// Top 10'a kalan check-in hesapla
$top10Remaining = 0;
if ($userRank == 0 || $userRank > 10) {
    $top10Users = $leaderboard->getTopUsers(10, $weekInfo['start'], $weekInfo['end']);
    if (count($top10Users) >= 10) {
        $top10MinCheckins = $top10Users[9]['checkin_count'] ?? 0;
        $top10Remaining = max(0, $top10MinCheckins - $weeklyCheckins + 1);
    } else {
        $top10Remaining = max(0, 1 - $weeklyCheckins);
    }
}

// Haftalık hedef (örnek: 10 check-in)
$weeklyGoal = 10;
$goalProgress = min(100, ($weeklyCheckins / $weeklyGoal) * 100);

// Reklamları veritabanından çek (Ortak Mantık)
require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sociaera Dashboard - Check-in yap, sıralamanı gör">
    <title><?php echo escape($pageTitle); ?> - Sociaera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php require_once '../includes/head-bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
    <style>
        /* Sidebar Dropdown Styles */
        .sidebar-nav-dropdown {
            position: relative;
        }

        .sidebar-nav-item.has-dropdown {
            cursor: pointer;
            user-select: none;
        }

        .dropdown-arrow {
            margin-left: auto;
            font-size: 0.7rem;
            transition: transform 0.2s ease;
        }

        .sidebar-nav-item.has-dropdown.open .dropdown-arrow {
            transform: rotate(180deg);
        }

        .sidebar-dropdown-menu {
            display: none;
            flex-direction: column;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            margin-top: 4px;
            overflow: hidden;
        }

        .sidebar-dropdown-menu.show {
            display: flex;
        }

        .sidebar-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px 10px 32px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .sidebar-dropdown-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-white);
        }

        .sidebar-dropdown-item.active {
            color: var(--orange-accent);
            background: rgba(192, 57, 1, 0.15);
        }

        .dropdown-icon {
            font-size: 0.9rem;
        }

        /* Paylaş butonu boşluğu */
        .btn-compose-submit {
            margin-left: 200px;
        }

        /* Fotoğraf önizleme */
        .compose-image-preview {
            position: relative;
            margin: 10px 0;
            max-width: 300px;
        }

        .compose-image-preview img {
            width: 100%;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .image-preview-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .image-preview-remove:hover {
            background: rgba(255, 0, 0, 0.8);
        }

        /* Post fotoğrafı */
        .post-image {
            margin-top: 12px;
            border-radius: 12px;
            overflow: hidden;
            max-width: 100%;
        }

        .post-image img {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 12px;
        }

        /* Yorum fotoğrafı */
        .comment-image {
            margin-top: 8px;
            max-width: 200px;
        }

        .comment-image img {
            width: 100%;
            border-radius: 8px;
        }

        /* Comment input row */
        .comment-input-row {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            margin-bottom: 10px;
        }

        .comment-input-row .comment-input {
            flex: 1;
        }

        .comment-input-row .compose-tool-btn {
            flex-shrink: 0;
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'dashboard';
    require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT (Bootstrap Grid - Fixed Sidebar) -->
    <div class="container-fluid app-layout-wrapper">
        <div class="row flex-nowrap h-100">

            <!-- Sol Sponsor: col-auto, sabit 300px -->
            <div class="col-auto app-sponsor-col">
                <?php require_once '../includes/sidebar-left.php'; ?>
            </div>

            <!-- Orta İçerik: col, esnek -->
            <div class="col app-center-col">
                <div class="row flex-nowrap app-inner-row">

                    <!-- Sol Sidebar: col-auto, sabit 280px -->
                    <div class="col-auto app-sidebar-left-col">
                        <aside class="twitter-sidebar-left app-sidebar-left">

                            <!-- Profile Mini Card -->
                            <div class="profile-mini-card">
                                <div class="profile-mini-header">
                                    <div class="profile-mini-avatar">
                                        <?php if ($avatarUrl): ?>
                                            <img src="<?php echo BASE_URL . '/' . escape($avatarUrl); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="profile-mini-info">
                                        <div class="profile-mini-name"><?php echo escape($username); ?></div>
                                        <div class="profile-mini-username">
                                            @<?php echo escape(!empty($userInfo['tag']) ? $userInfo['tag'] : strtolower($username)); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="profile-mini-stats">
                                    <div class="profile-stat">
                                        <span class="profile-stat-value"><?php echo $weeklyCheckins; ?></span>
                                        <span class="profile-stat-label">Bu Hafta</span>
                                    </div>
                                    <div class="profile-stat">
                                        <span class="profile-stat-value"><?php echo $totalCheckins; ?></span>
                                        <span class="profile-stat-label">Toplam</span>
                                    </div>
                                    <div class="profile-stat">
                                        <span
                                            class="profile-stat-value"><?php echo $userRank > 0 ? '#' . $userRank : '-'; ?></span>
                                        <span class="profile-stat-label">Sıra</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Navigation -->
                            <nav class="sidebar-nav">
                                <div class="sidebar-nav-dropdown">
                                    <div class="sidebar-nav-item active has-dropdown" id="feedDropdownToggle">
                                        <span class="sidebar-nav-icon">🏠</span>
                                        <span>Ana Sayfa</span>
                                        <span class="dropdown-arrow">▼</span>
                                    </div>
                                    <div class="sidebar-dropdown-menu" id="feedDropdownMenu">
                                        <a href="dashboard?feed=following"
                                            class="sidebar-dropdown-item <?php echo $feedFilter === 'following' ? 'active' : ''; ?>">
                                            <span class="dropdown-icon">👥</span>
                                            <span>Takip Edilenler</span>
                                        </a>
                                        <a href="dashboard?feed=all"
                                            class="sidebar-dropdown-item <?php echo $feedFilter === 'all' ? 'active' : ''; ?>">
                                            <span class="dropdown-icon">🌍</span>
                                            <span>Tüm Gönderiler</span>
                                        </a>
                                    </div>
                                </div>
                                <a href="venues" class="sidebar-nav-item">
                                    <span class="sidebar-nav-icon">📍</span>
                                    <span>Mekanlar</span>
                                </a>
                                <a href="leaderboard" class="sidebar-nav-item">
                                    <span class="sidebar-nav-icon">🏆</span>
                                    <span>Liderlik</span>
                                </a>
                                <a href="members" class="sidebar-nav-item">
                                    <span class="sidebar-nav-icon">👥</span>
                                    <span>Üyeler</span>
                                </a>
                                <a href="notifications" class="sidebar-nav-item" id="notifications-nav-item">
                                    <span class="sidebar-nav-icon">🔔</span>
                                    <span>Bildirimler</span>
                                    <span class="notification-badge" id="notification-badge"
                                        style="display: none;">0</span>
                                </a>
                                <a href="profile" class="sidebar-nav-item">
                                    <span class="sidebar-nav-icon">👤</span>
                                    <span>Profil</span>
                                </a>

                                <a href="settings" class="sidebar-nav-item">
                                    <span class="sidebar-nav-icon">⚙️</span>
                                    <span>Ayarlar</span>
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="admin" class="sidebar-nav-item"
                                        style="background: rgba(192, 57, 1, 0.15); border-left: 3px solid var(--orange-accent);">
                                        <span class="sidebar-nav-icon">🛡️</span>
                                        <span>Admin Panel</span>
                                    </a>
                                <?php endif; ?>
                            </nav>

                            <!-- Big Add Venue Button -->
                            <a href="add-venue" class="btn-checkin-big">
                                <span>🏢 Mekan Ekle</span>
                            </a>

                        </aside>
                    </div>
                    <!-- End sol sidebar col -->

                    <!-- Ana Feed: col, esnek - SCROLL BURADA -->
                    <div class="col app-feed-col">
                        <main class="twitter-main-feed app-feed">

                            <!-- Feed Header -->
                            <div class="feed-header-bar">
                                <h1><?php echo $feedFilter === 'all' ? '🌍 Tüm Gönderiler' : '👥 Takip Edilenler'; ?>
                                </h1>
                            </div>

                            <!-- Compose Box -->
                            <div class="compose-box">
                                <form id="post-form" class="compose-box-inner">
                                    <div class="compose-avatar">
                                        <?php if ($avatarUrl): ?>
                                            <img src="<?php echo BASE_URL . '/' . escape($avatarUrl); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="compose-content">
                                        <div class="compose-input-wrapper">
                                            <textarea id="post-content" class="compose-input"
                                                placeholder="Ne düşünüyorsun? @mekan veya @kullanıcı yazarak etiketle..."
                                                rows="2"></textarea>
                                            <div id="venue-autocomplete" class="venue-autocomplete"></div>
                                            <div id="user-autocomplete" class="user-autocomplete"></div>
                                        </div>
                                        <div id="selected-venue" class="selected-venue-tag" style="display: none;">
                                            <span class="venue-tag-icon">📍</span>
                                            <span id="venue-name"></span>
                                            <button type="button" id="remove-venue" class="venue-tag-remove">×</button>
                                        </div>
                                        <input type="hidden" id="venue-id" name="venue_id" value="">
                                        <div id="image-preview" class="compose-image-preview" style="display: none;">
                                            <img id="image-preview-img" src="" alt="Preview">
                                            <button type="button" id="remove-image"
                                                class="image-preview-remove">×</button>
                                        </div>
                                        <div class="compose-actions">
                                            <div class="compose-tools">
                                                <button type="button" class="compose-tool-btn" id="venue-picker-btn"
                                                    title="Mekan Etiketle">📍</button>
                                                <input type="file" id="image-input"
                                                    accept="image/jpeg,image/png,image/gif,image/webp"
                                                    style="display: none;">
                                                <button type="button" class="compose-tool-btn" id="image-picker-btn"
                                                    title="Fotoğraf Ekle">📷</button>
                                            </div>
                                            <button type="submit" class="btn-compose-submit" id="submit-btn"
                                                disabled>Paylaş</button>
                                        </div>
                                        <div id="post-error" class="post-error" style="display: none;"></div>
                                        <div id="post-success" class="post-success" style="display: none;"></div>
                                    </div>
                                </form>
                            </div>

                            <!-- Feed Posts -->
                            <?php if (empty($feedPosts)): ?>
                                <div class="feed-empty">
                                    <div class="feed-empty-icon">📍</div>
                                    <h3>Henüz paylaşım yok</h3>
                                    <p>İlk check-in'i yaparak feed'i başlat!</p>
                                    <a href="venues" class="btn btn-primary btn-sm">Check-in Yap</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($feedPosts as $post): ?>
                                    <article class="tweet-card <?php echo $post['is_repost'] ? 'is-repost-card' : ''; ?>">
                                        <?php if ($post['is_repost']): ?>
                                            <!-- Repost Header -->
                                            <div class="repost-header">
                                                <span class="repost-icon">🔄</span>
                                                <a href="profile.php?id=<?php echo $post['repost_user_id']; ?>" class="repost-by">
                                                    <?php echo escape($post['repost_username']); ?> repostladı
                                                </a>
                                            </div>
                                            <?php if (!empty($post['repost_quote'])): ?>
                                                <div class="repost-quote">
                                                    <?php echo escape($post['repost_quote']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <!-- Embedded Original Post -->
                                            <div class="embedded-post">
                                            <?php endif; ?>

                                            <div class="tweet-inner">
                                                <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="tweet-avatar">
                                                    <?php if (!empty($post['user_avatar'])): ?>
                                                        <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo escape($post['user_avatar']); ?>"
                                                            alt="<?php echo escape($post['username']); ?>">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                                                    <?php endif; ?>
                                                </a>
                                                <div class="tweet-content">
                                                    <div class="tweet-header">
                                                        <a href="profile.php?id=<?php echo $post['user_id']; ?>"
                                                            class="tweet-username">
                                                            <?php echo escape($post['username']); ?>
                                                        </a>
                                                        <span
                                                            class="tweet-handle">@<?php echo escape(!empty($post['user_tag']) ? $post['user_tag'] : strtolower($post['username'])); ?></span>
                                                        <span
                                                            class="tweet-time"><?php echo formatDate($post['created_at'], true); ?></span>
                                                        <?php if ($post['user_id'] == $userId && !$post['is_repost']): ?>
                                                            <button class="tweet-delete-btn"
                                                                data-checkin-id="<?php echo $post['id']; ?>"
                                                                title="Postu Sil">×</button>
                                                        <?php endif; ?>
                                                    </div>
                                                    <a href="venue-detail?id=<?php echo $post['venue_id']; ?>"
                                                        class="tweet-venue">
                                                        <?php echo escape($post['venue_name']); ?>
                                                    </a>
                                                    <?php if (!empty($post['note'])): ?>
                                                        <p class="tweet-note"><?php
                                                        // Parse @mentions and make them clickable (cache kullanarak)
                                                        $noteText = escape($post['note']);
                                                        $noteText = preg_replace_callback(
                                                            '/@([a-zA-Z0-9_]+)/',
                                                            function ($matches) {
                                                                $tag = $matches[1];
                                                                $tagLower = strtolower($tag);
                                                                // Cache'den kullanıcıyı bul (DB sorgusu yok!)
                                                                if (isset($GLOBALS['mentionCache'][$tagLower])) {
                                                                    return '<a href="profile?id=' . $GLOBALS['mentionCache'][$tagLower] . '" class="mention-link">@' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</a>';
                                                                }
                                                                return '@' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
                                                            },
                                                            $noteText
                                                        );
                                                        echo $noteText;
                                                        ?></p>
                                                    <?php endif; ?>
                                                    <?php if (!empty($post['image'])): ?>
                                                        <div class="post-image">
                                                            <img src="<?php echo BASE_URL; ?>/uploads/posts/<?php echo escape($post['image']); ?>"
                                                                alt="Post fotoğrafı">
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="tweet-actions">
                                                        <button class="tweet-action action-comment"
                                                            data-checkin-id="<?php echo $post['id']; ?>">
                                                            <span class="tweet-action-icon">💬</span>
                                                            <span
                                                                class="action-count"><?php echo $post['comment_count'] ?? 0; ?></span>
                                                        </button>
                                                        <button
                                                            class="tweet-action action-repost <?php echo ($post['user_reposted'] ?? 0) ? 'active' : ''; ?>"
                                                            data-checkin-id="<?php echo $post['id']; ?>">
                                                            <span class="tweet-action-icon">🔄</span>
                                                            <span
                                                                class="action-count"><?php echo $post['repost_count'] ?? 0; ?></span>
                                                        </button>
                                                        <button
                                                            class="tweet-action action-like <?php echo ($post['user_liked'] ?? 0) ? 'active' : ''; ?>"
                                                            data-checkin-id="<?php echo $post['id']; ?>">
                                                            <span
                                                                class="tweet-action-icon like-icon"><?php echo ($post['user_liked'] ?? 0) ? '❤️' : '🤍'; ?></span>
                                                            <span
                                                                class="action-count"><?php echo $post['like_count'] ?? 0; ?></span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if ($post['is_repost']): ?>
                                            </div><!-- End embedded-post -->
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </main>

                    </div>
                    <!-- End feed col -->

                    <!-- Sağ Sidebar: col-auto, sabit 340px -->
                    <div class="col-auto app-sidebar-right-col">
                        <aside class="twitter-sidebar-right app-sidebar-right">

                            <!-- Trending Venues -->
                            <div class="trending-box">
                                <div class="trending-header">
                                    <h3>🔥 Trend Mekanlar</h3>
                                </div>
                                <?php if (empty($topVenues)): ?>
                                    <div class="trending-item">
                                        <p style="color: var(--text-subtle);">Henüz trend yok</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($topVenues, 0, 3) as $index => $venue): ?>
                                        <a href="venue-detail?id=<?php echo $venue['id']; ?>" class="trending-item">
                                            <div class="trending-category"><?php echo $index + 1; ?>. sırada</div>
                                            <div class="trending-name"><?php echo escape($venue['name']); ?></div>
                                            <div class="trending-count"><?php echo $venue['checkin_count']; ?> check-in</div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="trending-footer">
                                    <a href="venues">Tüm Mekanları Gör</a>
                                </div>
                            </div>

                            <!-- Weekly Leaderboard -->
                            <div class="leaderboard-mini-box">
                                <div class="leaderboard-mini-header">
                                    <span>🏆</span>
                                    <h3>Haftalık Liderlik</h3>
                                </div>
                                <?php if (empty($topUsers)): ?>
                                    <div class="leaderboard-mini-item">
                                        <p style="color: var(--text-subtle);">Henüz lider yok</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($topUsers as $index => $user): ?>
                                        <div class="leaderboard-mini-item <?php echo $user['id'] == $userId ? 'is-me' : ''; ?>">
                                            <div class="leaderboard-mini-rank"><?php echo $index + 1; ?></div>
                                            <div class="leaderboard-mini-avatar">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div class="leaderboard-mini-info">
                                                <div class="leaderboard-mini-name"><?php echo escape($user['username']); ?>
                                                </div>
                                                <div class="leaderboard-mini-checkins"><?php echo $user['checkin_count']; ?>
                                                    check-in</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <div class="leaderboard-mini-footer">
                                    <a href="leaderboard">Tümünü Gör →</a>
                                </div>
                            </div>

                            <!-- Weekly Goals -->
                            <div class="goals-box">
                                <div class="goals-header">
                                    <span>🎯</span>
                                    <h3>Haftalık Hedef</h3>
                                </div>
                                <div class="goal-item">
                                    <div class="goal-label">
                                        <span class="goal-name">Bu hafta <?php echo $weeklyGoal; ?> check-in</span>
                                        <span
                                            class="goal-progress-text"><?php echo $weeklyCheckins; ?>/<?php echo $weeklyGoal; ?></span>
                                    </div>
                                    <div class="goal-bar">
                                        <div class="goal-fill" style="width: <?php echo $goalProgress; ?>%"></div>
                                    </div>
                                </div>
                                <?php if ($userRank == 0 || $userRank > 10): ?>
                                    <div class="goal-item">
                                        <div class="goal-label">
                                            <span class="goal-name">Top 10'a gir</span>
                                            <span class="goal-progress-text"><?php echo $top10Remaining; ?> kaldı</span>
                                        </div>
                                        <div class="goal-bar">
                                            <div class="goal-fill"
                                                style="width: <?php echo max(0, 100 - ($top10Remaining * 10)); ?>%"></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="goal-item">
                                        <div class="goal-label">
                                            <span class="goal-name">Top 10'dasın! 🎉</span>
                                            <span class="goal-progress-text">#<?php echo $userRank; ?></span>
                                        </div>
                                        <div class="goal-bar">
                                            <div class="goal-fill" style="width: 100%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ads Carousel -->
                            <div class="partners-box">
                                <div class="partners-header">
                                    <span>📢</span>
                                    <h3>Sponsor</h3>
                                </div>
                                <div class="partner-carousel">
                                    <div class="partner-slides">
                                        <?php if (!empty($carouselAds)): ?>
                                            <?php foreach ($carouselAds as $index => $ad): ?>
                                                <a href="<?php echo escape($ad['link_url'] ?: '#'); ?>" target="_blank"
                                                    class="partner-slide <?php echo $index === 0 ? 'active' : ''; ?>"
                                                    data-detail="<?php echo escape($ad['link_url'] ?: '#'); ?>">
                                                    <img src="<?php echo BASE_URL . '/' . escape($ad['image_url']); ?>"
                                                        alt="<?php echo escape($ad['title']); ?>">
                                                </a>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <a href="#" class="partner-slide active">
                                                <div
                                                    style="background: linear-gradient(135deg, var(--orange-primary), var(--orange-deeper)); padding: 40px 20px; text-align: center; color: white;">
                                                    <div style="font-size: 2rem; margin-bottom: 8px;">📢</div>

                                                </div>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (count($carouselAds) > 1): ?>
                                        <div class="partner-dots">
                                            <?php foreach ($carouselAds as $index => $ad): ?>
                                                <button class="partner-dot <?php echo $index === 0 ? 'active' : ''; ?>"
                                                    data-index="<?php echo $index; ?>"></button>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($carouselAds) && $carouselAds[0]['link_url']): ?>
                                    <div class="partners-footer">
                                        <a href="<?php echo escape($carouselAds[0]['link_url']); ?>" id="ad-detail-link"
                                            target="_blank">Detaylar →</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </aside>
                    </div>
                    <!-- End sağ sidebar col -->

                </div>
                <!-- End iç row -->
            </div>
            <!-- End orta içerik col -->

            <!-- Sağ Sponsor: col-auto, sabit 300px -->
            <div class="col-auto app-sponsor-col">
                <?php require_once '../includes/sidebar-right.php'; ?>
            </div>

        </div>
        <!-- End row -->
    </div>
    <!-- End container-fluid -->

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-sponsor">
            <?php if (!empty($footerAds)): ?>
                <?php $fAd = $footerAds[0]; ?>
                <a href="<?php echo escape($fAd['link_url'] ?: '#'); ?>" target="_blank"
                    style="display: block; text-align: center;">
                    <img src="<?php echo BASE_URL . '/' . escape($fAd['image_url']); ?>"
                        alt="<?php echo escape($fAd['title']); ?>"
                        style="max-width: 100%; max-height: 120px; border-radius: 8px;">
                </a>
            <?php else: ?>
                <div class="footer-sponsor-placeholder"
                    style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-align: center;">
                    <span style="font-size: 1.5rem;">📢</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="footer-content">
            <div class="footer-about">
                <h3>Sociaera</h3>
                <p>Sociaera, sosyal keşif ve check-in platformudur. Favori mekanlarınızda anlarınızı paylaşın.
                </p>
            </div>
            <div class="footer-links">
                <h4>Keşfet</h4>
                <ul>
                    <li><a href="venues">Mekanlar</a></li>
                    <li><a href="leaderboard">Liderlik</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Hesap</h4>
                <ul>
                    <li><a href="profile">Profilim</a></li>
                    <li><a href="settings">Ayarlar</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Sociaera. Tüm hakları saklıdır.</p>
        </div>
    </footer>

    <!-- Comment Modal -->
    <div id="commentModal" class="modal-overlay" style="display: none;">
        <div class="modal-content comment-modal">
            <div class="modal-header">
                <h3>Yorumlar</h3>
                <button class="modal-close" id="closeCommentModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="commentsList" class="comments-list">
                </div>
            </div>
            <div class="modal-footer">
                <form id="commentForm" class="comment-form">
                    <input type="hidden" id="commentCheckinId" value="">
                    <input type="hidden" id="commentRepostId" value="">
                    <div id="comment-image-preview" class="compose-image-preview" style="display: none;">
                        <img id="comment-image-preview-img" src="" alt="Preview">
                        <button type="button" id="remove-comment-image" class="image-preview-remove">×</button>
                    </div>
                    <div class="comment-input-row">
                        <textarea id="commentInput" class="comment-input" placeholder="Yorum yaz..."
                            maxlength="500"></textarea>
                        <input type="file" id="comment-image-input" accept="image/jpeg,image/png,image/gif,image/webp"
                            style="display: none;">
                        <button type="button" class="compose-tool-btn" id="comment-image-btn"
                            title="Fotoğraf Ekle">📷</button>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" id="submitComment">Gönder</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Repost Modal -->
    <div id="repostModal" class="modal-overlay" style="display: none;">
        <div class="modal-content repost-modal">
            <div class="modal-header">
                <h3>🔄 Repost</h3>
                <button class="modal-close" id="closeRepostModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="repostPreview" class="repost-preview">
                    <!-- Orijinal post önizlemesi buraya gelecek -->
                </div>
                <form id="repostForm" class="repost-form">
                    <input type="hidden" id="repostCheckinId" value="">
                    <textarea id="repostQuote" class="repost-quote-input" placeholder="Alıntı ekle (opsiyonel)..."
                        maxlength="500"></textarea>
                    <div class="repost-form-actions">
                        <span class="char-count"><span id="quoteCharCount">0</span>/500</span>
                        <button type="submit" class="btn btn-primary" id="submitRepost">Repostla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sidebar Dropdown Script -->
    <script>
        (function () {
            const dropdowns = document.querySelectorAll('.sidebar-nav-dropdown');

            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.has-dropdown');
                const menu = dropdown.querySelector('.sidebar-dropdown-menu');

                if (toggle && menu) {
                    // Feed dropdown default open
                    if (toggle.id === 'feedDropdownToggle') {
                        menu.classList.add('show');
                        toggle.classList.add('open');
                    }

                    toggle.addEventListener('click', function () {
                        menu.classList.toggle('show');
                        toggle.classList.toggle('open');
                    });
                }
            });
        })();
    </script>

    <!-- Ads Carousel Script -->
    <script>
        (function () {
            const slides = document.querySelectorAll('.partner-slide');
            const dots = document.querySelectorAll('.partner-dot');
            const detailLink = document.getElementById('ad-detail-link');
            let currentIndex = 0;
            const totalSlides = slides.length;

            if (totalSlides === 0) return;

            function updateDetailLink(index) {
                const activeSlide = slides[index];
                if (activeSlide && detailLink) {
                    const detailUrl = activeSlide.dataset.detail || activeSlide.href;
                    detailLink.href = detailUrl;
                }
            }

            function showSlide(index) {
                slides.forEach((slide, i) => {
                    slide.classList.toggle('active', i === index);
                });
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
                currentIndex = index;
                updateDetailLink(index);
            }

            // Initialize detail link
            updateDetailLink(0);

            function nextSlide() {
                showSlide((currentIndex + 1) % totalSlides);
            }

            // Auto-play every 5 seconds
            let autoPlay = setInterval(nextSlide, 5000);

            // Dot click handlers
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    clearInterval(autoPlay);
                    showSlide(index);
                    autoPlay = setInterval(nextSlide, 5000);
                });
            });

            // Pause on hover
            const carousel = document.querySelector('.partner-carousel');
            if (carousel) {
                carousel.addEventListener('mouseenter', () => clearInterval(autoPlay));
                carousel.addEventListener('mouseleave', () => {
                    autoPlay = setInterval(nextSlide, 5000);
                });
            }
        })();

        // Post Composition with @ Mention
        (function () {
            const postContent = document.getElementById('post-content');
            const venueAutocomplete = document.getElementById('venue-autocomplete');
            const selectedVenueDiv = document.getElementById('selected-venue');
            const venueNameSpan = document.getElementById('venue-name');
            const venueIdInput = document.getElementById('venue-id');
            const removeVenueBtn = document.getElementById('remove-venue');
            const submitBtn = document.getElementById('submit-btn');
            const postForm = document.getElementById('post-form');
            const postError = document.getElementById('post-error');
            const postSuccess = document.getElementById('post-success');
            const venuePickerBtn = document.getElementById('venue-picker-btn');

            let searchTimeout = null;
            let currentSearchQuery = '';

            // Detect @ in textarea
            postContent.addEventListener('input', function () {
                const value = this.value;
                const cursorPos = this.selectionStart;

                // Find @ before cursor
                const textBeforeCursor = value.substring(0, cursorPos);
                const atIndex = textBeforeCursor.lastIndexOf('@');

                if (atIndex !== -1) {
                    const searchText = textBeforeCursor.substring(atIndex + 1);
                    // Only search if no space after @
                    if (!searchText.includes(' ') && searchText.length > 0) {
                        currentSearchQuery = searchText;
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => searchVenues(searchText), 300);
                    } else if (searchText.length === 0) {
                        // Just typed @, show all venues
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => searchVenues(''), 300);
                    } else {
                        hideAutocomplete();
                    }
                } else {
                    hideAutocomplete();
                }

                updateSubmitButton();
            });

            // Venue picker button click
            venuePickerBtn.addEventListener('click', function () {
                postContent.value += '@';
                postContent.focus();
                searchVenues('');
            });

            // Search venues API
            async function searchVenues(query) {
                try {
                    // Paralel olarak hem mekan hem kullanıcı ara
                    const [venueResponse, userResponse] = await Promise.all([
                        fetch(`<?php echo BASE_URL; ?>/api/venue-search.php?q=${encodeURIComponent(query)}`),
                        fetch(`<?php echo BASE_URL; ?>/api/search-users.php?q=${encodeURIComponent(query)}`)
                    ]);

                    const venueData = await venueResponse.json();
                    const userData = await userResponse.json();

                    const venues = venueData.venues || [];
                    const users = userData.users || [];

                    if (venues.length > 0 || users.length > 0) {
                        showCombinedAutocomplete(venues, users);
                    } else {
                        showAutocompleteEmpty();
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    hideAutocomplete();
                }
            }

            // Show combined autocomplete (venues + users)
            function showCombinedAutocomplete(venues, users) {
                let html = '';

                // Önce mekanları göster
                if (venues.length > 0) {
                    html += '<div class="autocomplete-section-title">📍 Mekanlar</div>';
                    venues.slice(0, 4).forEach(venue => {
                        html += `
                        <div class="venue-option" data-id="${venue.id}" data-name="${escapeHtml(venue.name)}" data-type="venue">
                            <div class="venue-option-icon">📍</div>
                            <div class="venue-option-info">
                                <div class="venue-option-name">${escapeHtml(venue.name)}</div>
                                ${venue.address ? `<div class="venue-option-address">${escapeHtml(venue.address)}</div>` : ''}
                            </div>
                        </div>
                    `;
                    });
                }

                // Sonra kullanıcıları göster
                if (users.length > 0) {
                    html += '<div class="autocomplete-section-title">👤 Kullanıcılar</div>';
                    users.slice(0, 4).forEach(user => {
                        html += `
                        <div class="user-option" data-id="${user.id}" data-tag="${escapeHtml(user.tag)}" data-username="${escapeHtml(user.username)}" data-type="user">
                            <div class="user-option-avatar">
                                ${user.avatar_url
                                ? `<img src="<?php echo BASE_URL; ?>/${user.avatar_url}" alt="">`
                                : user.username.charAt(0).toUpperCase()}
                            </div>
                            <div class="user-option-info">
                                <div class="user-option-name">${escapeHtml(user.username)}</div>
                                <div class="user-option-tag">@${escapeHtml(user.tag)}</div>
                            </div>
                        </div>
                    `;
                    });
                }

                venueAutocomplete.innerHTML = html;
                venueAutocomplete.classList.add('active');

                // Mekan click handlers
                venueAutocomplete.querySelectorAll('.venue-option').forEach(option => {
                    option.addEventListener('click', function () {
                        selectVenue(this.dataset.id, this.dataset.name);
                    });
                });

                // Kullanıcı click handlers
                venueAutocomplete.querySelectorAll('.user-option').forEach(option => {
                    option.addEventListener('click', function () {
                        selectUser(this.dataset.tag, this.dataset.username);
                    });
                });
            }

            // Kullanıcı seç - metne @tag ekle
            function selectUser(tag, username) {
                const value = postContent.value;
                const cursorPos = postContent.selectionStart;
                const textBeforeCursor = value.substring(0, cursorPos);
                const textAfterCursor = value.substring(cursorPos);
                const atIndex = textBeforeCursor.lastIndexOf('@');

                if (atIndex !== -1) {
                    // @ ve sonrasını @tag ile değiştir
                    const newText = textBeforeCursor.substring(0, atIndex) + '@' + tag + ' ' + textAfterCursor;
                    postContent.value = newText;
                    // Cursor'u @tag'den sonraya koy
                    const newCursorPos = atIndex + tag.length + 2;
                    postContent.setSelectionRange(newCursorPos, newCursorPos);
                }

                hideAutocomplete();
                postContent.focus();
            }

            // Show autocomplete dropdown
            function showAutocomplete(venues) {
                let html = '';
                venues.forEach(venue => {
                    html += `
                    <div class="venue-option" data-id="${venue.id}" data-name="${escapeHtml(venue.name)}">
                        <div class="venue-option-icon">📍</div>
                        <div class="venue-option-info">
                            <div class="venue-option-name">${escapeHtml(venue.name)}</div>
                            ${venue.address ? `<div class="venue-option-address">${escapeHtml(venue.address)}</div>` : ''}
                        </div>
                    </div>
                `;
                });
                venueAutocomplete.innerHTML = html;
                venueAutocomplete.classList.add('active');

                // Add click handlers
                venueAutocomplete.querySelectorAll('.venue-option').forEach(option => {
                    option.addEventListener('click', function () {
                        selectVenue(this.dataset.id, this.dataset.name);
                    });
                });
            }

            function showAutocompleteEmpty() {
                venueAutocomplete.innerHTML = '<div class="venue-autocomplete-empty">Mekan veya kullanıcı bulunamadı</div>';
                venueAutocomplete.classList.add('active');
            }

            function hideAutocomplete() {
                venueAutocomplete.classList.remove('active');
            }

            // Select venue
            function selectVenue(id, name) {
                // Remove @ and search text from content
                const value = postContent.value;
                const atIndex = value.lastIndexOf('@');
                if (atIndex !== -1) {
                    postContent.value = value.substring(0, atIndex).trim();
                }

                // Set venue
                venueIdInput.value = id;
                venueNameSpan.textContent = name;
                selectedVenueDiv.style.display = 'inline-flex';
                hideAutocomplete();
                updateSubmitButton();
                postContent.focus();
            }

            // Remove venue
            removeVenueBtn.addEventListener('click', function () {
                venueIdInput.value = '';
                venueNameSpan.textContent = '';
                selectedVenueDiv.style.display = 'none';
                updateSubmitButton();
            });

            // Update submit button state
            function updateSubmitButton() {
                const hasVenue = venueIdInput.value !== '';
                submitBtn.disabled = !hasVenue;
            }

            // Form submission
            postForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                if (!venueIdInput.value) {
                    showError('Lütfen bir mekan etiketleyin (@mekan)');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Paylaşılıyor...';
                hideMessages();

                try {
                    const formData = new FormData();
                    formData.append('content', postContent.value);
                    formData.append('venue_id', venueIdInput.value);

                    // Add image if selected
                    const imageInput = document.getElementById('image-input');
                    if (imageInput.files.length > 0) {
                        formData.append('image', imageInput.files[0]);
                    }

                    const response = await fetch('<?php echo BASE_URL; ?>/api/create-post.php', {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccess(data.message);
                        // Reset form
                        postContent.value = '';
                        venueIdInput.value = '';
                        venueNameSpan.textContent = '';
                        selectedVenueDiv.style.display = 'none';
                        // Reset image
                        imageInput.value = '';
                        document.getElementById('image-preview').style.display = 'none';
                        // Reload page after short delay to show new post
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.error || 'Bir hata oluştu');
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    showError('Bağlantı hatası');
                    submitBtn.disabled = false;
                }

                submitBtn.textContent = 'Paylaş';
                updateSubmitButton();
            });

            function showError(msg) {
                postError.textContent = msg;
                postError.style.display = 'block';
                postSuccess.style.display = 'none';
            }

            function showSuccess(msg) {
                postSuccess.textContent = msg;
                postSuccess.style.display = 'block';
                postError.style.display = 'none';
            }

            function hideMessages() {
                postError.style.display = 'none';
                postSuccess.style.display = 'none';
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Close autocomplete on click outside
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.compose-input-wrapper')) {
                    hideAutocomplete();
                }
            });

            // Image picker functionality
            const imagePickerBtn = document.getElementById('image-picker-btn');
            const imageInput = document.getElementById('image-input');
            const imagePreview = document.getElementById('image-preview');
            const imagePreviewImg = document.getElementById('image-preview-img');
            const removeImageBtn = document.getElementById('remove-image');

            imagePickerBtn.addEventListener('click', function () {
                imageInput.click();
            });

            imageInput.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    const file = this.files[0];

                    // Check file size (5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        showError('Dosya boyutu 5MB\'dan küçük olmalıdır');
                        this.value = '';
                        return;
                    }

                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        imagePreviewImg.src = e.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });

            removeImageBtn.addEventListener('click', function () {
                imageInput.value = '';
                imagePreview.style.display = 'none';
                imagePreviewImg.src = '';
            });
        })();

        // Post Etkileşimleri (Like, Repost, Comment, Delete)
        (function () {
            console.log('=== Post Interactions Script Started ===');
            const API_URL = '<?php echo BASE_URL; ?>/api/interactions.php';

            // Delete butonları
            console.log('Delete buttons found:', document.querySelectorAll('.tweet-delete-btn').length);
            document.querySelectorAll('.tweet-delete-btn').forEach(btn => {
                console.log('Attaching delete listener to:', btn.dataset.checkinId);
                btn.addEventListener('click', async function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Delete button clicked for checkin:', this.dataset.checkinId);

                    if (!confirm('Bu postu silmek istediğinize emin misiniz?')) {
                        return;
                    }

                    const checkinId = this.dataset.checkinId;
                    const card = this.closest('.tweet-card');

                    try {
                        const formData = new FormData();
                        formData.append('checkin_id', checkinId);

                        console.log('Sending delete request to:', API_URL + '?action=delete');
                        const response = await fetch(API_URL + '?action=delete', {
                            method: 'POST',
                            headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
                            body: formData
                        });
                        const data = await response.json();
                        console.log('Delete response:', data);

                        if (data.success) {
                            // Kartı kaldır
                            card.style.transition = 'all 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(-20px)';
                            setTimeout(() => card.remove(), 300);
                        } else {
                            alert(data.error || 'Silme işlemi başarısız.');
                        }
                    } catch (error) {
                        console.error('Delete error:', error);
                        alert('Bağlantı hatası.');
                    }
                });
            });

            // Like butonları
            const likeButtons = document.querySelectorAll('.action-like');
            console.log('Like buttons found:', likeButtons.length);
            likeButtons.forEach(btn => {
                btn.addEventListener('click', async function () {
                    console.log('Like button clicked!', this.dataset.checkinId);
                    const checkinId = this.dataset.checkinId;
                    const repostId = this.dataset.repostId || '';
                    const likeIcon = this.querySelector('.like-icon');
                    const countSpan = this.querySelector('.action-count');

                    try {
                        const formData = new FormData();
                        formData.append('checkin_id', checkinId);
                        if (repostId) {
                            formData.append('repost_id', repostId);
                        }

                        const response = await fetch(API_URL + '?action=like', {
                            method: 'POST',
                            headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
                            body: formData
                        });
                        console.log('Like API response status:', response.status);
                        const data = await response.json();
                        console.log('Like API data:', data);

                        if (data.success) {
                            if (data.liked) {
                                this.classList.add('active');
                                likeIcon.textContent = '❤️';
                            } else {
                                this.classList.remove('active');
                                likeIcon.textContent = '🤍';
                            }
                            countSpan.textContent = data.count;
                            this.classList.add('pulse');
                            setTimeout(() => this.classList.remove('pulse'), 300);
                        } else {
                            console.error('Like failed:', data.error);
                        }
                    } catch (error) {
                        console.error('Like error:', error);
                    }
                });
            });

            // Utility function for HTML escaping
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Repost Modal
            const repostModal = document.getElementById('repostModal');
            const repostPreview = document.getElementById('repostPreview');
            const repostForm = document.getElementById('repostForm');
            const repostQuote = document.getElementById('repostQuote');
            const repostCheckinId = document.getElementById('repostCheckinId');
            const closeRepostBtn = document.getElementById('closeRepostModal');
            const quoteCharCount = document.getElementById('quoteCharCount');
            let currentRepostBtn = null;

            // Debug log
            console.log('Repost Modal Elements:', { repostModal, repostForm, repostQuote, repostCheckinId });

            // Character count for quote (with null check)
            if (repostQuote) {
                repostQuote.addEventListener('input', function () {
                    if (quoteCharCount) quoteCharCount.textContent = this.value.length;
                });
            }

            // Repost butonları - modal aç
            document.querySelectorAll('.action-repost').forEach(btn => {
                btn.addEventListener('click', async function () {
                    console.log('Repost button clicked!', this.dataset.checkinId);

                    const checkinId = this.dataset.checkinId;
                    const card = this.closest('.tweet-card');
                    const countSpan = this.querySelector('.action-count');
                    const isActive = this.classList.contains('active');
                    currentRepostBtn = this;

                    // Eğer zaten repostladıysa, direkt kaldır (toggle)
                    if (isActive) {
                        try {
                            const formData = new FormData();
                            formData.append('checkin_id', checkinId);

                            const response = await fetch(API_URL + '?action=repost', {
                                method: 'POST',
                                headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
                                body: formData
                            });
                            const data = await response.json();

                            if (data.success) {
                                this.classList.remove('active');
                                countSpan.textContent = data.count;
                                this.classList.add('pulse');
                                setTimeout(() => this.classList.remove('pulse'), 300);
                            } else {
                                alert(data.error || 'Bir hata oluştu.');
                            }
                        } catch (error) {
                            console.error('Repost toggle error:', error);
                        }
                        return;
                    }

                    // Repostlamadıysa modal aç
                    if (!repostModal || !repostPreview || !repostCheckinId) {
                        console.error('Repost modal elements not found!');
                        alert('Repost modal yüklenemedi. Sayfayı yenileyip tekrar deneyin.');
                        return;
                    }

                    // Orijinal post bilgilerini al
                    const username = card.querySelector('.tweet-username')?.textContent.trim() || 'Kullanıcı';
                    const venue = card.querySelector('.tweet-venue')?.textContent.trim() || 'Mekan';
                    const note = card.querySelector('.tweet-note')?.textContent.trim() || '';

                    // Önizleme oluştur
                    repostPreview.innerHTML = `
                    <div class="preview-card">
                        <div class="preview-header">
                            <span class="preview-username">${escapeHtml(username)}</span>
                        </div>
                        <div class="preview-venue">📍 ${escapeHtml(venue)}</div>
                        ${note ? `<p class="preview-note">${escapeHtml(note)}</p>` : ''}
                    </div>
                `;

                    repostCheckinId.value = checkinId;
                    if (repostQuote) repostQuote.value = '';
                    if (quoteCharCount) quoteCharCount.textContent = '0';
                    repostModal.style.display = 'flex';
                    console.log('Repost modal opened!');
                });
            });

            // Modal kapat
            if (closeRepostBtn) closeRepostBtn.addEventListener('click', () => repostModal.style.display = 'none');
            if (repostModal) repostModal.addEventListener('click', (e) => { if (e.target === repostModal) repostModal.style.display = 'none'; });

            // Repost form submit
            if (repostForm) repostForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                console.log('Repost form submitted!');
                const checkinId = repostCheckinId.value;
                const quote = repostQuote ? repostQuote.value.trim() : '';
                const submitBtn = document.getElementById('submitRepost');

                submitBtn.disabled = true;
                submitBtn.textContent = 'Gönderiliyor...';

                try {
                    const formData = new FormData();
                    formData.append('checkin_id', checkinId);
                    formData.append('quote', quote);

                    console.log('Sending repost request to:', API_URL + '?action=repost');
                    console.log('Checkin ID:', checkinId, 'Quote:', quote);

                    const response = await fetch(API_URL + '?action=repost', {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': window.CSRF_TOKEN },
                        body: formData
                    });

                    console.log('Response status:', response.status);
                    const data = await response.json();
                    console.log('Repost response data:', data);

                    if (data.success) {
                        // Sayıyı güncelle
                        if (currentRepostBtn) {
                            const countSpan = currentRepostBtn.querySelector('.action-count');
                            countSpan.textContent = data.count;
                            currentRepostBtn.classList.add('active');
                            currentRepostBtn.classList.add('pulse');
                            setTimeout(() => currentRepostBtn.classList.remove('pulse'), 300);
                        }

                        repostModal.style.display = 'none';

                        // Başarı mesajı ve sayfa yenile
                        alert(data.message || 'Repost başarıyla oluşturuldu!');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        console.error('Repost failed:', data.error);
                        alert(data.error || 'Bir hata oluştu.');
                    }
                } catch (error) {
                    console.error('Repost error:', error);
                    alert('Bağlantı hatası: ' + error.message);
                }

                submitBtn.disabled = false;
                submitBtn.textContent = 'Repostla';
            });

            // Yorum Modal
            const modal = document.getElementById('commentModal');
            const commentsList = document.getElementById('commentsList');
            const commentForm = document.getElementById('commentForm');
            const commentInput = document.getElementById('commentInput');
            const commentCheckinId = document.getElementById('commentCheckinId');
            const commentRepostId = document.getElementById('commentRepostId');
            const closeBtn = document.getElementById('closeCommentModal');

            document.querySelectorAll('.action-comment').forEach(btn => {
                btn.addEventListener('click', async function () {
                    const checkinId = this.dataset.checkinId;
                    const repostId = this.dataset.repostId || '';
                    commentCheckinId.value = checkinId;
                    commentRepostId.value = repostId;
                    await loadComments(checkinId, repostId);
                    modal.style.display = 'flex';
                });
            });

            closeBtn.addEventListener('click', () => modal.style.display = 'none');
            modal.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

            async function loadComments(checkinId, repostId) {
                commentsList.innerHTML = '<div class="loading-comments">Yükleniyor...</div>';
                try {
                    let url = API_URL + '?action=comments&checkin_id=' + checkinId;
                    if (repostId) {
                        url += '&repost_id=' + repostId;
                    }
                    const response = await fetch(url);
                    const data = await response.json();
                    if (data.success && data.comments.length > 0) {
                        commentsList.innerHTML = data.comments.map(c => {
                            let imageHtml = c.image ? `<div class="comment-image"><img src="<?php echo BASE_URL; ?>/uploads/comments/${c.image}" alt="Yorum fotoğrafı"></div>` : '';
                            return `
                        <div class="comment-item">
                            <div class="comment-avatar">${c.username.charAt(0).toUpperCase()}</div>
                            <div class="comment-content">
                                <div class="comment-header">
                                    <span class="comment-username">${escapeHtml(c.username)}</span>
                                    <span class="comment-time">${formatTime(c.created_at)}</span>
                                </div>
                                <p class="comment-text">${escapeHtml(c.content)}</p>
                                ${imageHtml}
                            </div>
                        </div>
                    `}).join('');
                    } else {
                        commentsList.innerHTML = '<div class="no-comments">Henüz yorum yok.</div>';
                    }
                } catch (error) {
                    commentsList.innerHTML = '<div class="error-comments">Yorumlar yüklenemedi.</div>';
                }
            }

            // Comment image picker functionality
            const commentImageBtn = document.getElementById('comment-image-btn');
            const commentImageInput = document.getElementById('comment-image-input');
            const commentImagePreview = document.getElementById('comment-image-preview');
            const commentImagePreviewImg = document.getElementById('comment-image-preview-img');
            const removeCommentImageBtn = document.getElementById('remove-comment-image');

            commentImageBtn.addEventListener('click', function () {
                commentImageInput.click();
            });

            commentImageInput.addEventListener('change', function () {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Dosya boyutu 5MB\'dan küçük olmalıdır');
                        this.value = '';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        commentImagePreviewImg.src = e.target.result;
                        commentImagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });

            removeCommentImageBtn.addEventListener('click', function () {
                commentImageInput.value = '';
                commentImagePreview.style.display = 'none';
                commentImagePreviewImg.src = '';
            });

            commentForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const content = commentInput.value.trim();
                const checkinId = commentCheckinId.value;
                const repostId = commentRepostId.value;
                const hasImage = commentImageInput.files.length > 0;

                if (!content && !hasImage) {
                    alert('Yorum veya fotoğraf gerekli');
                    return;
                }

                const submitBtn = document.getElementById('submitComment');
                submitBtn.disabled = true;

                try {
                    const formData = new FormData();
                    formData.append('checkin_id', checkinId);
                    formData.append('content', content);
                    if (repostId) {
                        formData.append('repost_id', repostId);
                    }
                    if (hasImage) {
                        formData.append('image', commentImageInput.files[0]);
                    }

                    const response = await fetch(API_URL + '?action=comment', { method: 'POST', headers: { 'X-CSRF-Token': window.CSRF_TOKEN }, body: formData });
                    const data = await response.json();

                    if (data.success) {
                        commentInput.value = '';
                        commentImageInput.value = '';
                        commentImagePreview.style.display = 'none';

                        const newComment = document.createElement('div');
                        newComment.className = 'comment-item new';
                        let imageHtml = data.comment.image ? `<div class="comment-image"><img src="<?php echo BASE_URL; ?>/${data.comment.image}" alt="Yorum fotoğrafı"></div>` : '';
                        newComment.innerHTML = `
                        <div class="comment-avatar">${data.comment.username.charAt(0).toUpperCase()}</div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <span class="comment-username">${escapeHtml(data.comment.username)}</span>
                                <span class="comment-time">Şimdi</span>
                            </div>
                            <p class="comment-text">${escapeHtml(data.comment.content)}</p>
                            ${imageHtml}
                        </div>
                    `;
                        const noComments = commentsList.querySelector('.no-comments');
                        if (noComments) noComments.remove();
                        commentsList.appendChild(newComment);

                        const btn = document.querySelector(`.action-comment[data-checkin-id="${checkinId}"]`);
                        if (btn) btn.querySelector('.action-count').textContent = data.count;
                    }
                } catch (error) {
                    alert('Bağlantı hatası.');
                }
                submitBtn.disabled = false;
            });

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function formatTime(dateStr) {
                const diff = (new Date() - new Date(dateStr)) / 1000;
                if (diff < 60) return 'Şimdi';
                if (diff < 3600) return Math.floor(diff / 60) + 'd';
                if (diff < 86400) return Math.floor(diff / 3600) + 'sa';
                return Math.floor(diff / 86400) + 'g';
            }

            // Fetch notification count
            async function updateNotificationBadge() {
                try {
                    const response = await fetch('<?php echo BASE_URL; ?>/api/notifications.php?action=count');
                    const data = await response.json();
                    if (data.success && data.count > 0) {
                        const badge = document.getElementById('notification-badge');
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = 'inline-flex';
                        }
                    }
                } catch (e) { }
            }
            updateNotificationBadge();
            setInterval(updateNotificationBadge, 60000); // Her dakika güncelle
        })();
    </script>

</body>

</html>