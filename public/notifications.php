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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
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
                    <h1>🔔 Bildirimler</h1>
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
                            <div class="notification-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notif['id']; ?>">
                                <div class="notification-avatar">
                                    <?php if (!empty($notif['from_avatar'])): ?>
                                        <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo escape($notif['from_avatar']); ?>" alt="">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($notif['from_username'] ?? '?', 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-text">
                                        <?php if ($notif['type'] === 'mention'): ?>
                                            <a href="profile?id=<?php echo $notif['from_user_id']; ?>" class="notification-username">
                                                <?php echo escape($notif['from_username']); ?>
                                            </a>
                                            sizi bir gönderide etiketledi
                                        <?php elseif ($notif['type'] === 'like'): ?>
                                            <a href="profile?id=<?php echo $notif['from_user_id']; ?>" class="notification-username">
                                                <?php echo escape($notif['from_username']); ?>
                                            </a>
                                            gönderinizi beğendi
                                        <?php elseif ($notif['type'] === 'comment'): ?>
                                            <a href="profile?id=<?php echo $notif['from_user_id']; ?>" class="notification-username">
                                                <?php echo escape($notif['from_username']); ?>
                                            </a>
                                            gönderinize yorum yaptı
                                        <?php elseif ($notif['type'] === 'follow'): ?>
                                            <a href="profile?id=<?php echo $notif['from_user_id']; ?>" class="notification-username">
                                                <?php echo escape($notif['from_username']); ?>
                                            </a>
                                            sizi takip etmeye başladı
                                        <?php elseif ($notif['type'] === 'repost'): ?>
                                            <a href="profile?id=<?php echo $notif['from_user_id']; ?>" class="notification-username">
                                                <?php echo escape($notif['from_username']); ?>
                                            </a>
                                            gönderinizi repostladı
                                        <?php else: ?>
                                            <?php echo escape($notif['content']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-time">
                                        <?php echo formatDate($notif['created_at'], true); ?>
                                    </div>
                                </div>
                                <?php if ($notif['from_user_id']): ?>
                                <a href="profile?id=<?php echo $notif['from_user_id']; ?>" class="notification-action">
                                    Görüntüle →
                                </a>
                                <?php endif; ?>
                            </div>
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
