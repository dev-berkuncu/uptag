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
    <title><?php echo escape($pageTitle); ?> - Sociaera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css?v=<?php echo time(); ?>">
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
                    <div>
                        <h1>Bildirimler</h1>
                        <div class="notifications-filter">Seni etikettiğin</div>
                    </div>
                    <?php if (!empty($notifications)): ?>
                    <button class="clear-notifications-btn" id="clearNotificationsBtn">
                        🗑️ Tümünü Temizle
                    </button>
                    <?php endif; ?>
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
                            <a href="<?php echo BASE_URL; ?>/<?php echo $notif['checkin_id'] ? 'posts/' . $notif['checkin_id'] : 'profile?id=' . $notif['from_user_id']; ?>" data-post-id="<?php echo $notif['checkin_id'] ?? 'null'; ?>" class="notification-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <div class="notification-icon-wrapper">
                                    <div class="notification-avatar">
                                        <?php if (!empty($notif['from_avatar'])): ?>
                                            <img src="<?php echo BASE_URL; ?>/uploads/avatars/<?php echo escape($notif['from_avatar']); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($notif['from_username'] ?? '?', 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-type-badge">
                                        <?php 
                                        $icons = ['mention' => '📣', 'like' => '❤️', 'comment' => '💬', 'follow' => '👤', 'repost' => '🔄'];
                                        echo $icons[$notif['type']] ?? '🔔'; 
                                        ?>
                                    </div>
                                </div>
                                <div class="notification-body">
                                    <div class="notification-text">
                                        <span class="notification-username"><?php echo escape($notif['from_username']); ?></span>
                                        <?php 
                                        $actionText = 'bir işlem yaptı';
                                        if ($notif['type'] === 'mention') $actionText = 'sizi etiketledi';
                                        elseif ($notif['type'] === 'like') $actionText = 'gönderinizi beğendi';
                                        elseif ($notif['type'] === 'comment') $actionText = 'yorum yaptı';
                                        elseif ($notif['type'] === 'follow') $actionText = 'sizi takip etti';
                                        elseif ($notif['type'] === 'repost') $actionText = 'repostladı';
                                        echo $actionText;
                                        ?>
                                    </div>
                                    <div class="notification-preview-text">
                                        <?php echo escape($notif['content'] ?? $actionText); ?>
                                    </div>
                                </div>
                                <div class="notification-meta">
                                    <span class="notification-time"><?php echo formatDate($notif['created_at'], true); ?></span>
                                    <span class="notification-dot"></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        </main>

        <!-- Right Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-right.php'; ?>

    </div>

    <!-- Post Modal Overlay -->
    <div id="post-modal" class="post-modal-overlay" style="display: none;">
        <div class="post-modal-backdrop"></div>
        <div class="post-modal-container">
            <button class="post-modal-close">&times;</button>
            <div class="post-modal-content" id="post-modal-content">
                <div class="post-modal-loading">Yükleniyor...</div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer footer-minimal">
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Sociaera. Tüm hakları saklıdır.</p>
        </div>
    </footer>

    <script>
    // Clear Notifications Button
    const clearBtn = document.getElementById('clearNotificationsBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', async function() {
            if (!confirm('Tüm bildirimleri silmek istediğinize emin misiniz?')) return;
            
            try {
                const res = await fetch('<?php echo BASE_URL; ?>/api/notifications.php?action=clear', {
                    method: 'POST'
                });
                const data = await res.json();
                if (data.success) {
                    // DOM'dan bildirimleri kaldır
                    const notificationsList = document.querySelector('.notifications-list');
                    if (notificationsList) {
                        notificationsList.style.opacity = '0';
                        notificationsList.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => {
                            notificationsList.innerHTML = `
                                <div class="notifications-empty">
                                    <div class="empty-icon">🔕</div>
                                    <h3>Henüz bildirim yok</h3>
                                    <p>Birisi sizi etiketlediğinde burada göreceksiniz.</p>
                                </div>
                            `;
                            notificationsList.style.opacity = '1';
                        }, 300);
                    }
                    // Butonu gizle
                    this.style.display = 'none';
                } else {
                    alert(data.error || 'Bir hata oluştu.');
                }
            } catch (e) {
                alert('Bağlantı hatası: ' + e.message);
            }
        });
    }
    
    // Post Modal System
    const modal = document.getElementById('post-modal');
    const modalContent = document.getElementById('post-modal-content');
    const closeBtn = modal.querySelector('.post-modal-close');
    const backdrop = modal.querySelector('.post-modal-backdrop');

    // Open modal when clicking notification
    document.querySelectorAll('.notification-card').forEach(card => {
        card.addEventListener('click', async function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            
            // Extract post ID from /posts/123 format
            const match = href.match(/\/posts\/(\d+)/);
            if (!match) {
                // Not a post link, open normally
                window.open(href, '_blank');
                return;
            }
            
            const postId = match[1];
            openPostModal(postId);
        });
    });

    async function openPostModal(postId) {
        console.log('Opening modal for post ID:', postId);
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        modalContent.innerHTML = '<div class="post-modal-loading">Yükleniyor...</div>';
        
        try {
            const url = '<?php echo BASE_URL; ?>/api/get-post.php?id=' + postId;
            console.log('Fetching URL:', url);
            const res = await fetch(url);
            const data = await res.json();
            console.log('API Response:', data);
            
            if (data.success) {
                renderPostModal(data.post, data.comments);
            } else {
                modalContent.innerHTML = '<div class="post-modal-error">' + (data.error || 'Post bulunamadı.') + '</div>';
            }
        } catch (e) {
            console.error('Modal Error:', e);
            modalContent.innerHTML = '<div class="post-modal-error">Bir hata oluştu: ' + e.message + '</div>';
        }
    }

    function renderPostModal(post, comments) {
        let commentsHtml = '';
        if (comments && comments.length > 0) {
            commentsHtml = comments.map(c => `
                <div class="modal-comment">
                    <div class="modal-comment-avatar">
                        ${c.avatar ? `<img src="<?php echo BASE_URL; ?>/uploads/avatars/${c.avatar}" alt="">` : c.username.charAt(0).toUpperCase()}
                    </div>
                    <div class="modal-comment-body">
                        <span class="modal-comment-user">${c.username}</span>
                        <span class="modal-comment-text">${c.comment}</span>
                    </div>
                </div>
            `).join('');
        }
        
        modalContent.innerHTML = `
            <div class="modal-post" data-post-id="${post.id}">
                <div class="modal-post-header">
                    <a href="<?php echo BASE_URL; ?>/profile?id=${post.user_id}" class="modal-avatar">
                        ${post.user_avatar ? `<img src="<?php echo BASE_URL; ?>/uploads/avatars/${post.user_avatar}" alt="">` : post.username.charAt(0).toUpperCase()}
                    </a>
                    <div class="modal-user-info">
                        <a href="<?php echo BASE_URL; ?>/profile?id=${post.user_id}" class="modal-username">${post.username}</a>
                        <span class="modal-time">${post.created_at}</span>
                    </div>
                </div>
                <div class="modal-post-venue">
                    <a href="<?php echo BASE_URL; ?>/venue-detail?id=${post.venue_id}">${post.venue_name}</a>
                </div>
                ${post.note ? `<div class="modal-post-text">${post.note}</div>` : ''}
                ${post.image ? `<div class="modal-post-image"><img src="<?php echo BASE_URL; ?>/uploads/posts/${post.image}" alt=""></div>` : ''}
                
                <div class="modal-actions">
                    <button class="modal-action-btn modal-like-btn" data-post-id="${post.id}">
                        <span class="like-icon">🤍</span>
                        <span class="like-count">${post.like_count}</span> Beğeni
                    </button>
                    <button class="modal-action-btn modal-repost-btn" data-post-id="${post.id}">
                        🔄 <span class="repost-count">${post.repost_count}</span> Repost
                    </button>
                </div>
                
                <div class="modal-comments">
                    <h4>Yorumlar (${post.comment_count})</h4>
                    <div class="modal-comments-list" id="modal-comments-list">
                        ${commentsHtml || '<p class="no-comments">Henüz yorum yok.</p>'}
                    </div>
                </div>
                
                <?php if (isLoggedIn()): ?>
                <div class="modal-comment-form">
                    <input type="text" id="modal-comment-input" placeholder="Yorum yaz..." />
                    <label class="modal-image-btn" title="Fotoğraf ekle">
                        📷
                        <input type="file" id="modal-comment-image" accept="image/*" style="display:none" />
                    </label>
                    <button id="modal-comment-submit" data-post-id="${post.id}">Gönder</button>
                </div>
                <div id="modal-image-preview" class="modal-image-preview" style="display:none;">
                    <img id="modal-preview-img" src="" alt="" />
                    <button id="modal-remove-image" class="modal-remove-image">&times;</button>
                </div>
                <?php endif; ?>
            </div>
        `;
        
        // Attach like button handler
        const likeBtn = modalContent.querySelector('.modal-like-btn');
        if (likeBtn) {
            likeBtn.addEventListener('click', async function() {
                const postId = this.dataset.postId;
                try {
                    const res = await fetch('<?php echo BASE_URL; ?>/api/interactions.php?action=like', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'checkin_id=' + postId
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.querySelector('.like-icon').textContent = data.liked ? '❤️' : '🤍';
                        this.querySelector('.like-count').textContent = data.count;
                    }
                } catch (e) {
                    console.error('Like error:', e);
                }
            });
        }
        
        // Attach comment submit handler
        const commentSubmit = document.getElementById('modal-comment-submit');
        const commentInput = document.getElementById('modal-comment-input');
        const imageInput = document.getElementById('modal-comment-image');
        const imagePreview = document.getElementById('modal-image-preview');
        const previewImg = document.getElementById('modal-preview-img');
        const removeImageBtn = document.getElementById('modal-remove-image');
        
        // Image preview handler
        if (imageInput) {
            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Remove image handler
        if (removeImageBtn) {
            removeImageBtn.addEventListener('click', function() {
                imageInput.value = '';
                imagePreview.style.display = 'none';
                previewImg.src = '';
            });
        }
        
        if (commentSubmit && commentInput) {
            commentSubmit.addEventListener('click', async function() {
                const postId = this.dataset.postId;
                const comment = commentInput.value.trim();
                const imageFile = imageInput?.files[0];
                
                if (!comment && !imageFile) return;
                
                try {
                    const formData = new FormData();
                    formData.append('checkin_id', postId);
                    formData.append('content', comment);
                    if (imageFile) {
                        formData.append('image', imageFile);
                    }
                    
                    const res = await fetch('<?php echo BASE_URL; ?>/api/interactions.php?action=comment', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        // Add comment to list
                        const commentsList = document.getElementById('modal-comments-list');
                        const noComments = commentsList.querySelector('.no-comments');
                        if (noComments) noComments.remove();
                        
                        let imageHtml = '';
                        if (data.comment && data.comment.image) {
                            imageHtml = `<img src="<?php echo BASE_URL; ?>/${data.comment.image}" class="comment-image" alt="">`;
                        }
                        
                        commentsList.innerHTML += `
                            <div class="modal-comment">
                                <div class="modal-comment-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></div>
                                <div class="modal-comment-body">
                                    <span class="modal-comment-user"><?php echo escape($_SESSION['username'] ?? 'Sen'); ?></span>
                                    <span class="modal-comment-text">${comment}</span>
                                    ${imageHtml}
                                </div>
                            </div>
                        `;
                        commentInput.value = '';
                        if (imageInput) imageInput.value = '';
                        if (imagePreview) imagePreview.style.display = 'none';
                    }
                } catch (e) {
                    console.error('Comment error:', e);
                }
            });
            
            // Submit on Enter
            commentInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    commentSubmit.click();
                }
            });
        }
    }

    // Close modal
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    </script>

</body>
</html>

