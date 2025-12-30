<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$pageTitle = 'Check-in Geçmişim';

$checkin = new Checkin();
$checkins = $checkin->getUserCheckins($_SESSION['user_id'], 20);

include 'includes/header.php';
?>

<h1>Check-in Geçmişim</h1>

<?php if (empty($checkins)): ?>
    <div class="card">
        <p>Henüz check-in yapmadınız. <a href="venues.php">Mekanları görüntüleyin</a> ve ilk check-in'inizi yapın!</p>
    </div>
<?php else: ?>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Mekan</th>
                    <th>Adres</th>
                    <th>Not</th>
                    <th>Tarih</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checkins as $ci): ?>
                    <tr>
                        <td><strong><?php echo escape($ci['venue_name']); ?></strong></td>
                        <td><?php echo escape($ci['venue_address'] ?? '-'); ?></td>
                        <td><?php echo escape($ci['note'] ?? '-'); ?></td>
                        <td><?php echo formatDate($ci['created_at'], true); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>


