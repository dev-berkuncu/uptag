<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Zaten giriş yapmışsa yönlendir
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Kayıt Ol';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if ($password !== $passwordConfirm) {
        $error = 'Şifreler eşleşmiyor.';
    } else {
        $user = new User();
        $result = $user->register($username, $email, $password);
        
        if ($result['success']) {
            $success = $result['message'];
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
    <meta name="description" content="Uptag'a üye ol ve check-in yapmaya başla">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php require_once '../includes/head-bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'register'; require_once '../includes/navbar.php'; ?>

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
                            <div class="auth-icon auth-icon-register"></div>
                            <h1>Aramıza Katıl!</h1>
                            <p>Ücretsiz hesap oluştur ve check-in yapmaya başla</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-error">
                                <?php echo escape($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo escape($success); ?>
                            </div>
                            <div class="auth-success-action">
                                <a href="login" class="btn btn-primary btn-full">Giriş Yap</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" class="auth-form">
                                <?php echo csrfField(); ?>
                                <div class="form-group">
                                    <label for="username">Kullanıcı Adı</label>
                                    <input 
                                        type="text" 
                                        id="username" 
                                        name="username" 
                                        placeholder="kullaniciadi"
                                        required 
                                        autofocus
                                        minlength="3" 
                                        maxlength="50"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">E-posta</label>
                                    <input 
                                        type="email" 
                                        id="email" 
                                        name="email" 
                                        placeholder="email@example.com"
                                        required
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Şifre</label>
                                    <input 
                                        type="password" 
                                        id="password" 
                                        name="password" 
                                        placeholder="En az 6 karakter"
                                        required
                                        minlength="6"
                                    >
                                </div>
                                
                                <div class="form-group">
                                    <label for="password_confirm">Şifre Tekrar</label>
                                    <input 
                                        type="password" 
                                        id="password_confirm" 
                                        name="password_confirm" 
                                        placeholder="Şifreyi tekrar girin"
                                        required
                                        minlength="6"
                                    >
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-full">Kayıt Ol</button>
                            </form>

                            <div class="auth-footer">
                                <p>Zaten hesabın var mı? <a href="login">Giriş Yap</a></p>
                            </div>
                        <?php endif; ?>
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

