<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Public page
$userId = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';

$pageTitle = 'Üyeler';

$db = Database::getInstance()->getConnection();

// Arama parametresi
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

// Tüm üyeleri çek (kendisi hariç)
if ($searchQuery) {
    $usersStmt = $db->prepare("
        SELECT u.id, u.username, u.avatar, u.created_at,
               (SELECT COUNT(*) FROM checkins WHERE user_id = u.id) as checkin_count,
               (SELECT COUNT(*) FROM user_follows WHERE following_id = u.id) as follower_count,
               (SELECT COUNT(*) FROM user_follows WHERE follower_id = u.id) as following_count
        FROM users u
        WHERE u.id != ? AND u.username LIKE ?
        ORDER BY follower_count DESC, checkin_count DESC
    ");
    $usersStmt->execute([$userId, "%$searchQuery%"]);
} else {
    $usersStmt = $db->prepare("
        SELECT u.id, u.username, u.avatar, u.created_at,
               (SELECT COUNT(*) FROM checkins WHERE user_id = u.id) as checkin_count,
               (SELECT COUNT(*) FROM user_follows WHERE following_id = u.id) as follower_count,
               (SELECT COUNT(*) FROM user_follows WHERE follower_id = u.id) as following_count
        FROM users u
        WHERE u.id != ?
        ORDER BY follower_count DESC, checkin_count DESC
    ");
    $usersStmt->execute([$userId]);
}
$users = $usersStmt->fetchAll();

// Takip edilen kullanıcıların ID'lerini al
$followingIds = [];
if ($userId > 0) {
    $followingStmt = $db->prepare("SELECT following_id FROM user_follows WHERE follower_id = ?");
    $followingStmt->execute([$userId]);
    $followingIds = array_column($followingStmt->fetchAll(), 'following_id');
}

require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Uptag Üyeleri - Topluluktan yeni insanlar keşfet">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
    <style>
        /* Members Page Specific Styles */
        .members-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .members-header {
            margin-bottom: 24px;
        }

        .members-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
        }

        .members-search {
            position: relative;
        }

        .members-search input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 1px solid var(--border-color);
            border-radius: 9999px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .members-search input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .members-search input::placeholder {
            color: var(--text-subtle);
        }

        .members-search .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.1rem;
        }

        .members-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        @media (max-width: 1200px) {
            .members-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .members-grid {
                grid-template-columns: 1fr;
            }
        }

        .member-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 12px;
            padding: 24px 20px;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .member-card:hover {
            border-color: var(--accent-primary);
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        .member-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .member-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-info {
            flex: 1;
            min-width: 0;
            width: 100%;
        }

        .member-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .member-username {
            font-size: 0.875rem;
            color: var(--text-subtle);
            margin-bottom: 12px;
        }

        .member-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            font-size: 0.8rem;
        }

        .member-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            color: var(--text-subtle);
        }

        .member-stat-value {
            font-weight: 700;
            font-size: 1rem;
            color: var(--text-primary);
        }

        .member-actions {
            width: 100%;
            margin-top: 8px;
        }

        .member-actions .follow-btn {
            width: 100%;
        }

        .follow-btn {
            padding: 8px 20px;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .follow-btn.follow {
            background: var(--accent-primary);
            color: white;
        }

        .follow-btn.follow:hover {
            background: var(--accent-hover);
            transform: scale(1.05);
        }

        .follow-btn.following {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .follow-btn.following:hover {
            border-color: #ef4444;
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .members-empty {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-subtle);
        }

        .members-empty .empty-icon {
            font-size: 3rem;
            margin-bottom: 16px;
        }

        .members-empty h3 {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .members-count {
            font-size: 0.875rem;
            color: var(--text-subtle);
            margin-bottom: 24px;
        }

        /* View Profile Link */
        .member-name-link {
            color: inherit;
            text-decoration: none;
        }

        .member-name-link:hover {
            color: var(--accent-primary);
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'members'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content members-page">
            
            <div class="members-header">
                <h1>👥 Üyeler</h1>
                <p class="members-count"><?php echo count($users); ?> üye bulundu</p>
                
                <form class="members-search" method="GET" action="">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="q" placeholder="Kullanıcı ara..." value="<?php echo escape($searchQuery); ?>">
                </form>
            </div>

            <?php if (empty($users)): ?>
                <div class="members-empty">
                    <div class="empty-icon">👥</div>
                    <h3>Üye bulunamadı</h3>
                    <p>Arama kriterlerinize uygun üye yok</p>
                </div>
            <?php else: ?>
                <div class="members-grid">
                    <?php foreach ($users as $user): 
                        $isFollowing = in_array($user['id'], $followingIds);
                        $avatarUrl = $user['avatar'] ? BASE_URL . '/uploads/avatars/' . $user['avatar'] : null;
                    ?>
                        <div class="member-card">
                            <a href="profile.php?id=<?php echo $user['id']; ?>" class="member-avatar">
                                <?php if ($avatarUrl): ?>
                                    <img src="<?php echo $avatarUrl; ?>" alt="<?php echo escape($user['username']); ?>">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                <?php endif; ?>
                            </a>
                            
                            <div class="member-info">
                                <a href="profile.php?id=<?php echo $user['id']; ?>" class="member-name-link">
                                    <div class="member-name"><?php echo escape($user['username']); ?></div>
                                </a>
                                <div class="member-username">@<?php echo strtolower(escape($user['username'])); ?></div>
                                <div class="member-stats">
                                    <div class="member-stat">
                                        <span class="member-stat-value"><?php echo $user['follower_count']; ?></span>
                                        <span>Takipçi</span>
                                    </div>
                                    <div class="member-stat">
                                        <span class="member-stat-value"><?php echo $user['following_count']; ?></span>
                                        <span>Takip</span>
                                    </div>
                                    <div class="member-stat">
                                        <span class="member-stat-value"><?php echo $user['checkin_count']; ?></span>
                                        <span>Check-in</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="member-actions">
                                <?php if ($userId > 0): ?>
                                    <button class="follow-btn <?php echo $isFollowing ? 'following' : 'follow'; ?>" 
                                            data-user-id="<?php echo $user['id']; ?>">
                                        <?php echo $isFollowing ? 'Takip Ediliyor' : 'Takip Et'; ?>
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="follow-btn follow" style="display:block; text-align:center; text-decoration:none;">Takip Et</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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
                    <span style="color: var(--text-muted); font-size: 0.85rem;">📢 Footer Banner</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="footer-content">
            <div class="footer-about">
                <h3>Uptag</h3>
                <p>Uptag, sosyal keşif ve check-in platformudur. Favori mekanlarınızda anlarınızı paylaşın.</p>
            </div>
            <div class="footer-links">
                <h4>Keşfet</h4>
                <ul>
                    <li><a href="venues">Mekanlar</a></li>
                    <li><a href="leaderboard">Liderlik</a></li>
                    <li><a href="members">Üyeler</a></li>
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
            <p>&copy; <?php echo date('Y'); ?> Uptag. Tüm hakları saklıdır.</p>
        </div>
    </footer>

    <script>
    // Follow button functionality
    document.querySelectorAll('.follow-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const userId = this.dataset.userId;
            const button = this;
            
            button.disabled = true;
            
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
                        button.textContent = 'Takip Ediliyor';
                        button.classList.remove('follow');
                        button.classList.add('following');
                    } else {
                        button.textContent = 'Takip Et';
                        button.classList.remove('following');
                        button.classList.add('follow');
                    }
                    
                    // Update follower count in the card
                    const card = button.closest('.member-card');
                    const followerStat = card.querySelector('.member-stat:first-child .member-stat-value');
                    if (followerStat) {
                        followerStat.textContent = data.follower_count;
                    }
                } else {
                    alert(data.error || 'İşlem başarısız.');
                }
            } catch (error) {
                console.error('Follow error:', error);
                alert('Bağlantı hatası.');
            }
            
            button.disabled = false;
        });
    });
    </script>

</body>
</html>
