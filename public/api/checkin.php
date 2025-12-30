<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Giriş yapmalısınız.']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'search') {
    // @ mention için mekan arama
    $query = trim($_GET['q'] ?? '');
    
    if (strlen($query) < 1) {
        echo json_encode([]);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, name, address 
        FROM venues 
        WHERE is_active = 1 AND name LIKE ?
        ORDER BY name ASC
        LIMIT 10
    ");
    $stmt->execute(['%' . $query . '%']);
    $venues = $stmt->fetchAll();
    
    echo json_encode($venues);
    exit;
}

if ($action === 'post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Yeni ment oluştur
    $content = trim($_POST['content'] ?? '');
    $venueId = (int)($_POST['venue_id'] ?? 0);
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Ment içeriği boş olamaz.']);
        exit;
    }
    
    if ($venueId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Bir mekan etiketlemelisiniz (@mekanadi).']);
        exit;
    }
    
    // Mekan kontrolü
    $venue = new Venue();
    $venueData = $venue->getVenueById($venueId);
    
    if (!$venueData || !$venueData['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Mekan bulunamadı.']);
        exit;
    }
    
    // Cooldown kontrolü
    $db = Database::getInstance()->getConnection();
    $cooldownSeconds = (int)getSetting('checkin_cooldown_seconds', 300);
    
    $cooldownStmt = $db->prepare("
        SELECT created_at FROM checkins 
        WHERE user_id = ? AND venue_id = ?
        ORDER BY created_at DESC LIMIT 1
    ");
    $cooldownStmt->execute([$_SESSION['user_id'], $venueId]);
    $lastCheckin = $cooldownStmt->fetch();
    
    if ($lastCheckin) {
        $lastCheckinTime = strtotime($lastCheckin['created_at']);
        $timeSince = time() - $lastCheckinTime;
        
        if ($timeSince < $cooldownSeconds) {
            $remaining = ceil(($cooldownSeconds - $timeSince) / 60);
            echo json_encode(['success' => false, 'message' => "Bu mekana tekrar ment atmak için $remaining dakika beklemelisiniz."]);
            exit;
        }
    }
    
    // Check-in (ment) oluştur
    $stmt = $db->prepare("INSERT INTO checkins (user_id, venue_id, note) VALUES (?, ?, ?)");
    
    if ($stmt->execute([$_SESSION['user_id'], $venueId, $content])) {
        echo json_encode(['success' => true, 'message' => 'Ment başarıyla paylaşıldı!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);

