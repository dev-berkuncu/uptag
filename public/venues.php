<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Login gerekmez - herkese açık sayfa
$pageTitle = 'Mekanlar';

$search = trim($_GET['search'] ?? '');
$venue = new Venue();
$venues = $venue->getActiveVenues($search);
require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Uptag'da popüler mekanları keşfet, check-in yap ve puan kazan!">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php require_once '../includes/head-bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'venues'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            
            <!-- Page Header -->
            <section class="page-header">
                <div class="page-header-content">
                    <h1 class="page-title">Mekanlar</h1>
                    <p class="page-subtitle">Şehrin en popüler mekanlarını keşfet ve check-in yap</p>
                </div>
            </section>

            <!-- Search Section -->
            <section class="search-section">
                <form method="GET" action="" class="search-form">
                    <div class="search-input-wrapper">
                        <input 
                            type="text" 
                            name="search" 
                            placeholder="Mekan ara..." 
                            value="<?php echo escape($search); ?>"
                            class="search-input"
                        >
                        <button type="submit" class="search-btn">Ara</button>
                    </div>
                </form>
            </section>

            <!-- Venues Grid -->
            <section class="venues-section">
                <?php if (empty($venues)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"></div>
                        <h3><?php echo $search ? 'Arama sonucu bulunamadı' : 'Henüz mekan eklenmemiş'; ?></h3>
                        <p><?php echo $search ? 'Farklı bir arama terimi deneyin.' : 'Yakında yeni mekanlar eklenecek!'; ?></p>
                    </div>
                <?php else: ?>
                    <div class="venues-grid">
                        <?php foreach ($venues as $v): ?>
                            <article class="venue-card">
                                <div class="venue-card-header">
                                    <div class="venue-badge"></div>
                                    <h3 class="venue-name"><?php echo escape($v['name']); ?></h3>
                                </div>
                                <?php if ($v['description']): ?>
                                    <p class="venue-description"><?php echo escape($v['description']); ?></p>
                                <?php endif; ?>
                                <?php if ($v['address']): ?>
                                    <p class="venue-address"><?php echo escape($v['address']); ?></p>
                                <?php endif; ?>
                                <div class="venue-card-footer">
                                    <a href="venue-detail?id=<?php echo $v['id']; ?>" class="btn btn-primary btn-sm">Check-in</a>
                                    <?php if (!empty($v['website'])): ?>
                                        <a href="<?php echo escape($v['website']); ?>" class="btn btn-secondary btn-sm" target="_blank">Facebrowser</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </main>

        <!-- Right Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-right.php'; ?>

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

