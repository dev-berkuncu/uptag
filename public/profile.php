<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Profil sayfası giriş gerektirir
requireLogin();

// Profil ID'si - kendi profilim veya başkasının profili
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];
$isOwnProfile = ($profileId == $_SESSION['user_id']);

$userObj = new User();
$profileUser = $userObj->getUserById($profileId);

require_once '../includes/ads_logic.php';

if (!$profileUser) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = $isOwnProfile ? 'Profilim' : escape($profileUser['username']) . ' - Profil';

// Leaderboard verileri
$leaderboard = new Leaderboard();
$weekInfo = $leaderboard->getWeekInfo();

// Kullanıcının istatistikleri
$db = Database::getInstance()->getConnection();

// Takipçi sayısı
$followerStmt = $db->prepare("SELECT COUNT(*) as count FROM user_follows WHERE following_id = ?");
$followerStmt->execute([$profileId]);
$followerCount = $followerStmt->fetch()['count'] ?? 0;

// Takip edilen sayısı
$followingStmt = $db->prepare("SELECT COUNT(*) as count FROM user_follows WHERE follower_id = ?");
$followingStmt->execute([$profileId]);
$followingCount = $followingStmt->fetch()['count'] ?? 0;

// Takip ediyor mu?
$isFollowing = false;
if (!$isOwnProfile) {
    $followCheck = $db->prepare("SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $followCheck->execute([$_SESSION['user_id'], $profileId]);
    $isFollowing = $followCheck->fetch() ? true : false;
}

// Bu hafta check-in sayısı
$weeklyStmt = $db->prepare("
    SELECT COUNT(*) as count FROM checkins 
    WHERE user_id = ? AND created_at >= ? AND created_at <= ?
");
$weeklyStmt->execute([$profileId, $weekInfo['start'], $weekInfo['end']]);
$weeklyCheckins = $weeklyStmt->fetch()['count'] ?? 0;

// Toplam check-in sayısı
$totalStmt = $db->prepare("SELECT COUNT(*) as count FROM checkins WHERE user_id = ?");
$totalStmt->execute([$profileId]);
$totalCheckins = $totalStmt->fetch()['count'] ?? 0;

// Ziyaret edilen farklı mekan sayısı
$uniqueVenuesStmt = $db->prepare("SELECT COUNT(DISTINCT venue_id) as count FROM checkins WHERE user_id = ?");
$uniqueVenuesStmt->execute([$profileId]);
$uniqueVenues = $uniqueVenuesStmt->fetch()['count'] ?? 0;

// Haftalık sıralama
$topUsers = $leaderboard->getTopUsers(100, $weekInfo['start'], $weekInfo['end']);
$userRank = 0;
foreach ($topUsers as $index => $user) {
    if ($user['id'] == $profileId) {
        $userRank = $index + 1;
        break;
    }
}

// En çok check-in yapılan mekan (favori mekan)
$favoriteVenueStmt = $db->prepare("
    SELECT v.id, v.name, COUNT(*) as count 
    FROM checkins c 
    JOIN venues v ON c.venue_id = v.id 
    WHERE c.user_id = ? 
    GROUP BY v.id, v.name 
    ORDER BY count DESC 
    LIMIT 1
");
$favoriteVenueStmt->execute([$profileId]);
$favoriteVenue = $favoriteVenueStmt->fetch();

// Son check-in'ler
$checkin = new Checkin();
$recentCheckins = $checkin->getUserCheckins($profileId, 10);

// Her check-in için etkileşim sayılarını çek
foreach ($recentCheckins as &$ci) {
    // Like sayısı
    $likeStmt = $db->prepare("SELECT COUNT(*) as count FROM post_likes WHERE checkin_id = ?");
    $likeStmt->execute([$ci['id']]);
    $ci['like_count'] = $likeStmt->fetch()['count'] ?? 0;
    
    // Kullanıcı beğenmiş mi?
    $userLikeStmt = $db->prepare("SELECT id FROM post_likes WHERE checkin_id = ? AND user_id = ?");
    $userLikeStmt->execute([$ci['id'], $_SESSION['user_id']]);
    $ci['user_liked'] = $userLikeStmt->fetch() ? true : false;
    
    // Repost sayısı
    $repostStmt = $db->prepare("SELECT COUNT(*) as count FROM post_reposts WHERE checkin_id = ?");
    $repostStmt->execute([$ci['id']]);
    $ci['repost_count'] = $repostStmt->fetch()['count'] ?? 0;
    
    // Kullanıcı repost etmiş mi?
    $userRepostStmt = $db->prepare("SELECT id FROM post_reposts WHERE checkin_id = ? AND user_id = ?");
    $userRepostStmt->execute([$ci['id'], $_SESSION['user_id']]);
    $ci['user_reposted'] = $userRepostStmt->fetch() ? true : false;
    
    // Yorum sayısı
    $commentStmt = $db->prepare("SELECT COUNT(*) as count FROM post_comments WHERE checkin_id = ?");
    $commentStmt->execute([$ci['id']]);
    $ci['comment_count'] = $commentStmt->fetch()['count'] ?? 0;
}
unset($ci);

// Üyelik tarihi
$memberSince = date('F Y', strtotime($profileUser['created_at']));

// Avatar ve banner URL'leri
$avatarUrl = isset($profileUser['avatar']) && $profileUser['avatar'] ? BASE_URL . '/uploads/avatars/' . $profileUser['avatar'] : null;
$bannerUrl = isset($profileUser['banner']) && $profileUser['banner'] ? BASE_URL . '/uploads/banners/' . $profileUser['banner'] : null;

// Beğenilen postları çek
$likedPosts = [];
try {
    $likedStmt = $db->prepare("
        SELECT c.*, u.username, v.name as venue_name,
               (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
               (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
               (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
               (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
               (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted
        FROM post_likes pl
        JOIN checkins c ON pl.checkin_id = c.id
        JOIN users u ON c.user_id = u.id
        JOIN venues v ON c.venue_id = v.id
        WHERE pl.user_id = ? AND c.is_flagged = 0
        ORDER BY pl.created_at DESC
        LIMIT 20
    ");
    $likedStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $profileId]);
    $likedPosts = $likedStmt->fetchAll();
} catch (PDOException $e) {
    $likedPosts = [];
}

// Repost edilen postları çek
$repostedPosts = [];
try {
    $repostStmt = $db->prepare("
        SELECT c.*, u.username, v.name as venue_name,
               r.quote as repost_quote, r.created_at as repost_date,
               (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
               (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
               (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count,
               (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id AND user_id = ?) as user_liked,
               (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id AND user_id = ?) as user_reposted
        FROM post_reposts r
        JOIN checkins c ON r.checkin_id = c.id
        JOIN users u ON c.user_id = u.id
        JOIN venues v ON c.venue_id = v.id
        WHERE r.user_id = ? AND c.is_flagged = 0
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $repostStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $profileId]);
    $repostedPosts = $repostStmt->fetchAll();
} catch (PDOException $e) {
    $repostedPosts = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo escape($profileUser['username']); ?> - Sociaera Profili">
    <title><?php echo escape($pageTitle); ?> - Sociaera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'profile'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content profile-page-twitter">
            
            <!-- Twitter-style Banner -->
            <div class="profile-banner" <?php if ($bannerUrl): ?>style="background-image: url('<?php echo $bannerUrl; ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
                <?php if (!$bannerUrl): ?><div class="banner-gradient"></div><?php endif; ?>
            </div>

            <!-- Profile Card -->
            <div class="profile-card-twitter">
                <div class="profile-card-header">
                    <div class="profile-avatar-large">
                        <?php if ($avatarUrl): ?>
                            <img src="<?php echo $avatarUrl; ?>" alt="<?php echo escape($profileUser['username']); ?>">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($profileUser['username'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-actions">
                        <?php if ($isOwnProfile): ?>
                            <a href="settings" class="btn btn-secondary btn-sm">Profili Düzenle</a>
                        <?php else: ?>
                            <button id="followBtn" class="btn <?php echo $isFollowing ? 'btn-secondary' : 'btn-primary'; ?> btn-sm" data-user-id="<?php echo $profileId; ?>">
                                <?php echo $isFollowing ? 'Takip Ediliyor' : 'Takip Et'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="profile-card-body">
                    <h1 class="profile-name-twitter"><?php echo escape($profileUser['username']); ?></h1>
                    <span class="profile-handle">@<?php echo escape(!empty($profileUser['tag']) ? $profileUser['tag'] : strtolower($profileUser['username'])); ?></span>
                    
                    <?php if ($favoriteVenue): ?>
                    <p class="profile-bio">
                        📍 En sevdiği mekan: <a href="venue-detail?id=<?php echo $favoriteVenue['id']; ?>"><?php echo escape($favoriteVenue['name']); ?></a>
                    </p>
                    <?php endif; ?>

                    <div class="profile-meta-twitter">
                        <span class="meta-item">📅 <?php echo $memberSince; ?> tarihinden beri üye</span>
                        <?php if ($userRank > 0): ?>
                        <span class="meta-item">🏆 Bu hafta #<?php echo $userRank; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="profile-stats-inline">
                        <div class="stat-inline">
                            <span class="stat-number" id="followerCount"><?php echo $followerCount; ?></span>
                            <span class="stat-text">Takipçi</span>
                        </div>
                        <div class="stat-inline">
                            <span class="stat-number" id="followingCount"><?php echo $followingCount; ?></span>
                            <span class="stat-text">Takip</span>
                        </div>
                        <div class="stat-inline">
                            <span class="stat-number"><?php echo $totalCheckins; ?></span>
                            <span class="stat-text">Check-in</span>
                        </div>
                        <div class="stat-inline">
                            <span class="stat-number"><?php echo $uniqueVenues; ?></span>
                            <span class="stat-text">Mekan</span>
                        </div>
                        <div class="stat-inline">
                            <span class="stat-number">#<?php echo $userRank > 0 ? $userRank : '-'; ?></span>
                            <span class="stat-text">Sıralama</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="profile-tabs">
                <button class="tab-btn active" data-tab="checkins">Check-in'ler</button>
                <button class="tab-btn" data-tab="likes">Beğenilenler</button>
                <button class="tab-btn" data-tab="reposts">Repostlar</button>
            </div>

            <!-- Check-ins Feed -->
            <div class="profile-feed tab-content active" id="tab-checkins">
                <?php if (empty($recentCheckins)): ?>
                    <div class="empty-state-card">
                        <p>Henüz check-in yapılmamış</p>
                        <?php if ($isOwnProfile): ?>
                            <a href="venues" class="btn btn-primary btn-sm">İlk Check-in'ini Yap</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentCheckins as $ci): ?>
                        <div class="checkin-tweet">
                            <div class="tweet-avatar">
                                <?php if ($avatarUrl): ?>
                                    <img src="<?php echo $avatarUrl; ?>" alt="">
                                <?php else: ?>
                                    <span><?php echo strtoupper(substr($profileUser['username'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="tweet-content">
                                <div class="tweet-header">
                                    <span class="tweet-name"><?php echo escape($profileUser['username']); ?></span>
                                    <span class="tweet-handle">@<?php echo escape(!empty($profileUser['tag']) ? $profileUser['tag'] : strtolower($profileUser['username'])); ?></span>
                                    <span class="tweet-dot">·</span>
                                    <span class="tweet-time"><?php echo formatDate($ci['created_at'], true); ?></span>
                                    <?php if ($isOwnProfile): ?>
                                    <button class="tweet-delete-btn" data-checkin-id="<?php echo $ci['id']; ?>" title="Postu Sil">×</button>
                                    <?php endif; ?>
                                </div>
                                <div class="tweet-body">
                                    <p class="tweet-text">
                                        📍 <a href="venue-detail?id=<?php echo $ci['venue_id']; ?>" class="tweet-venue"><?php echo escape($ci['venue_name']); ?></a> mekanında check-in yaptı
                                    </p>
                                    <?php if (!empty($ci['note'])): ?>
                                        <p class="tweet-note">"<?php echo escape($ci['note']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                                <div class="tweet-actions">
                                    <button class="tweet-action comment-btn" data-checkin-id="<?php echo $ci['id']; ?>">
                                        <span class="tweet-action-icon">💬</span>
                                        <span class="action-count"><?php echo $ci['comment_count']; ?></span>
                                    </button>
                                    <button class="tweet-action repost-btn <?php echo $ci['user_reposted'] ? 'active' : ''; ?>" data-checkin-id="<?php echo $ci['id']; ?>">
                                        <span class="tweet-action-icon">🔄</span>
                                        <span class="action-count"><?php echo $ci['repost_count']; ?></span>
                                    </button>
                                    <button class="tweet-action like-btn <?php echo $ci['user_liked'] ? 'active' : ''; ?>" data-checkin-id="<?php echo $ci['id']; ?>">
                                        <span class="tweet-action-icon"><?php echo $ci['user_liked'] ? '❤️' : '🤍'; ?></span>
                                        <span class="action-count"><?php echo $ci['like_count']; ?></span>
                                    </button>
                                    <button class="tweet-action share-btn" data-checkin-id="<?php echo $ci['id']; ?>">
                                        <span class="tweet-action-icon">📤</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Liked Posts Feed -->
            <div class="profile-feed tab-content" id="tab-likes" style="display: none;">
                <?php if (empty($likedPosts)): ?>
                    <div class="empty-state-card">
                        <p>Henüz beğenilen post yok</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($likedPosts as $lp): ?>
                        <div class="checkin-tweet">
                            <div class="tweet-avatar">
                                <span><?php echo strtoupper(substr($lp['username'], 0, 1)); ?></span>
                            </div>
                            <div class="tweet-content">
                                <div class="tweet-header">
                                    <a href="profile.php?id=<?php echo $lp['user_id']; ?>" class="tweet-name"><?php echo escape($lp['username']); ?></a>
                                    <span class="tweet-handle">@<?php echo strtolower(escape($lp['username'])); ?></span>
                                    <span class="tweet-dot">·</span>
                                    <span class="tweet-time"><?php echo formatDate($lp['created_at'], true); ?></span>
                                </div>
                                <div class="tweet-body">
                                    <p class="tweet-text">
                                        📍 <a href="venue-detail?id=<?php echo $lp['venue_id']; ?>" class="tweet-venue"><?php echo escape($lp['venue_name']); ?></a> mekanında check-in yaptı
                                    </p>
                                    <?php if (!empty($lp['note'])): ?>
                                        <p class="tweet-note">"<?php echo escape($lp['note']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                                <div class="tweet-actions">
                                    <button class="tweet-action comment-btn" data-checkin-id="<?php echo $lp['id']; ?>">
                                        <span class="tweet-action-icon">💬</span>
                                        <span class="action-count"><?php echo $lp['comment_count']; ?></span>
                                    </button>
                                    <button class="tweet-action repost-btn <?php echo $lp['user_reposted'] ? 'active' : ''; ?>" data-checkin-id="<?php echo $lp['id']; ?>">
                                        <span class="tweet-action-icon">🔄</span>
                                        <span class="action-count"><?php echo $lp['repost_count']; ?></span>
                                    </button>
                                    <button class="tweet-action like-btn active" data-checkin-id="<?php echo $lp['id']; ?>">
                                        <span class="tweet-action-icon">❤️</span>
                                        <span class="action-count"><?php echo $lp['like_count']; ?></span>
                                    </button>
                                    <button class="tweet-action share-btn" data-checkin-id="<?php echo $lp['id']; ?>">
                                        <span class="tweet-action-icon">📤</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Reposted Posts Feed -->
            <div class="profile-feed tab-content" id="tab-reposts" style="display: none;">
                <?php if (empty($repostedPosts)): ?>
                    <div class="empty-state-card">
                        <p>Henüz repost yapılmamış</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($repostedPosts as $rp): ?>
                        <div class="checkin-tweet is-repost-card">
                            <?php if (!empty($rp['repost_quote'])): ?>
                            <div class="repost-quote-display">
                                "<?php echo escape($rp['repost_quote']); ?>"
                            </div>
                            <?php endif; ?>
                            <div class="embedded-post-profile">
                                <div class="tweet-avatar">
                                    <span><?php echo strtoupper(substr($rp['username'], 0, 1)); ?></span>
                                </div>
                                <div class="tweet-content">
                                    <div class="tweet-header">
                                        <a href="profile.php?id=<?php echo $rp['user_id']; ?>" class="tweet-name"><?php echo escape($rp['username']); ?></a>
                                        <span class="tweet-handle">@<?php echo strtolower(escape($rp['username'])); ?></span>
                                        <span class="tweet-dot">·</span>
                                        <span class="tweet-time"><?php echo formatDate($rp['created_at'], true); ?></span>
                                    </div>
                                    <div class="tweet-body">
                                        <p class="tweet-text">
                                            📍 <a href="venue-detail?id=<?php echo $rp['venue_id']; ?>" class="tweet-venue"><?php echo escape($rp['venue_name']); ?></a> mekanında check-in yaptı
                                        </p>
                                        <?php if (!empty($rp['note'])): ?>
                                            <p class="tweet-note">"<?php echo escape($rp['note']); ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="tweet-actions">
                                        <button class="tweet-action comment-btn" data-checkin-id="<?php echo $rp['id']; ?>">
                                            <span class="tweet-action-icon">💬</span>
                                            <span class="action-count"><?php echo $rp['comment_count']; ?></span>
                                        </button>
                                        <button class="tweet-action repost-btn active" data-checkin-id="<?php echo $rp['id']; ?>">
                                            <span class="tweet-action-icon">🔄</span>
                                            <span class="action-count"><?php echo $rp['repost_count']; ?></span>
                                        </button>
                                        <button class="tweet-action like-btn <?php echo $rp['user_liked'] ? 'active' : ''; ?>" data-checkin-id="<?php echo $rp['id']; ?>">
                                            <span class="tweet-action-icon"><?php echo $rp['user_liked'] ? '❤️' : '🤍'; ?></span>
                                            <span class="action-count"><?php echo $rp['like_count']; ?></span>
                                        </button>
                                        <button class="tweet-action share-btn" data-checkin-id="<?php echo $rp['id']; ?>">
                                            <span class="tweet-action-icon">📤</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>

        <!-- Right Sponsor Sidebar -->
        <!-- Right Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-right.php'; ?>

    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-sponsor">
            <?php if (!empty($footerAds)): ?>
                <?php $fAd = $footerAds[0]; ?>
                <a href="<?php echo escape($fAd['link_url'] ?: '#'); ?>" target="_blank" style="display: block; text-align: center;">
                    <img src="<?php echo BASE_URL . '/' . escape($fAd['image_url']); ?>" alt="<?php echo escape($fAd['title']); ?>" style="max-width: 100%; max-height: 120px; border-radius: 8px;">
                </a>
            <?php else: ?>
                <div class="footer-sponsor-placeholder" style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-align: center;">
                    <span style="font-size: 1.5rem;">📢</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="footer-content">
            <div class="footer-about">
                <h3>Sociaera</h3>
                <p>Sociaera, sosyal keşif ve check-in platformudur. Favori mekanlarınızda anlarınızı paylaşın.</p>
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
                    <li><a href="logout">Çıkış Yap</a></li>
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
                    <textarea id="commentInput" class="comment-input" placeholder="Yorum yaz..." maxlength="500"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">Gönder</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tab Switching Script -->
    <script>
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active from all tabs
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            // Add active to clicked tab
            this.classList.add('active');
            
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            // Show target tab content
            const tabId = 'tab-' + this.dataset.tab;
            document.getElementById(tabId).style.display = 'block';
        });
    });
    
    // Follow button
    const followBtn = document.getElementById('followBtn');
    if (followBtn) {
        followBtn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            const btn = this;
            
            btn.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('user_id', userId);
                
                const response = await fetch('api/interactions.php?action=follow', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    if (data.following) {
                        btn.textContent = 'Takip Ediliyor';
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-secondary');
                    } else {
                        btn.textContent = 'Takip Et';
                        btn.classList.remove('btn-secondary');
                        btn.classList.add('btn-primary');
                    }
                    // Takipçi sayısını güncelle
                    document.getElementById('followerCount').textContent = data.follower_count;
                } else {
                    alert(data.error || 'İşlem başarısız.');
                }
            } catch (error) {
                console.error('Follow error:', error);
                alert('Bağlantı hatası.');
            }
            
            btn.disabled = false;
        });
    }
    </script>

    <!-- Delete Post Script -->
    <script>
    document.querySelectorAll('.tweet-delete-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Bu postu silmek istediğinize emin misiniz?')) {
                return;
            }
            
            const checkinId = this.dataset.checkinId;
            const card = this.closest('.checkin-tweet');
            
            try {
                const formData = new FormData();
                formData.append('checkin_id', checkinId);
                
                const response = await fetch('api/interactions.php?action=delete', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
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

    // Like functionality
    document.querySelectorAll('.like-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const checkinId = this.dataset.checkinId;
            const iconSpan = this.querySelector('.tweet-action-icon');
            const countSpan = this.querySelector('.action-count');
            const card = this.closest('.checkin-tweet');
            const isInLikesTab = card.closest('#tab-likes') !== null;
            
            try {
                const formData = new FormData();
                formData.append('checkin_id', checkinId);
                
                const response = await fetch('api/interactions.php?action=like', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    // Tüm aynı checkin_id'li like butonlarını güncelle
                    document.querySelectorAll(`.like-btn[data-checkin-id="${checkinId}"]`).forEach(b => {
                        const icon = b.querySelector('.tweet-action-icon');
                        const count = b.querySelector('.action-count');
                        count.textContent = data.count;
                        if (data.liked) {
                            b.classList.add('active');
                            icon.textContent = '❤️';
                        } else {
                            b.classList.remove('active');
                            icon.textContent = '🤍';
                        }
                    });
                    
                    // Beğenilenler tabındaysa ve beğeni kaldırıldıysa, kartı kaldır
                    if (isInLikesTab && !data.liked) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateX(-20px)';
                        setTimeout(() => card.remove(), 300);
                    }
                }
            } catch (error) {
                console.error('Like error:', error);
            }
        });
    });

    // Repost functionality
    document.querySelectorAll('.repost-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const checkinId = this.dataset.checkinId;
            const countSpan = this.querySelector('.action-count');
            const card = this.closest('.checkin-tweet');
            const isInRepostsTab = card.closest('#tab-reposts') !== null;
            
            try {
                const formData = new FormData();
                formData.append('checkin_id', checkinId);
                
                const response = await fetch('api/interactions.php?action=repost', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    // Tüm aynı checkin_id'li repost butonlarını güncelle
                    document.querySelectorAll(`.repost-btn[data-checkin-id="${checkinId}"]`).forEach(b => {
                        const count = b.querySelector('.action-count');
                        count.textContent = data.count;
                        if (data.reposted) {
                            b.classList.add('active');
                        } else {
                            b.classList.remove('active');
                        }
                    });
                    
                    if (data.reposted) {
                        // Repost animasyonu
                        card.style.transition = 'all 0.3s ease';
                        card.style.transform = 'scale(1.02)';
                        card.style.boxShadow = '0 0 20px rgba(34, 197, 94, 0.3)';
                        setTimeout(() => {
                            card.style.transform = '';
                            card.style.boxShadow = '';
                        }, 500);
                    } else {
                        // Repostlar tabındaysa ve repost kaldırıldıysa, kartı kaldır
                        if (isInRepostsTab) {
                            card.style.transition = 'all 0.3s ease';
                            card.style.opacity = '0';
                            card.style.transform = 'translateX(-20px)';
                            setTimeout(() => card.remove(), 300);
                        }
                    }
                }
            } catch (error) {
                console.error('Repost error:', error);
            }
        });
    });

    // Comment functionality
    const commentModal = document.getElementById('commentModal');
    const closeCommentModal = document.getElementById('closeCommentModal');
    const commentsList = document.getElementById('commentsList');
    const commentForm = document.getElementById('commentForm');
    const commentCheckinId = document.getElementById('commentCheckinId');
    const commentInput = document.getElementById('commentInput');

    document.querySelectorAll('.comment-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const checkinId = this.dataset.checkinId;
            commentCheckinId.value = checkinId;
            commentModal.style.display = 'flex';
            
            // Load comments
            commentsList.innerHTML = '<p class="loading-comments">Yorumlar yükleniyor...</p>';
            try {
                const response = await fetch(`api/interactions.php?action=get_comments&checkin_id=${checkinId}`);
                const data = await response.json();
                
                if (data.success && data.comments.length > 0) {
                    commentsList.innerHTML = data.comments.map(c => `
                        <div class="comment-item">
                            <div class="comment-avatar">${c.username.charAt(0).toUpperCase()}</div>
                            <div class="comment-body">
                                <div class="comment-header">
                                    <span class="comment-username">${c.username}</span>
                                    <span class="comment-time">${c.created_at}</span>
                                </div>
                                <p class="comment-text">${c.content}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    commentsList.innerHTML = '<p class="no-comments">Henüz yorum yok. İlk yorumu sen yap!</p>';
                }
            } catch (error) {
                commentsList.innerHTML = '<p class="error-comments">Yorumlar yüklenemedi.</p>';
            }
        });
    });

    closeCommentModal.addEventListener('click', () => {
        commentModal.style.display = 'none';
    });

    commentModal.addEventListener('click', (e) => {
        if (e.target === commentModal) {
            commentModal.style.display = 'none';
        }
    });

    commentForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const checkinId = commentCheckinId.value;
        const content = commentInput.value.trim();
        
        if (!content) return;
        
        try {
            const formData = new FormData();
            formData.append('checkin_id', checkinId);
            formData.append('content', content);
            
            const response = await fetch('api/interactions.php?action=comment', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                commentInput.value = '';
                // Reload comments
                document.querySelector(`.comment-btn[data-checkin-id="${checkinId}"]`).click();
                // Update count
                const countSpan = document.querySelector(`.comment-btn[data-checkin-id="${checkinId}"] .action-count`);
                if (countSpan) {
                    countSpan.textContent = parseInt(countSpan.textContent) + 1;
                }
            }
        } catch (error) {
            console.error('Comment error:', error);
        }
    });
    </script>

</body>
</html>

