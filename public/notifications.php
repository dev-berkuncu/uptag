<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();
require_once '../includes/ads_logic.php';

$pageTitle = 'Bildirimler';
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$db = Database::getInstance()->getConnection();

// Bildirimleri getir
$notifications = [];
try {
    $stmt = $db->prepare("
        SELECT n.*, 
               u.username as from_username, 
               u.avatar as from_avatar,
               u.tag as from_tag
        FROM notifications n
        LEFT JOIN users u ON n.from_user_id = u.id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tüm bildirimleri okundu işaretle
    $markRead = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $markRead->execute([$userId]);
} catch (PDOException $e) {
    $notifications = [];
}

// Avatar URL
$avatarUrl = null;
try {
    $userStmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userInfo = $userStmt->fetch();
    if (!empty($userInfo['avatar'])) {
        $avatarUrl = 'uploads/avatars/' . $userInfo['avatar'];
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bildirimleriniz">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'notifications'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content notifications-page">
            
            <div class="notifications-container">
                <div class="notifications-header">
                    <div>
                        <h1>Bildirimler</h1>
                        <div class="notifications-filter">Seni etikettiğin</div>
                    </div>
                </div>

                <?php if (empty($notifications)): ?>
                    <div class="notifications-empty">
                        <div class="empty-icon">🔕</div>
                        <h3>Henüz bildirim yok</h3>
                        <p>Birisi sizi etiketlediğinde burada göreceksiniz.</p>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <?php foreach ($notifications as $notif): ?>
                            <a href="<?php echo $notif['checkin_id'] ? 'feed?post=' . $notif['checkin_id'] : 'profile?id=' . $notif['from_user_id']; ?>" class="notification-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <div class="notification-icon-wrapper">
                                    <div class="notification-avatar">
                                        <?php if (!empty($notif['from_avatar'])): ?>
                                            <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo escape($notif['from_avatar']); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($notif['from_username'] ?? '?', 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-type-badge">
                                        <?php 
                                        $icons = ['mention' => '📣', 'like' => '❤️', 'comment' => '💬', 'follow' => '👤', 'repost' => '🔄'];
                                        echo $icons[$notif['type']] ?? '🔔'; 
                                        ?>
                                    </div>
                                </div>
                                <div class="notification-body">
                                    <div class="notification-text">
                                        <span class="notification-username"><?php echo escape($notif['from_username']); ?></span>
                                        <?php 
                                        $actionText = 'bir işlem yaptı';
                                        if ($notif['type'] === 'mention') $actionText = 'sizi etiketledi';
                                        elseif ($notif['type'] === 'like') $actionText = 'gönderinizi beğendi';
                                        elseif ($notif['type'] === 'comment') $actionText = 'yorum yaptı';
                                        elseif ($notif['type'] === 'follow') $actionText = 'sizi takip etti';
                                        elseif ($notif['type'] === 'repost') $actionText = 'repostladı';
                                        echo $actionText;
                                        ?>
                                    </div>
                                    <div class="notification-preview-text">
                                        <?php echo escape($notif['content'] ?? $actionText); ?>
                                    </div>
                                </div>
                                <div class="notification-meta">
                                    <span class="notification-time"><?php echo formatDate($notif['created_at'], true); ?></span>
                                    <span class="notification-dot"></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        </main>

        <!-- Right Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-right.php'; ?>

    </div>

    <!-- FOOTER -->
    <footer class="footer footer-minimal">
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Uptag. Tüm hakları saklıdır.</p>
        </div>
    </footer>

</body>
</html>
