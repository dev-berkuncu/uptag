<?php
require_once '../config/config.php';
require_once '../config/database.php';

$pageTitle = 'Sponsorlarımız';

// İş ortaklarımız
$sponsors = [
    [
        'name' => 'Pillbox Casino',
        'logo' => 'pillbox-casino.png',
        'description' => 'Premium eğlence ve oyun deneyimi sunan lider casino.',
        'website' => 'https://facebrowser-tr.gta.world/pages/PillboxCasino?ref=qs'
    ],
    [
        'name' => 'Paradise Group',
        'logo' => 'paradise-group.png',
        'description' => 'Hızın, tutkunun ve lüksün adresi.',
        'website' => 'https://facebrowser-tr.gta.world/pages/paradise?ref=qs'
    ]
];

require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sociaera'ı destekleyen sponsorlarımız">
    <title><?php echo escape($pageTitle); ?> - Sociaera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'sponsors'; require_once '../includes/navbar.php'; ?>

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
            
            <!-- Page Header -->
            <section class="page-header">
                <div class="page-header-content">
                    <h1 class="page-title">Sponsorlarımız</h1>
                    <p class="page-subtitle">Sociaera'yı destekleyen değerli iş ortaklarımız</p>
                </div>
            </section>

            <!-- Sponsors Section -->
            <section class="sponsors-section">
                
                <!-- All Sponsors Card -->
                <div class="sponsors-showcase-card">
                    <div class="showcase-header">
                        <h2>🤝 Destekçilerimiz</h2>
                        <p>Sociaera'yı güçlü kılan değerli iş ortaklarımız</p>
                    </div>
                    
                    <div class="sponsors-showcase-grid">
                        <?php foreach ($sponsors as $sponsor): ?>
                        <a href="<?php echo $sponsor['website']; ?>" target="_blank" class="sponsor-showcase-item partner">
                            <div class="sponsor-showcase-logo">
                                <img src="<?php echo BASE_URL; ?>/assets/sponsors/<?php echo $sponsor['logo']; ?>" alt="<?php echo escape($sponsor['name']); ?>">
                            </div>
                            <div class="sponsor-showcase-info">
                                <h4><?php echo escape($sponsor['name']); ?></h4>
                                <p class="sponsor-description"><?php echo escape($sponsor['description']); ?></p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Become a Sponsor CTA -->
                <div class="sponsor-cta">
                    <h3>Sponsor Olmak İster Misiniz?</h3>
                    <p>Sociaera ile iş birliği yaparak markanızı binlerce kullanıcıya ulaştırın.</p>
                    <a href="https://facebrowser-tr.gta.world/pages/sociaerasantos" class="btn btn-primary">İletişime Geçin</a>
                </div>

            </section>

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
                <h3>Sociaera</h3>
                <p>Sociaera, sosyal keşif ve check-in platformudur. Favori mekanlarınızda anlarınızı paylaşın.</p>
            </div>
            <div class="footer-links">
                <h4>Keşfet</h4>
                <ul>
                    <li><a href="venues">Mekanlar</a></li>
                    <li><a href="leaderboard">Liderlik</a></li>
                    <li><a href="sponsors">Sponsorlar</a></li>
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
            <p>&copy; <?php echo date('Y'); ?> Sociaera. Tüm hakları saklıdır.</p>
        </div>
    </footer>

</body>
</html>
