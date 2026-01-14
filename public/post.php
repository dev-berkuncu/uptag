<?php
/**
 * Post Detail Page - Shows a single post with its unique URL
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/ads_logic.php';

$pageTitle = 'Gönderi';
$db = Database::getInstance()->getConnection();

// Get post ID from URL
$postId = intval($_GET['id'] ?? 0);

if ($postId === 0) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

// Fetch the post with all details
try {
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
        header('Location: ' . BASE_URL . '/dashboard');
        exit;
    }
    
    $pageTitle = $post['username'] . ' - Gönderi';
    
    // Check if current user liked/reposted
    $userLiked = false;
    $userReposted = false;
    if (isLoggedIn()) {
        $userId = $_SESSION['user_id'];
        $likeCheck = $db->prepare("SELECT 1 FROM post_likes WHERE user_id = ? AND checkin_id = ?");
        $likeCheck->execute([$userId, $postId]);
        $userLiked = $likeCheck->fetch() ? true : false;
        
        $repostCheck = $db->prepare("SELECT 1 FROM post_reposts WHERE user_id = ? AND checkin_id = ?");
        $repostCheck->execute([$userId, $postId]);
        $userReposted = $repostCheck->fetch() ? true : false;
    }
    
    // Fetch comments
    $commentsStmt = $db->prepare("
        SELECT pc.*, u.username, u.avatar, u.tag
        FROM post_comments pc
        JOIN users u ON pc.user_id = u.id
        WHERE pc.checkin_id = ?
        ORDER BY pc.created_at ASC
        LIMIT 50
    ");
    $commentsStmt->execute([$postId]);
    $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo escape($post['username']); ?> - <?php echo escape(substr($post['note'] ?? $post['venue_name'], 0, 100)); ?>">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php require_once '../includes/head-bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- NAVBAR -->
    <?php require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content post-detail-page">
            
            <div class="post-detail-container">
                
                <div class="post-detail-header">
                    <a href="<?php echo BASE_URL; ?>/dashboard" class="back-link">← Geri</a>
                    <h1>Gönderi</h1>
                </div>
                
                <article class="tweet post-detail-card">
                    <div class="tweet-inner">
                        <a href="<?php echo BASE_URL; ?>/profile?id=<?php echo $post['user_id']; ?>" class="tweet-avatar">
                            <?php if (!empty($post['user_avatar'])): ?>
                                <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo escape($post['user_avatar']); ?>" alt="<?php echo escape($post['username']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                            <?php endif; ?>
                        </a>
                        <div class="tweet-content">
                            <div class="tweet-header">
                                <a href="<?php echo BASE_URL; ?>/profile?id=<?php echo $post['user_id']; ?>" class="tweet-username">
                                    <?php echo escape($post['username']); ?>
                                </a>
                                <span class="tweet-handle">@<?php echo escape(!empty($post['user_tag']) ? $post['user_tag'] : strtolower($post['username'])); ?></span>
                                <span class="tweet-time"><?php echo formatDate($post['created_at'], true); ?></span>
                            </div>
                            <a href="<?php echo BASE_URL; ?>/venue-detail?id=<?php echo $post['venue_id']; ?>" class="tweet-venue">
                                <?php echo escape($post['venue_name']); ?>
                            </a>
                            <?php if (!empty($post['note'])): ?>
                                <p class="tweet-note"><?php 
                                    // Parse @mentions
                                    $noteText = escape($post['note']);
                                    $noteText = preg_replace_callback(
                                        '/@([a-zA-Z0-9_]+)/',
                                        function($matches) use ($db) {
                                            $tag = $matches[1];
                                            $stmt = $db->prepare("SELECT id FROM users WHERE tag = ? OR LOWER(username) = LOWER(?)");
                                            $stmt->execute([$tag, $tag]);
                                            $user = $stmt->fetch();
                                            if ($user) {
                                                return '<a href="' . BASE_URL . '/profile?id=' . $user['id'] . '" class="mention-link">@' . escape($tag) . '</a>';
                                            }
                                            return '@' . escape($tag);
                                        },
                                        $noteText
                                    );
                                    echo $noteText;
                                ?></p>
                            <?php endif; ?>
                            <?php if (!empty($post['image'])): ?>
                                <div class="post-image">
                                    <img src="<?php echo BASE_URL; ?>/uploads/posts/<?php echo escape($post['image']); ?>" alt="Post fotoğrafı">
                                </div>
                            <?php endif; ?>
                            
                            <div class="post-detail-meta">
                                <span><?php echo date('H:i', strtotime($post['created_at'])); ?></span>
                                <span>·</span>
                                <span><?php echo date('d M Y', strtotime($post['created_at'])); ?></span>
                            </div>
                            
                            <div class="post-detail-stats">
                                <span><strong><?php echo $post['repost_count']; ?></strong> Repost</span>
                                <span><strong><?php echo $post['like_count']; ?></strong> Beğeni</span>
                            </div>
                            
                            <?php if (isLoggedIn()): ?>
                            <div class="tweet-actions">
                                <button class="tweet-action action-comment" data-checkin-id="<?php echo $post['id']; ?>">
                                    <span class="tweet-action-icon">💬</span>
                                    <span class="action-count"><?php echo $post['comment_count']; ?></span>
                                </button>
                                <button class="tweet-action action-repost <?php echo $userReposted ? 'active' : ''; ?>" data-checkin-id="<?php echo $post['id']; ?>">
                                    <span class="tweet-action-icon">🔄</span>
                                    <span class="action-count"><?php echo $post['repost_count']; ?></span>
                                </button>
                                <button class="tweet-action action-like <?php echo $userLiked ? 'active' : ''; ?>" data-checkin-id="<?php echo $post['id']; ?>">
                                    <span class="tweet-action-icon like-icon"><?php echo $userLiked ? '❤️' : '🤍'; ?></span>
                                    <span class="action-count"><?php echo $post['like_count']; ?></span>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                
                <!-- Comments Section -->
                <div class="post-comments-section">
                    <h3>Yorumlar (<?php echo count($comments); ?>)</h3>
                    
                    <?php if (isLoggedIn()): ?>
                    <form class="comment-form" id="comment-form" data-checkin-id="<?php echo $post['id']; ?>">
                        <textarea name="comment" placeholder="Yorum yaz..." required></textarea>
                        <button type="submit">Gönder</button>
                    </form>
                    <?php endif; ?>
                    
                    <div class="comments-list">
                        <?php if (empty($comments)): ?>
                            <p class="no-comments">Henüz yorum yok. İlk yorumu sen yap!</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                            <div class="comment-item">
                                <a href="<?php echo BASE_URL; ?>/profile?id=<?php echo $comment['user_id']; ?>" class="comment-avatar">
                                    <?php if (!empty($comment['avatar'])): ?>
                                        <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo escape($comment['avatar']); ?>" alt="">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($comment['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </a>
                                <div class="comment-content">
                                    <div class="comment-header">
                                        <a href="<?php echo BASE_URL; ?>/profile?id=<?php echo $comment['user_id']; ?>" class="comment-username"><?php echo escape($comment['username']); ?></a>
                                        <span class="comment-time"><?php echo formatDate($comment['created_at'], true); ?></span>
                                    </div>
                                    <p class="comment-text"><?php echo escape($comment['comment']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
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

    <script>
    // Like functionality
    document.querySelectorAll('.action-like').forEach(btn => {
        btn.addEventListener('click', async function() {
            const checkinId = this.dataset.checkinId;
            try {
                const res = await fetch('<?php echo BASE_URL; ?>/api/interactions.php?action=like', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'checkin_id=' + checkinId
                });
                const data = await res.json();
                if (data.success) {
                    this.classList.toggle('active', data.liked);
                    this.querySelector('.like-icon').textContent = data.liked ? '❤️' : '🤍';
                    this.querySelector('.action-count').textContent = data.count;
                }
            } catch (e) {}
        });
    });
    
    // Repost functionality
    document.querySelectorAll('.action-repost').forEach(btn => {
        btn.addEventListener('click', async function() {
            const checkinId = this.dataset.checkinId;
            try {
                const res = await fetch('<?php echo BASE_URL; ?>/api/interactions.php?action=repost', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'checkin_id=' + checkinId
                });
                const data = await res.json();
                if (data.success) {
                    this.classList.toggle('active', data.reposted);
                    this.querySelector('.action-count').textContent = data.count;
                }
            } catch (e) {}
        });
    });
    
    // Comment form
    const commentForm = document.getElementById('comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const checkinId = this.dataset.checkinId;
            const comment = this.querySelector('textarea').value;
            
            try {
                const res = await fetch('<?php echo BASE_URL; ?>/api/interactions.php?action=comment', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'checkin_id=' + checkinId + '&comment=' + encodeURIComponent(comment)
                });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                }
            } catch (e) {}
        });
    }
    </script>

</body>
</html>

