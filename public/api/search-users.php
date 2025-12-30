<?php
/**
 * Kullanıcı Arama API
 * @ mention için kullanıcı arama
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Giriş yapmalısınız.']);
    exit;
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 1) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];

try {
    // Kullanıcı adı veya tag'e göre ara (kendisi hariç)
    $searchTerm = '%' . $query . '%';
    $stmt = $db->prepare("
        SELECT id, username, tag, avatar 
        FROM users 
        WHERE (username LIKE ? OR tag LIKE ?) 
        AND id != ? 
        AND is_active = 1
        ORDER BY 
            CASE 
                WHEN tag = ? THEN 1
                WHEN username = ? THEN 2
                WHEN tag LIKE ? THEN 3
                WHEN username LIKE ? THEN 4
                ELSE 5
            END,
            username ASC
        LIMIT 8
    ");
    
    $exactTerm = $query;
    $startTerm = $query . '%';
    $stmt->execute([$searchTerm, $searchTerm, $userId, $exactTerm, $exactTerm, $startTerm, $startTerm]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Avatar URL'lerini düzenle
    foreach ($users as &$user) {
        if (!empty($user['avatar'])) {
            $user['avatar_url'] = 'uploads/avatars/' . $user['avatar'];
        } else {
            $user['avatar_url'] = null;
        }
        // Tag yoksa username'i kullan
        if (empty($user['tag'])) {
            $user['tag'] = strtolower($user['username']);
        }
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Arama yapılamadı.']);
}
