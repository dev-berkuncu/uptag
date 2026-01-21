<?php
/**
 * Create Post API - Twitter-style posting with venue tags and image upload
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Giriş yapmalısınız']);
    exit;
}

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF validation
requireCsrf();

// Güvenli resim yükleme sınıfı
require_once '../../includes/ImageUploader.php';

$userId = $_SESSION['user_id'];
$content = trim($_POST['content'] ?? '');
$venueId = intval($_POST['venue_id'] ?? 0);

// Image upload handling with secure ImageUploader
$imageName = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploader = new ImageUploader();
    $result = $uploader->upload($_FILES['image'], 'posts', [
        'maxSize' => 5 * 1024 * 1024,
        'outputFormat' => 'webp',
        'quality' => 85,
        'maxWidth' => 1200,
        'maxHeight' => 1200
    ]);

    if (!$result['success']) {
        echo json_encode(['success' => false, 'error' => $result['error']]);
        exit;
    }

    $imageName = $result['filename'];
}

// Validate
if (empty($content) && $venueId === 0 && !$imageName) {
    echo json_encode(['success' => false, 'error' => 'Post içeriği, mekan veya fotoğraf gerekli']);
    exit;
}

if ($venueId === 0) {
    echo json_encode(['success' => false, 'error' => 'Lütfen bir mekan etiketleyin (@mekan)']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verify venue exists
    $venueStmt = $db->prepare("SELECT id, name FROM venues WHERE id = ? AND is_active = 1");
    $venueStmt->execute([$venueId]);
    $venue = $venueStmt->fetch();

    if (!$venue) {
        echo json_encode(['success' => false, 'error' => 'Mekan bulunamadı']);
        exit;
    }

    // Rate limiting check (optional)
    $cooldownSeconds = (int) getSetting('checkin_cooldown_seconds', 300);
    $lastCheckinStmt = $db->prepare("
        SELECT created_at FROM checkins 
        WHERE user_id = ? AND venue_id = ? 
        ORDER BY created_at DESC LIMIT 1
    ");
    $lastCheckinStmt->execute([$userId, $venueId]);
    $lastCheckin = $lastCheckinStmt->fetch();

    if ($lastCheckin) {
        $timeSince = time() - strtotime($lastCheckin['created_at']);
        if ($timeSince < $cooldownSeconds) {
            $remaining = $cooldownSeconds - $timeSince;
            echo json_encode([
                'success' => false,
                'error' => "Bu mekana tekrar check-in için {$remaining} saniye bekleyin"
            ]);
            exit;
        }
    }

    // Create the check-in (post) with image
    $insertStmt = $db->prepare("
        INSERT INTO checkins (user_id, venue_id, note, image, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $insertStmt->execute([$userId, $venueId, $content, $imageName]);
    $postId = $db->lastInsertId();

    // Parse @mentions and create notifications
    if (!empty($content)) {
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        if (!empty($matches[1])) {
            $mentionedTags = array_unique($matches[1]);
            foreach ($mentionedTags as $tag) {
                // Find user by tag or username
                $userStmt = $db->prepare("SELECT id FROM users WHERE (tag = ? OR LOWER(username) = LOWER(?)) AND id != ?");
                $userStmt->execute([$tag, $tag, $userId]);
                $mentionedUser = $userStmt->fetch();

                if ($mentionedUser) {
                    // Create notification
                    try {
                        $notifStmt = $db->prepare("
                            INSERT INTO notifications (user_id, type, from_user_id, checkin_id, content, created_at)
                            VALUES (?, 'mention', ?, ?, ?, NOW())
                        ");
                        $notifContent = $_SESSION['username'] . ' sizi bir gönderide etiketledi';
                        $notifStmt->execute([$mentionedUser['id'], $userId, $postId, $notifContent]);
                    } catch (PDOException $e) {
                        // Notification error should not break post creation
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Post paylaşıldı!',
        'post' => [
            'id' => $postId,
            'venue_name' => $venue['name'],
            'content' => $content,
            'image' => $imageName ? 'uploads/posts/' . $imageName : null
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Bir hata oluştu']);
}

