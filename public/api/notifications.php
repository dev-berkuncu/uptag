<?php
/**
 * Notifications API
 * Bildirim işlemleri
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Giriş yapmalısınız.']);
    exit;
}

$userId = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? '';

// Okunmamış bildirim sayısı
if ($action === 'count') {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $count = $stmt->fetch()['count'];
        echo json_encode(['success' => true, 'count' => (int) $count]);
    } catch (PDOException $e) {
        echo json_encode(['success' => true, 'count' => 0]);
    }
    exit;
}

// POST isteklerinde CSRF doğrulama
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
}

// Bildirimi okundu işaretle
if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = (int) ($_POST['notification_id'] ?? 0);

    if ($notificationId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz bildirim.']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Hata oluştu.']);
    }
    exit;
}

// Tüm bildirimleri okundu işaretle
if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Hata oluştu.']);
    }
    exit;
}

// Tüm bildirimleri sil
if ($action === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'Tüm bildirimler silindi.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Hata oluştu.']);
    }
    exit;
}

// Bildirimleri listele
if ($action === 'list' || $action === '') {
    $limit = (int) ($_GET['limit'] ?? 20);
    $offset = (int) ($_GET['offset'] ?? 0);

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
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Avatar URL'leri düzenle
        foreach ($notifications as &$notif) {
            if (!empty($notif['from_avatar'])) {
                $notif['from_avatar_url'] = 'uploads/avatars/' . $notif['from_avatar'];
            } else {
                $notif['from_avatar_url'] = null;
            }
        }

        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Bildirimler yüklenemedi.']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Geçersiz işlem.']);
