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
    
    // Fetch post
    $stmt = $db->prepare("
        SELECT c.*, 
               u.username, u.avatar as user_avatar, u.tag as user_tag,
               v.name as venue_name, v.id as venue_id,
               (SELECT COUNT(*) FROM post_likes WHERE checkin_id = c.id) as like_count,
               (SELECT COUNT(*) FROM post_reposts WHERE checkin_id = c.id) as repost_count,
               (SELECT COUNT(*) FROM post_comments WHERE checkin_id = c.id) as comment_count
        FROM checkins c
        JOIN users u ON c.user_id = u.id
        JOIN venues v ON c.venue_id = v.id
        WHERE c.id = ?
    ");
    $stmt->execute([$postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post bulunamadı']);
        exit;
    }
    
    // Format date
    $post['created_at'] = formatDate($post['created_at'], true);
    
    // Fetch comments
    $commentsStmt = $db->prepare("
        SELECT pc.id, pc.comment, pc.created_at, pc.user_id,
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
    echo json_encode(['success' => false, 'error' => 'Bir hata oluştu']);
}
