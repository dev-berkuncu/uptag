<?php
require_once '../config/config.php';
require_once '../config/database.php';

$pageTitle = 'Lider Tablosu';

$leaderboard = new Leaderboard();
$weekInfo = $leaderboard->getWeekInfo();

$topUsersLimit = (int)getSetting('leaderboard_top_users', 20);
$topVenuesLimit = (int)getSetting('leaderboard_top_venues', 20);

$topUsers = $leaderboard->getTopUsers($topUsersLimit, $weekInfo['start'], $weekInfo['end']);
$topVenues = $leaderboard->getTopVenues($topVenuesLimit, $weekInfo['start'], $weekInfo['end']);

require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Uptag haftalık liderlik tablosu - En çok check-in yapan kullanıcılar ve en popüler mekanlar">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'leaderboard'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            
            <!-- Page Header -->
            <section class="page-header">
                <div class="page-header-content">
                    <h1 class="page-title">Haftalık Lider Tablosu</h1>
                    <p class="page-subtitle">
                        <?php echo $weekInfo['start_formatted']; ?> - <?php echo $weekInfo['end_formatted']; ?>
                    </p>
                </div>
            </section>

            <!-- Leaderboard Section -->
            <section class="leaderboard-section">
                <div class="leaderboard-grid">
                    
                    <!-- Top Users Table -->
                    <div class="leaderboard-card">
                        <div class="leaderboard-card-header">
                            <div class="leaderboard-icon user-icon"></div>
                            <h2>En Çok Check-in Yapanlar</h2>
                        </div>
                        <div class="leaderboard-table-wrapper">
                            <?php if (empty($topUsers)): ?>
                                <div class="empty-state-small">
                                    <p>Bu hafta henüz check-in yapılmamış.</p>
                                </div>
                            <?php else: ?>
                                <table class="leaderboard-table">
                                    <thead>
                                        <tr>
                                            <th class="rank-col">Sıra</th>
                                            <th>Kullanıcı</th>
                                            <th class="count-col">Check-in</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topUsers as $index => $user): ?>
                                            <?php $rank = $index + 1; ?>
                                            <tr class="<?php echo $rank <= 3 ? 'top-rank' : ''; ?>">
                                                <td>
                                                    <span class="rank-badge rank-<?php echo $rank <= 3 ? $rank : 'other'; ?>">
                                                        <?php echo $rank; ?>
                                                    </span>
                                                </td>
                                                <td class="name-cell"><?php echo escape($user['username']); ?></td>
                                                <td class="count-cell"><?php echo $user['checkin_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Venues Table -->
                    <div class="leaderboard-card">
                        <div class="leaderboard-card-header">
                            <div class="leaderboard-icon venue-icon"></div>
                            <h2>En Popüler Mekanlar</h2>
                        </div>
                        <div class="leaderboard-table-wrapper">
                            <?php if (empty($topVenues)): ?>
                                <div class="empty-state-small">
                                    <p>Bu hafta henüz check-in yapılmamış.</p>
                                </div>
                            <?php else: ?>
                                <table class="leaderboard-table">
                                    <thead>
                                        <tr>
                                            <th class="rank-col">Sıra</th>
                                            <th>Mekan</th>
                                            <th class="count-col">Check-in</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($topVenues as $index => $venue): ?>
                                            <?php $rank = $index + 1; ?>
                                            <tr class="<?php echo $rank <= 3 ? 'top-rank' : ''; ?>">
                                                <td>
                                                    <span class="rank-badge rank-<?php echo $rank <= 3 ? $rank : 'other'; ?>">
                                                        <?php echo $rank; ?>
                                                    </span>
                                                </td>
                                                <td class="name-cell">
                                                    <?php echo escape($venue['name']); ?>
                                                    <?php if (!empty($venue['address'])): ?>
                                                        <span class="venue-address-small"><?php echo escape($venue['address']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="count-cell"><?php echo $venue['checkin_count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
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
