<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Zaten giriş yapmışsa yönlendir
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Giriş Yap';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } elseif (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $user = new User();
        $result = $user->login($username, $password);
        
        if ($result['success']) {
            session_regenerate_id(true);
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = 'success';
            header('Location: dashboard.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}


require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Uptag'a giriş yap ve check-in yapmaya başla">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'login'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <aside class="sponsor-sidebar sponsor-left">
            <?php if (!empty($sidebarLeftAds)): ?>
                <?php $lAd = $sidebarLeftAds[0]; ?>
                <a href="<?php echo escape($lAd['link_url'] ?: '#'); ?>" target="_blank">
                    <img src="<?php echo BASE_URL . '/' . escape($lAd['image_url']); ?>" alt="<?php echo escape($lAd['title']); ?>">
                </a>
            <?php else: ?>
                <div class="sponsor-placeholder" style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 30px; text-align: center;">
                    <div style="font-size: 1.5rem; margin-bottom: 8px;">📢</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">Sol Sidebar</div>
                </div>
            <?php endif; ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            
            <!-- AUTH PAGE -->
            <div class="auth-page-inner">
                <div class="auth-container">
                    <div class="auth-card">
                        <div class="auth-header">
                            <div class="auth-icon"></div>
                            <h1>Hoş Geldin!</h1>
                            <p>Hesabına giriş yap ve check-in yapmaya başla</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error">
                                <?php echo escape($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="auth-form">
                            <?php echo csrfField(); ?>
                            <div class="form-group">
                                <label for="username">Kullanıcı Adı veya E-posta</label>
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    placeholder="kullaniciadi veya email@example.com"
                                    required 
                                    autofocus
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Şifre</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    placeholder="••••••••"
                                    required
                                >
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-full">Giriş Yap</button>
                        </form>

                        <div class="auth-footer">
                            <p>Hesabın yok mu? <a href="register">Kayıt Ol</a></p>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <!-- Right Sponsor Sidebar -->
        <aside class="sponsor-sidebar sponsor-right">
            <?php if (!empty($sidebarRightAds)): ?>
                <?php $rAd = $sidebarRightAds[0]; ?>
                <a href="<?php echo escape($rAd['link_url'] ?: '#'); ?>" target="_blank">
                    <img src="<?php echo BASE_URL . '/' . escape($rAd['image_url']); ?>" alt="<?php echo escape($rAd['title']); ?>">
                </a>
            <?php else: ?>
                <div class="sponsor-placeholder" style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 30px; text-align: center;">
                    <div style="font-size: 1.5rem; margin-bottom: 8px;">📢</div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">Sağ Sidebar</div>
                </div>
            <?php endif; ?>
        </aside>

    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-sponsor">
            <?php if (!empty($footerAds)): ?>
                <?php $fAd = $footerAds[0]; ?>
                <a href="<?php echo escape($fAd['link_url'] ?: '#'); ?>" target="_blank" style="display: block; text-align: center;">
                    <img src="<?php echo BASE_URL . '/' . escape($fAd['image_url']); ?>" alt="<?php echo escape($fAd['title']); ?>" style="max-width: 100%; max-height: 120px; border-radius: 8px;">
                </a>
            <?php else: ?>
                <div class="footer-sponsor-placeholder" style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-align: center;">
                    <span style="font-size: 1.5rem;">📢</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="footer-content">
            <div class="footer-about">
                <h3>Uptag</h3>
                <p>Uptag, sosyal keşif ve check-in platformudur. Favori mekanlarınızda anlarınızı paylaşın.</p>
            </div>
            <div class="footer-links">
                <h4>Keşfet</h4>
                <ul>
                    <li><a href="venues">Mekanlar</a></li>
                    <li><a href="leaderboard">Liderlik</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Hesap</h4>
                <ul>
                    <li><a href="login">Giriş Yap</a></li>
                    <li><a href="register">Kayıt Ol</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Uptag. Tüm hakları saklıdır.</p>
        </div>
    </footer>

</body>
</html>
