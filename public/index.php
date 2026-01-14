<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Initialize Database
$db = Database::getInstance()->getConnection();

require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sociaera ile favori mekanlarında check-in yap, puan kazan ve haftalık liderlik tablosunda yerini al!">
    <title>Sociaera - Mekanlarını Keşfet, Check-in Yap!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php require_once '../includes/head-bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'home'; require_once '../includes/navbar.php'; ?>



    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            
            <!-- HERO SECTION (Inside Grid) -->
            <section class="hero hero-compact">
                <div class="hero-bg">
                    <img src="<?php echo BASE_URL; ?>/assets/index/hero.png" alt="Uptag Hero">
                </div>
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <h1 class="hero-title">Paylaş, Check-in Yap Ve<br>Keşfet</h1>
                    <p class="hero-description">
                        Favori mekanlarında check-in yap, özel rozetler kazan ve arkadaşlarınla 
                        haftalık liderlik tablosunda yarış.
                    </p>
                    <div class="hero-buttons">
                        <a href="venues" class="btn btn-primary">Hemen Check-in Yap</a>
                        <a href="leaderboard" class="btn btn-secondary">Haftalık Liderlik</a>
                    </div>
                </div>
            </section>

            <!-- FEATURES SECTION -->
            <section class="features-section">
                <div class="features-grid">
                    
                    <!-- Feature 1: Kolay Check-in -->
                    <article class="feature-card">
                        <div class="feature-icon"></div>
                        <h3 class="feature-title">Kolay Check-in</h3>
                        <p class="feature-description">
                            Tek tıkla bulunduğun mekanda check-in yap. 
                            Hızlı, pratik ve eğlenceli deneyim seni bekliyor.
                        </p>
                    </article>

                    <!-- Feature 2: Haftalık Liderlik -->
                    <article class="feature-card">
                        <div class="feature-icon"></div>
                        <h3 class="feature-title">Haftalık Liderlik</h3>
                        <p class="feature-description">
                            Her hafta sıfırlanan liderlik tablosunda 
                            arkadaşlarınla yarış ve zirvenin tadını çıkar.
                        </p>
                    </article>

                    <!-- Feature 3: Popüler Mekanlar -->
                    <article class="feature-card">
                        <div class="feature-icon"></div>
                        <h3 class="feature-title">Popüler Mekanlar</h3>
                        <p class="feature-description">
                            En çok check-in yapılan mekanları keşfet. 
                            Yeni yerler bul, deneyimlerini paylaş.
                        </p>
                    </article>

                </div>
            </section>

        </main>

        <!-- Right Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-right.php'; ?>

    </div>


    <!-- FOOTER -->
    <footer class="footer">
        
        <!-- Footer Sponsor Banner -->
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

        <!-- Footer Content -->
        <div class="footer-content">
            <div class="footer-about">
                <h3>Uptag</h3>
                <p>
                    Uptag, sosyal keşif ve check-in platformudur. Favori mekanlarınızda 
                    anlarınızı paylaşın, arkadaşlarınızla yarışın ve şehri birlikte keşfedin.
                </p>
            </div>
            
            <div class="footer-links">
                <h4>Keşfet</h4>
                <ul>
                    <li><a href="venues">Mekanlar</a></li>
                    <li><a href="leaderboard">Liderlik</a></li>
                    <li><a href="#">Hakkımızda</a></li>
                </ul>
            </div>
            
            <div class="footer-links">
                <h4>Hesap</h4>
                <ul>
                    <li><a href="login">Giriş Yap</a></li>
                    <li><a href="register">Kayıt Ol</a></li>
                    <li><a href="#">Gizlilik</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Uptag. Tüm hakları saklıdır.</p>
        </div>

    </footer>

</body>
</html>

