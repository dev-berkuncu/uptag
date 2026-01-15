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
    <?php require_once '../includes/head-bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>

<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'login';
    require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT (Bootstrap Grid - Fixed Sidebar) -->
    <div class="container-fluid app-layout-wrapper">
        <div class="row flex-nowrap h-100">

            <!-- Sol Sponsor: col-auto, sabit 300px -->
            <div class="col-auto app-sponsor-col">
                <aside class="sponsor-sidebar sponsor-left app-sponsor-sidebar">
                    <?php if (!empty($sidebarLeftAds)): ?>
                        <?php $lAd = $sidebarLeftAds[0]; ?>
                        <a href="<?php echo escape($lAd['link_url'] ?: '#'); ?>" target="_blank">
                            <img src="<?php echo BASE_URL . '/' . escape($lAd['image_url']); ?>"
                                alt="<?php echo escape($lAd['title']); ?>">
                        </a>
                    <?php else: ?>
                        <div class="sponsor-placeholder"
                            style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 30px; text-align: center;">
                            <div style="font-size: 1.5rem; margin-bottom: 8px;">📢</div>
                            <div style="color: var(--text-muted); font-size: 0.85rem;">Sol Sidebar</div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <!-- Orta İçerik: col, esnek - SCROLL BURADA -->
            <div class="col app-feed-col">
                <main class="main-content app-feed">

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
                                        <input type="text" id="username" name="username"
                                            placeholder="kullaniciadi veya email@example.com" required autofocus>
                                    </div>

                                    <div class="form-group">
                                        <label for="password">Şifre</label>
                                        <input type="password" id="password" name="password" placeholder="••••••••"
                                            required>
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-full">Giriş Yap</button>
                                </form>

                                <div class="login-divider">
                                    <span>veya</span>
                                </div>

                                <a href="<?php echo BASE_URL; ?>/oauth-login" class="btn-oauth-gta">
                                    <img src="<?php echo BASE_URL; ?>/assets/common/site-mark.png" alt=""
                                        class="oauth-logo">
                                    GTA World TR ile Giriş Yap
                                </a>

                                <div class="auth-footer">
                                    <p>Hesabın yok mu? <a href="register">Kayıt Ol</a></p>
                                </div>
                            </div>
                        </div>
                    </div>

                </main>
            </div>

            <!-- Sağ Sponsor: col-auto, sabit 300px -->
            <div class="col-auto app-sponsor-col">
                <aside class="sponsor-sidebar sponsor-right app-sponsor-sidebar">
                    <?php if (!empty($sidebarRightAds)): ?>
                        <?php $rAd = $sidebarRightAds[0]; ?>
                        <a href="<?php echo escape($rAd['link_url'] ?: '#'); ?>" target="_blank">
                            <img src="<?php echo BASE_URL . '/' . escape($rAd['image_url']); ?>"
                                alt="<?php echo escape($rAd['title']); ?>">
                        </a>
                    <?php else: ?>
                        <div class="sponsor-placeholder"
                            style="background: rgba(0,0,0,0.3); border-radius: 12px; padding: 30px; text-align: center;">
                            <div style="font-size: 1.5rem; margin-bottom: 8px;">📢</div>
                            <div style="color: var(--text-muted); font-size: 0.85rem;">Sağ Sidebar</div>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-sponsor">
            <?php if (!empty($footerAds)): ?>
                <?php $fAd = $footerAds[0]; ?>
                <a href="<?php echo escape($fAd['link_url'] ?: '#'); ?>" target="_blank"
                    style="display: block; text-align: center;">
                    <img src="<?php echo BASE_URL . '/' . escape($fAd['image_url']); ?>"
                        alt="<?php echo escape($fAd['title']); ?>"
                        style="max-width: 100%; max-height: 120px; border-radius: 8px;">
                </a>
            <?php else: ?>
                <div class="footer-sponsor-placeholder"
                    style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-align: center;">
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

    <style>
        .login-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: var(--text-muted, #888);
        }

        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--card-border, #333);
        }

        .login-divider span {
            padding: 0 1rem;
            font-size: 0.9rem;
        }

        .btn-oauth-gta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 14px 20px;
            background: linear-gradient(135deg, var(--orange-accent, #c03901) 0%, #ff6b35 100%);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(192, 57, 1, 0.3);
        }

        .btn-oauth-gta:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(192, 57, 1, 0.4);
        }

        .btn-oauth-gta .oauth-logo {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }
    </style>

</body>

</html>