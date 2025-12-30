<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$pageTitle = 'Leaderboard Kontrolü';

$leaderboard = new Leaderboard();
$weekInfo = $leaderboard->getWeekInfo();

$topUsersLimit = (int)getSetting('leaderboard_top_users', 20);
$topVenuesLimit = (int)getSetting('leaderboard_top_venues', 20);

$tab = $_GET['tab'] ?? 'users';

$topUsers = $leaderboard->getTopUsers($topUsersLimit, $weekInfo['start'], $weekInfo['end']);
$topVenues = $leaderboard->getTopVenues($topVenuesLimit, $weekInfo['start'], $weekInfo['end']);

include '../includes/header.php';
?>

<h1>Leaderboard Kontrolü</h1>

<div class="card" style="margin: 2rem 0;">
    <div class="card-header">Haftalık Hesaplama Bilgileri</div>
    <p><strong>Hafta Başlangıcı:</strong> <?php echo $weekInfo['start_formatted']; ?></p>
    <p><strong>Hafta Bitişi:</strong> <?php echo $weekInfo['end_formatted']; ?></p>
    <p><strong>Zaman Dilimi:</strong> Europe/Istanbul</p>
    <p><strong>Hafta Kuralı:</strong> Pazartesi 00:00 - Pazar 23:59</p>
    <p style="margin-top: 1rem;">
        <a href="leaderboard.php" class="btn btn-primary">Yenile</a>
    </p>
</div>

<div class="leaderboard-tabs">
    <a href="?tab=users" class="<?php echo $tab === 'users' ? 'active' : ''; ?>">Top Kullanıcılar (<?php echo count($topUsers); ?>)</a>
    <a href="?tab=venues" class="<?php echo $tab === 'venues' ? 'active' : ''; ?>">Top Mekanlar (<?php echo count($topVenues); ?>)</a>
</div>

<?php if ($tab === 'users'): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Sıra</th>
                    <th>Kullanıcı</th>
                    <th>E-posta</th>
                    <th>Check-in Sayısı</th>
                    <th>İlk Check-in</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topUsers)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem;">Bu hafta henüz check-in yapılmamış.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topUsers as $index => $user): ?>
                        <tr>
                            <td>
                                <?php
                                $rank = $index + 1;
                                $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                                ?>
                                <span class="rank-badge <?php echo $rankClass; ?>"><?php echo $rank; ?></span>
                            </td>
                            <td><strong><?php echo escape($user['username']); ?></strong></td>
                            <td><?php echo escape($user['email']); ?></td>
                            <td><?php echo $user['checkin_count']; ?></td>
                            <td><?php echo formatDate($user['first_checkin'], true); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Sıra</th>
                    <th>Mekan</th>
                    <th>Adres</th>
                    <th>Check-in Sayısı</th>
                    <th>İlk Check-in</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topVenues)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 2rem;">Bu hafta henüz check-in yapılmamış.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topVenues as $index => $venue): ?>
                        <tr>
                            <td>
                                <?php
                                $rank = $index + 1;
                                $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-other'));
                                ?>
                                <span class="rank-badge <?php echo $rankClass; ?>"><?php echo $rank; ?></span>
                            </td>
                            <td><strong><?php echo escape($venue['name']); ?></strong></td>
                            <td><?php echo escape($venue['address'] ?? '-'); ?></td>
                            <td><?php echo $venue['checkin_count']; ?></td>
                            <td><?php echo formatDate($venue['first_checkin'], true); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>


