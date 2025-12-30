<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();
require_once '../includes/ads_logic.php';

$pageTitle = 'Profil Ayarları';
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$db = Database::getInstance()->getConnection();

// Kullanıcı bilgilerini getir
$stmt = $db->prepare("SELECT id, username, tag, email, avatar, banner, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$success = '';
$error = '';

// Resim yükleme fonksiyonu
function uploadImage($file, $type, $userId) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    // Avatar için 2MB, banner için 5MB sınırı
    $maxSize = $type === 'avatar' ? 2 * 1024 * 1024 : 5 * 1024 * 1024;
    $maxSizeText = $type === 'avatar' ? '2MB' : '5MB';
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Dosya yükleme hatası.'];
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Sadece JPG, PNG, GIF veya WebP dosyaları yüklenebilir.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => "Dosya boyutu {$maxSizeText}'dan küçük olmalıdır."];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $userId . '_' . time() . '.' . strtolower($ext);
    $folder = $type === 'avatar' ? 'avatars' : 'banners';
    $uploadPath = __DIR__ . '/uploads/' . $folder . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Dosya kaydedilemedi.'];
}

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
        $newUsername = trim($_POST['username'] ?? '');
        $newTag = trim($_POST['tag'] ?? '');
        $newEmail = trim($_POST['email'] ?? '');
        
        // Tag'den @ işaretini kaldır
        $newTag = ltrim($newTag, '@');
        
        if (empty($newUsername) || empty($newEmail)) {
            $error = 'Kullanıcı adı ve e-posta gereklidir.';
        } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 50) {
            $error = 'Kullanıcı adı 3-50 karakter arasında olmalıdır.';
        } elseif (!empty($newTag) && !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $newTag)) {
            $error = 'Etiket 3-30 karakter arasında olmalı ve sadece harf, rakam ve alt çizgi içermelidir.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Geçerli bir e-posta adresi giriniz.';
        } else {
            // Kullanıcı adı, etiket ve e-posta benzersizliğini kontrol et
            $checkStmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ? OR (tag = ? AND tag != '')) AND id != ?");
            $checkStmt->execute([$newUsername, $newEmail, $newTag, $userId]);
            
            if ($checkStmt->fetch()) {
                $error = 'Bu kullanıcı adı, etiket veya e-posta zaten kullanılıyor.';
            } else {
                $updateStmt = $db->prepare("UPDATE users SET username = ?, tag = ?, email = ? WHERE id = ?");
                if ($updateStmt->execute([$newUsername, $newTag, $newEmail, $userId])) {
                    $_SESSION['username'] = $newUsername;
                    $_SESSION['email'] = $newEmail;
                    $username = $newUsername;
                    $user['username'] = $newUsername;
                    $user['tag'] = $newTag;
                    $user['email'] = $newEmail;
                    $success = 'Profil bilgileri güncellendi!';
                } else {
                    $error = 'Bir hata oluştu.';
                }
            }
        }
    }
    
    if ($action === 'upload_avatar' && isset($_FILES['avatar'])) {
        $result = uploadImage($_FILES['avatar'], 'avatar', $userId);
        if ($result['success']) {
            // Eski avatarı sil
            if ($user['avatar']) {
                @unlink(__DIR__ . '/uploads/avatars/' . $user['avatar']);
            }
            $updateStmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $updateStmt->execute([$result['filename'], $userId]);
            $user['avatar'] = $result['filename'];
            $success = 'Profil fotoğrafı güncellendi!';
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'upload_banner' && isset($_FILES['banner'])) {
        $result = uploadImage($_FILES['banner'], 'banner', $userId);
        if ($result['success']) {
            // Eski banner'ı sil
            if ($user['banner']) {
                @unlink(__DIR__ . '/uploads/banners/' . $user['banner']);
            }
            $updateStmt = $db->prepare("UPDATE users SET banner = ? WHERE id = ?");
            $updateStmt->execute([$result['filename'], $userId]);
            $user['banner'] = $result['filename'];
            $success = 'Banner güncellendi!';
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Tüm şifre alanları gereklidir.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Yeni şifre en az 6 karakter olmalıdır.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Yeni şifreler eşleşmiyor.';
        } else {
            $passStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $passStmt->execute([$userId]);
            $passRow = $passStmt->fetch();
            
            if (!password_verify($currentPassword, $passRow['password_hash'])) {
                $error = 'Mevcut şifre yanlış.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePassStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                if ($updatePassStmt->execute([$newHash, $userId])) {
                    $success = 'Şifre başarıyla değiştirildi!';
                } else {
                    $error = 'Bir hata oluştu.';
                }
            }
        }
        }
    }
}

$avatarUrl = $user['avatar'] ? BASE_URL . '/uploads/avatars/' . $user['avatar'] : null;
$bannerUrl = $user['banner'] ? BASE_URL . '/uploads/banners/' . $user['banner'] : null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Profil ayarlarını düzenle">
    <title><?php echo escape($pageTitle); ?> - Sociaera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'profile'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content settings-page">
            
            <div class="settings-container">
                <div class="settings-header">
                    <a href="profile" class="back-link">← Profile Dön</a>
                    <h1>Profil Ayarları</h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo escape($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo escape($error); ?></div>
                <?php endif; ?>

                <!-- Avatar & Banner Upload -->
                <div class="settings-card">
                    <h2>Profil Görselleri</h2>
                    
                    <!-- Banner Preview & Upload -->
                    <div class="image-upload-section">
                        <label>Banner (Kapak Fotoğrafı)</label>
                        <div class="banner-preview" <?php if ($bannerUrl): ?>style="background-image: url('<?php echo $bannerUrl; ?>')"<?php endif; ?>>
                            <?php if (!$bannerUrl): ?>
                                <span>1500x500 önerilir</span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="upload_banner">
                            <input type="file" name="banner" id="bannerInput" accept="image/*" onchange="this.form.submit()">
                            <label for="bannerInput" class="btn btn-secondary btn-sm">Banner Yükle</label>
                        </form>
                    </div>
                    
                    <!-- Avatar Preview & Upload -->
                    <div class="image-upload-section">
                        <label>Profil Fotoğrafı</label>
                        <div class="avatar-preview">
                            <?php if ($avatarUrl): ?>
                                <img src="<?php echo $avatarUrl; ?>" alt="Avatar">
                            <?php else: ?>
                                <span><?php echo strtoupper(substr($user['username'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="upload_avatar">
                            <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="this.form.submit()">
                            <label for="avatarInput" class="btn btn-secondary btn-sm">Fotoğraf Yükle</label>
                        </form>
                    </div>
                </div>

                <!-- Profile Info Form -->
                <div class="settings-card">
                    <h2>Profil Bilgileri</h2>
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="username">Kullanıcı Adı</label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                value="<?php echo escape($user['username']); ?>"
                                required
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="tag">@ Etiketi</label>
                            <div class="input-with-prefix">
                                <span class="input-prefix">@</span>
                                <input 
                                    type="text" 
                                    id="tag" 
                                    name="tag" 
                                    value="<?php echo escape($user['tag'] ?? ''); ?>"
                                    placeholder="kullanici_adi"
                                    pattern="[a-zA-Z0-9_]{3,30}"
                                >
                            </div>
                            <small class="form-hint">3-30 karakter, sadece harf, rakam ve alt çizgi</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-posta</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="<?php echo escape($user['email']); ?>"
                                required
                            >
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </form>
                </div>

                <!-- Password Form -->
                <div class="settings-card">
                    <h2>Şifre Değiştir</h2>
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Mevcut Şifre</label>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                placeholder="••••••••"
                                required
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">Yeni Şifre</label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                placeholder="••••••••"
                                required
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Yeni Şifre (Tekrar)</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                placeholder="••••••••"
                                required
                            >
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Şifreyi Değiştir</button>
                    </form>
                </div>

                <!-- Account Info -->
                <div class="settings-card settings-info">
                    <h2>Hesap Bilgileri</h2>
                    <p><strong>Üyelik Tarihi:</strong> <?php echo formatDate($user['created_at'], true); ?></p>
                    <p><strong>Hesap ID:</strong> #<?php echo $user['id']; ?></p>
                </div>

            </div>

        </main>

        <!-- Right Sponsor Sidebar -->
        <!-- Right Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-right.php'; ?>

    </div>

    <!-- FOOTER -->
    <footer class="footer footer-minimal">
        <div class="footer-sponsor">
            <?php if (!empty($footerAds)): ?>
                <?php $fAd = $footerAds[0]; ?>
                <a href="<?php echo escape($fAd['link_url'] ?: '#'); ?>" target="_blank" style="display: block; text-align: center; margin-bottom: 20px;">
                    <img src="<?php echo BASE_URL . '/' . escape($fAd['image_url']); ?>" alt="<?php echo escape($fAd['title']); ?>" style="max-width: 100%; max-height: 120px; border-radius: 8px;">
                </a>
            <?php endif; ?>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Sociaera. Tüm hakları saklıdır.</p>
        </div>
    </footer>

</body>
</html>

