<?php
require_once 'config/config.php';
require_once 'config/database.php';

$pageTitle = 'Haftalık Liderlik Tablosu';

$leaderboard = new Leaderboard();
$weekInfo = $leaderboard->getWeekInfo();

$tab = $_GET['tab'] ?? 'users';
$topUsersLimit = (int)getSetting('leaderboard_top_users', 20);
$topVenuesLimit = (int)getSetting('leaderboard_top_venues', 20);

$topUsers = $leaderboard->getTopUsers($topUsersLimit, $weekInfo['start'], $weekInfo['end']);
$topVenues = $leaderboard->getTopVenues($topVenuesLimit, $weekInfo['start'], $weekInfo['end']);

include 'includes/header.php';
?>

<h1>Haftalık Liderlik Tablosu</h1>

<div class="card" style="margin-bottom: 2rem;">
    <p><strong>Hafta Aralığı:</strong> <?php echo $weekInfo['start_formatted']; ?> - <?php echo $weekInfo['end_formatted']; ?></p>
    <p style="color: #7f8c8d; font-size: 0.9rem; margin-top: 0.5rem;">Haftalık hesaplama Pazartesi 00:00 - Pazar 23:59 (Europe/Istanbul) aralığını kapsar.</p>
</div>

<div class="leaderboard-tabs">
    <a href="?tab=users" class="<?php echo $tab === 'users' ? 'active' : ''; ?>">Top Kullanıcılar</a>
    <a href="?tab=venues" class="<?php echo $tab === 'venues' ? 'active' : ''; ?>">Top Mekanlar</a>
</div>

<?php if ($tab === 'users'): ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Sıra</th>
                    <th>Kullanıcı</th>
                    <th>Check-in Sayısı</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topUsers)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 2rem;">Bu hafta henüz check-in yapılmamış.</td>
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
                            <td><?php echo $user['checkin_count']; ?></td>
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
                </tr>
            </thead>
            <tbody>
                <?php if (empty($topVenues)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 2rem;">Bu hafta henüz check-in yapılmamış.</td>
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
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>


