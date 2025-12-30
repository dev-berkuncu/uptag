<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$pageTitle = 'Check-in Yönetimi';

$checkin = new Checkin();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? '';
$checkinId = (int)($_GET['id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$error = '';
$success = '';

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $checkinId = (int)($_POST['checkin_id'] ?? 0);
    
    if ($action === 'exclude') {
        $reason = trim($_POST['reason'] ?? '');
        $result = $checkin->excludeFromLeaderboard($checkinId, $reason);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'flag') {
        $reason = trim($_POST['reason'] ?? '');
        $result = $checkin->flagCheckin($checkinId, $reason);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'delete') {
        $result = $checkin->deleteCheckin($checkinId);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Check-in listesi
$checkins = $checkin->getRecentCheckins($perPage, $offset);
$totalCheckins = $db->query("SELECT COUNT(*) FROM checkins")->fetchColumn();
$totalPages = ceil($totalCheckins / $perPage);

include '../includes/header.php';
?>

<h1>Check-in Yönetimi</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo escape($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo escape($success); ?></div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kullanıcı</th>
                <th>Mekan</th>
                <th>Not</th>
                <th>Tarih</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checkins as $ci): ?>
                <tr style="<?php echo $ci['is_flagged'] ? 'background-color: #fff3cd;' : ($ci['is_excluded_from_leaderboard'] ? 'background-color: #f8d7da;' : ''); ?>">
                    <td><?php echo $ci['id']; ?></td>
                    <td><?php echo escape($ci['username']); ?></td>
                    <td><?php echo escape($ci['venue_name']); ?></td>
                    <td><?php echo escape($ci['note'] ?? '-'); ?></td>
                    <td><?php echo formatDate($ci['created_at'], true); ?></td>
                    <td>
                        <?php if ($ci['is_flagged']): ?>
                            <span style="color: orange;">⚠️ İşaretli</span>
                        <?php endif; ?>
                        <?php if ($ci['is_excluded_from_leaderboard']): ?>
                            <span style="color: red;">🚫 Hariç</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="checkin_id" value="<?php echo $ci['id']; ?>">
                            <input type="hidden" name="action" value="exclude">
                            <input type="text" name="reason" placeholder="Sebep" style="width: 150px; padding: 0.25rem; font-size: 0.9rem;">
                            <button type="submit" class="btn btn-warning" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">Hariç Tut</button>
                        </form>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="checkin_id" value="<?php echo $ci['id']; ?>">
                            <input type="hidden" name="action" value="flag">
                            <input type="text" name="reason" placeholder="Sebep" style="width: 150px; padding: 0.25rem; font-size: 0.9rem;">
                            <button type="submit" class="btn btn-warning" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">İşaretle</button>
                        </form>
                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Bu check-in\'i silmek istediğinize emin misiniz?');">
                            <input type="hidden" name="checkin_id" value="<?php echo $ci['id']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>">Önceki</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>">Sonraki</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

