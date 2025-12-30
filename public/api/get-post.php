<?php
/**
 * Get Post API - Returns post data for modal display
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$postId = intval($_GET['id'] ?? 0);

if ($postId === 0) {
    echo json_encode(['success' => false, 'error' => 'Post ID gerekli']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // First check if the checkin exists at all
    $checkStmt = $db->prepare("SELECT id, venue_id, user_id FROM checkins WHERE id = ?");
    $checkStmt->execute([$postId]);
    $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$checkResult) {
        echo json_encode(['success' => false, 'error' => 'Post bulunamadı (ID: ' . $postId . ')']);
        exit;
    }
    
    // Fetch post with LEFT JOIN to handle missing venue/user
    $stmt = $db->prepare("
        SELECT c.*, 
               COALESCE(u.username, 'Deleted User') as username, 
               u.avatar as user_avatar, 
               u.tag as user_tag,
               COALESCE(v.name, 'Unknown Venue') as venue_name, 
               COALESCE(v.id, 0) as venue_id,
               (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
               (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
               (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count
        FROM checkins c
        LEFT JOIN users u ON c.user_id = u.id
        LEFT JOIN venues v ON c.venue_id = v.id
        WHERE c.id = ?
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post verisi alınamadı']);
        exit;
    }
    
    // Format date
    $post['created_at'] = formatDate($post['created_at'], true);
    
    // Fetch comments
    $commentsStmt = $db->prepare("
        SELECT pc.id, pc.content as comment, pc.created_at, pc.user_id,
               u.username, u.avatar, u.tag
        FROM post_comments pc
        JOIN users u ON pc.user_id = u.id
        WHERE pc.checkin_id = ?
        ORDER BY pc.created_at ASC
        LIMIT 20
    ");
    $commentsStmt->execute([$postId]);
    $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'post' => $post,
        'comments' => $comments
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB Error: ' . $e->getMessage()]);
}
