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

$userId = $_SESSION['user_id'];
$content = trim($_POST['content'] ?? '');
$venueId = intval($_POST['venue_id'] ?? 0);

// Image upload handling
$imageName = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($_FILES['image']['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Sadece JPG, PNG, GIF veya WebP dosyaları yüklenebilir']);
        exit;
    }
    
    if ($_FILES['image']['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'Dosya boyutu 5MB\'dan küçük olmalıdır']);
        exit;
    }
    
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $imageName = $userId . '_' . time() . '_' . uniqid() . '.' . strtolower($ext);
    $uploadPath = __DIR__ . '/../uploads/posts/' . $imageName;
    
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
        echo json_encode(['success' => false, 'error' => 'Fotoğraf yüklenemedi']);
        exit;
    }
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
    $cooldownSeconds = (int)getSetting('checkin_cooldown_seconds', 300);
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

