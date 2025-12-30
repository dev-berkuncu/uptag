<?php
/**
 * Post Etkileşimleri API
 * Beğeni, Repost ve Yorum işlemleri
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Giriş yapmalısınız.']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$db = Database::getInstance()->getConnection();

// Debug modunda parametreleri göster
if (isset($_GET['debug'])) {
    echo json_encode(['debug' => true, 'action' => $action, 'GET' => $_GET, 'POST' => $_POST, 'user_id' => $userId]);
    exit;
}

// Beğeni toggle
if ($action === 'like' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkinId = (int)($_POST['checkin_id'] ?? 0);
    $repostId = !empty($_POST['repost_id']) ? (int)$_POST['repost_id'] : null;
    
    if ($checkinId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz post.', 'checkin_id_received' => $_POST['checkin_id'] ?? 'empty']);
        exit;
    }
    
    try {
        // Mevcut beğeni var mı kontrol et (repost_id dahil)
        if ($repostId) {
            $checkStmt = $db->prepare("SELECT id FROM post_likes WHERE user_id = ? AND checkin_id = ? AND repost_id = ?");
            $checkStmt->execute([$userId, $checkinId, $repostId]);
        } else {
            $checkStmt = $db->prepare("SELECT id FROM post_likes WHERE user_id = ? AND checkin_id = ? AND repost_id IS NULL");
            $checkStmt->execute([$userId, $checkinId]);
        }
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Beğeniyi kaldır
            if ($repostId) {
                $deleteStmt = $db->prepare("DELETE FROM post_likes WHERE user_id = ? AND checkin_id = ? AND repost_id = ?");
                $deleteStmt->execute([$userId, $checkinId, $repostId]);
            } else {
                $deleteStmt = $db->prepare("DELETE FROM post_likes WHERE user_id = ? AND checkin_id = ? AND repost_id IS NULL");
                $deleteStmt->execute([$userId, $checkinId]);
            }
            $liked = false;
        } else {
            // Beğeni ekle
            $insertStmt = $db->prepare("INSERT INTO post_likes (user_id, checkin_id, repost_id) VALUES (?, ?, ?)");
            $insertStmt->execute([$userId, $checkinId, $repostId]);
            $liked = true;
            
            // Bildirim gönder (kendi postunu beğendiyse göndermez)
            $ownerStmt = $db->prepare("SELECT user_id FROM checkins WHERE id = ?");
            $ownerStmt->execute([$checkinId]);
            $owner = $ownerStmt->fetch();
            if ($owner && $owner['user_id'] != $userId) {
                $notifStmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, checkin_id) VALUES (?, ?, 'like', ?)");
                $notifStmt->execute([$owner['user_id'], $userId, $checkinId]);
            }
        }
        
        // Yeni sayıyı getir (repost için veya orijinal post için)
        if ($repostId) {
            $countStmt = $db->prepare("SELECT COUNT(*) as count FROM post_likes WHERE repost_id = ?");
            $countStmt->execute([$repostId]);
        } else {
            $countStmt = $db->prepare("SELECT COUNT(*) as count FROM post_likes WHERE checkin_id = ? AND repost_id IS NULL");
            $countStmt->execute([$checkinId]);
        }
        $count = $countStmt->fetch()['count'];
        
        echo json_encode(['success' => true, 'liked' => $liked, 'count' => (int)$count]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'DB Error: ' . $e->getMessage()]);
    }
    exit;
}

// Repost (Toggle - bir kez repost yapılabilir)
if ($action === 'repost' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkinId = (int)($_POST['checkin_id'] ?? 0);
    $quote = trim($_POST['quote'] ?? '');
    
    if ($checkinId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz post.']);
        exit;
    }
    
    // Orijinal postu kontrol et
    $ownerStmt = $db->prepare("SELECT user_id FROM checkins WHERE id = ?");
    $ownerStmt->execute([$checkinId]);
    $owner = $ownerStmt->fetch();
    
    if (!$owner) {
        echo json_encode(['success' => false, 'error' => 'Post bulunamadı.']);
        exit;
    }
    
    // Kendi postunu repost edemez
    if ($owner['user_id'] == $userId) {
        echo json_encode(['success' => false, 'error' => 'Kendi postunuzu repost edemezsiniz.']);
        exit;
    }
    
    // Daha önce repost yapmış mı kontrol et
    $checkStmt = $db->prepare("SELECT id FROM post_reposts WHERE user_id = ? AND checkin_id = ?");
    $checkStmt->execute([$userId, $checkinId]);
    $existingRepost = $checkStmt->fetch();
    
    if ($existingRepost) {
        // Repost'u kaldır (toggle)
        $deleteStmt = $db->prepare("DELETE FROM post_reposts WHERE user_id = ? AND checkin_id = ?");
        $deleteStmt->execute([$userId, $checkinId]);
        
        // Yeni sayıyı getir
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM post_reposts WHERE checkin_id = ?");
        $countStmt->execute([$checkinId]);
        $count = $countStmt->fetch()['count'];
        
        echo json_encode([
            'success' => true, 
            'reposted' => false,
            'count' => (int)$count,
            'message' => 'Repost kaldırıldı.'
        ]);
        exit;
    }
    
    // Quote text max 500 karakter
    if (strlen($quote) > 500) {
        echo json_encode(['success' => false, 'error' => 'Alıntı metni çok uzun (max 500 karakter).']);
        exit;
    }
    
    try {
        // Quote kolonu var mı kontrol et
        $hasQuoteColumn = false;
        try {
            $checkCol = $db->query("SHOW COLUMNS FROM post_reposts LIKE 'quote'");
            $hasQuoteColumn = $checkCol->rowCount() > 0;
        } catch (Exception $e) {
            $hasQuoteColumn = false;
        }
        
        if ($hasQuoteColumn) {
            // Quote destekli insert
            $insertStmt = $db->prepare("INSERT INTO post_reposts (user_id, checkin_id, quote) VALUES (?, ?, ?)");
            $insertStmt->execute([$userId, $checkinId, $quote ?: null]);
        } else {
            // Quote olmadan insert (eski tablo yapısı)
            $insertStmt = $db->prepare("INSERT INTO post_reposts (user_id, checkin_id) VALUES (?, ?)");
            $insertStmt->execute([$userId, $checkinId]);
        }
        $repostId = $db->lastInsertId();
        
        // Bildirim gönder (post sahibine)
        if ($owner['user_id'] != $userId) {
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, from_user_id, type, checkin_id) VALUES (?, ?, 'repost', ?)");
            $notifStmt->execute([$owner['user_id'], $userId, $checkinId]);
        }
        
        // Yeni sayıyı getir
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM post_reposts WHERE checkin_id = ?");
        $countStmt->execute([$checkinId]);
        $count = $countStmt->fetch()['count'];
        
        echo json_encode([
            'success' => true, 
            'reposted' => true, 
            'repost_id' => (int)$repostId,
            'count' => (int)$count,
            'message' => 'Repost başarıyla oluşturuldu!'
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Repost oluşturulamadı: ' . $e->getMessage()]);
    }
    exit;
}

// Yorum ekle
if ($action === 'comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkinId = (int)($_POST['checkin_id'] ?? 0);
    $repostId = !empty($_POST['repost_id']) ? (int)$_POST['repost_id'] : null;
    $content = trim($_POST['content'] ?? '');
    
    if ($checkinId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz post.']);
        exit;
    }
    
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
        $uploadPath = __DIR__ . '/../uploads/comments/' . $imageName;
        
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            echo json_encode(['success' => false, 'error' => 'Fotoğraf yüklenemedi']);
            exit;
        }
    }
    
    if (empty($content) && !$imageName) {
        echo json_encode(['success' => false, 'error' => 'Yorum veya fotoğraf gerekli.']);
        exit;
    }
    
    if (strlen($content) > 500) {
        echo json_encode(['success' => false, 'error' => 'Yorum çok uzun (max 500 karakter).']);
        exit;
    }
    
    // Yorum ekle
    $insertStmt = $db->prepare("INSERT INTO post_comments (user_id, checkin_id, repost_id, content, image) VALUES (?, ?, ?, ?, ?)");
    $insertStmt->execute([$userId, $checkinId, $repostId, $content, $imageName]);
    $commentId = $db->lastInsertId();
    
    // Yeni yorum bilgilerini getir
    $commentStmt = $db->prepare("
        SELECT c.*, u.username 
        FROM post_comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $commentStmt->execute([$commentId]);
    $comment = $commentStmt->fetch();
    
    // Yorum sayısını getir (repost için veya orijinal post için)
    if ($repostId) {
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM post_comments WHERE repost_id = ?");
        $countStmt->execute([$repostId]);
    } else {
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM post_comments WHERE checkin_id = ? AND repost_id IS NULL");
        $countStmt->execute([$checkinId]);
    }
    $count = $countStmt->fetch()['count'];
    
    echo json_encode([
        'success' => true, 
        'comment' => [
            'id' => $comment['id'],
            'content' => $comment['content'],
            'username' => $comment['username'],
            'created_at' => $comment['created_at'],
            'image' => $imageName ? 'uploads/comments/' . $imageName : null
        ],
        'count' => (int)$count
    ]);
    exit;
}

// Yorumları getir
if (($action === 'comments' || $action === 'get_comments') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $checkinId = (int)($_GET['checkin_id'] ?? 0);
    $repostId = !empty($_GET['repost_id']) ? (int)$_GET['repost_id'] : null;
    
    if ($checkinId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz post.']);
        exit;
    }
    
    if ($repostId) {
        $stmt = $db->prepare("
            SELECT c.*, u.username, u.id as user_id
            FROM post_comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.repost_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$repostId]);
    } else {
        $stmt = $db->prepare("
            SELECT c.*, u.username, u.id as user_id
            FROM post_comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.checkin_id = ? AND c.repost_id IS NULL
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$checkinId]);
    }
    $comments = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'comments' => $comments]);
    exit;
}

// Post etkileşim durumlarını getir (tek post için)
if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $checkinId = (int)($_GET['checkin_id'] ?? 0);
    
    if ($checkinId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz post.']);
        exit;
    }
    
    // Beğeni sayısı ve kullanıcı beğenmiş mi
    $likeCountStmt = $db->prepare("SELECT COUNT(*) as count FROM post_likes WHERE checkin_id = ?");
    $likeCountStmt->execute([$checkinId]);
    $likeCount = $likeCountStmt->fetch()['count'];
    
    $userLikedStmt = $db->prepare("SELECT id FROM post_likes WHERE user_id = ? AND checkin_id = ?");
    $userLikedStmt->execute([$userId, $checkinId]);
    $userLiked = (bool)$userLikedStmt->fetch();
    
    // Repost sayısı ve kullanıcı repost etmiş mi
    $repostCountStmt = $db->prepare("SELECT COUNT(*) as count FROM post_reposts WHERE checkin_id = ?");
    $repostCountStmt->execute([$checkinId]);
    $repostCount = $repostCountStmt->fetch()['count'];
    
    $userRepostedStmt = $db->prepare("SELECT id FROM post_reposts WHERE user_id = ? AND checkin_id = ?");
    $userRepostedStmt->execute([$userId, $checkinId]);
    $userReposted = (bool)$userRepostedStmt->fetch();
    
    // Yorum sayısı
    $commentCountStmt = $db->prepare("SELECT COUNT(*) as count FROM post_comments WHERE checkin_id = ?");
    $commentCountStmt->execute([$checkinId]);
    $commentCount = $commentCountStmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'likes' => ['count' => (int)$likeCount, 'liked' => $userLiked],
        'reposts' => ['count' => (int)$repostCount, 'reposted' => $userReposted],
        'comments' => ['count' => (int)$commentCount]
    ]);
    exit;
}
// Post sil (sadece sahibi silebilir)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkinId = (int)($_POST['checkin_id'] ?? 0);
    
    if ($checkinId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz post.']);
        exit;
    }
    
    // Post sahibi mi kontrol et
    $ownerStmt = $db->prepare("SELECT user_id FROM checkins WHERE id = ?");
    $ownerStmt->execute([$checkinId]);
    $owner = $ownerStmt->fetch();
    
    if (!$owner) {
        echo json_encode(['success' => false, 'error' => 'Post bulunamadı.']);
        exit;
    }
    
    if ($owner['user_id'] != $userId) {
        echo json_encode(['success' => false, 'error' => 'Bu postu silme yetkiniz yok.']);
        exit;
    }
    
    // İlişkili verileri sil (beğeniler, repostlar, yorumlar)
    try {
        $db->beginTransaction();
        
        $db->prepare("DELETE FROM post_likes WHERE checkin_id = ?")->execute([$checkinId]);
        $db->prepare("DELETE FROM post_reposts WHERE checkin_id = ?")->execute([$checkinId]);
        $db->prepare("DELETE FROM post_comments WHERE checkin_id = ?")->execute([$checkinId]);
        $db->prepare("DELETE FROM checkins WHERE id = ?")->execute([$checkinId]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Post silindi.']);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Silme işlemi başarısız.']);
    }
    exit;
}

// Takip et / Takipten çık (toggle)
if ($action === 'follow' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    
    if ($targetUserId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz kullanıcı.']);
        exit;
    }
    
    // Kendini takip edemez
    if ($targetUserId == $userId) {
        echo json_encode(['success' => false, 'error' => 'Kendinizi takip edemezsiniz.']);
        exit;
    }
    
    // Kullanıcı var mı kontrol et
    $userCheck = $db->prepare("SELECT id FROM users WHERE id = ?");
    $userCheck->execute([$targetUserId]);
    if (!$userCheck->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı.']);
        exit;
    }
    
    try {
        // Mevcut takip var mı?
        $checkStmt = $db->prepare("SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $checkStmt->execute([$userId, $targetUserId]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Takipten çık
            $deleteStmt = $db->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
            $deleteStmt->execute([$userId, $targetUserId]);
            $following = false;
        } else {
            // Takip et
            $insertStmt = $db->prepare("INSERT INTO user_follows (follower_id, following_id) VALUES (?, ?)");
            $insertStmt->execute([$userId, $targetUserId]);
            $following = true;
        }
        
        // Takipçi sayısını getir
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM user_follows WHERE following_id = ?");
        $countStmt->execute([$targetUserId]);
        $followerCount = $countStmt->fetch()['count'];
        
        // Takip edilen sayısını getir
        $followingStmt = $db->prepare("SELECT COUNT(*) as count FROM user_follows WHERE follower_id = ?");
        $followingStmt->execute([$targetUserId]);
        $followingCount = $followingStmt->fetch()['count'];
        
        echo json_encode([
            'success' => true,
            'following' => $following,
            'follower_count' => (int)$followerCount,
            'following_count' => (int)$followingCount,
            'message' => $following ? 'Takip edildi.' : 'Takipten çıkıldı.'
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'İşlem başarısız: ' . $e->getMessage()]);
    }
    exit;
}

// Takip durumu kontrolü
if ($action === 'check_follow' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $targetUserId = (int)($_GET['user_id'] ?? 0);
    
    if ($targetUserId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz kullanıcı.']);
        exit;
    }
    
    $checkStmt = $db->prepare("SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $checkStmt->execute([$userId, $targetUserId]);
    $isFollowing = $checkStmt->fetch() ? true : false;
    
    // Takipçi ve takip sayıları
    $followerStmt = $db->prepare("SELECT COUNT(*) as count FROM user_follows WHERE following_id = ?");
    $followerStmt->execute([$targetUserId]);
    $followerCount = $followerStmt->fetch()['count'];
    
    $followingStmt = $db->prepare("SELECT COUNT(*) as count FROM user_follows WHERE follower_id = ?");
    $followingStmt->execute([$targetUserId]);
    $followingCount = $followingStmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'following' => $isFollowing,
        'follower_count' => (int)$followerCount,
        'following_count' => (int)$followingCount
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Geçersiz istek.']);
