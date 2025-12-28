<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$pageTitle = 'Kullanıcı Yönetimi';

$user = new User();
$db = Database::getInstance()->getConnection();

$search = trim($_GET['search'] ?? '');
$action = $_GET['action'] ?? '';
$userId = (int)($_GET['id'] ?? 0);

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($action === 'ban') {
        $banUntil = $_POST['ban_until'] ?? null;
        $banUntil = $banUntil ? date('Y-m-d H:i:s', strtotime($banUntil)) : null;
        
        $stmt = $db->prepare("UPDATE users SET banned_until = ? WHERE id = ?");
        if ($stmt->execute([$banUntil, $userId])) {
            logAdminAction('user_ban', 'user', $userId, "Kullanıcı askıya alındı: $banUntil");
            $_SESSION['message'] = 'Kullanıcı askıya alındı.';
            $_SESSION['message_type'] = 'success';
        }
    } elseif ($action === 'unban') {
        $stmt = $db->prepare("UPDATE users SET banned_until = NULL WHERE id = ?");
        if ($stmt->execute([$userId])) {
            logAdminAction('user_unban', 'user', $userId, "Kullanıcı yeniden etkinleştirildi");
            $_SESSION['message'] = 'Kullanıcı yeniden etkinleştirildi.';
            $_SESSION['message_type'] = 'success';
        }
    } elseif ($action === 'toggle_active') {
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        if ($stmt->execute([$userId])) {
            logAdminAction('user_toggle_active', 'user', $userId, "Kullanıcı aktiflik durumu değiştirildi");
            $_SESSION['message'] = 'Kullanıcı durumu güncellendi.';
            $_SESSION['message_type'] = 'success';
        }
    }
    
    header('Location: users.php' . ($search ? '?search=' . urlencode($search) : ''));
    exit;
}

// Kullanıcı listesi
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM checkins WHERE user_id = u.id) as total_checkins,
        (SELECT COUNT(*) FROM checkins WHERE user_id = u.id AND DATE(created_at) = CURDATE()) as today_checkins
        FROM users u";
$params = [];

if ($search) {
    $sql .= " WHERE u.username LIKE ? OR u.email LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Kullanıcı detayı
$userDetail = null;
if ($action === 'view' && $userId) {
    $userDetail = $user->getUserById($userId);
    if ($userDetail) {
        $userDetail['checkin_count'] = $user->getCheckinCount($userId);
        $checkin = new Checkin();
        $userDetail['recent_checkins'] = $checkin->getUserCheckins($userId, 10);
    }
}

include '../includes/header.php';
?>

<h1>Kullanıcı Yönetimi</h1>

<div class="search-box">
    <form method="GET" action="">
        <input type="text" name="search" placeholder="Kullanıcı adı veya e-posta ile ara..." value="<?php echo escape($search); ?>">
    </form>
</div>

<?php if ($userDetail): ?>
    <div class="card" style="margin: 2rem 0;">
        <div class="card-header">Kullanıcı Detayı: <?php echo escape($userDetail['username']); ?></div>
        <p><strong>E-posta:</strong> <?php echo escape($userDetail['email']); ?></p>
        <p><strong>Kayıt Tarihi:</strong> <?php echo formatDate($userDetail['created_at'], true); ?></p>
        <p><strong>Son Giriş:</strong> <?php echo $userDetail['last_login'] ? formatDate($userDetail['last_login'], true) : 'Henüz giriş yapmamış'; ?></p>
        <p><strong>Durum:</strong> 
            <?php echo $userDetail['is_active'] ? '<span style="color: green;">Aktif</span>' : '<span style="color: red;">Pasif</span>'; ?>
            <?php if ($userDetail['is_admin']): ?>
                <span style="color: #3498db;">(Admin)</span>
            <?php endif; ?>
        </p>
        <?php if ($userDetail['banned_until']): ?>
            <p><strong>Askıya Alınma:</strong> <?php echo formatDate($userDetail['banned_until'], true); ?></p>
        <?php endif; ?>
        <p><strong>Toplam Check-in:</strong> <?php echo $userDetail['checkin_count']; ?></p>
        
        <h3 style="margin-top: 1.5rem;">Son Check-in'ler</h3>
        <?php if (empty($userDetail['recent_checkins'])): ?>
            <p>Henüz check-in yapmamış.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($userDetail['recent_checkins'] as $ci): ?>
                    <li style="padding: 0.5rem 0; border-bottom: 1px solid #eee;">
                        <?php echo escape($ci['venue_name']); ?> - <?php echo formatDate($ci['created_at'], true); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <div style="margin-top: 1.5rem;">
            <form method="POST" action="" style="display: inline;">
                <input type="hidden" name="user_id" value="<?php echo $userDetail['id']; ?>">
                <input type="hidden" name="action" value="toggle_active">
                <button type="submit" class="btn btn-secondary">
                    <?php echo $userDetail['is_active'] ? 'Pasife Al' : 'Aktifleştir'; ?>
                </button>
            </form>
            
            <?php if (!$userDetail['banned_until'] || strtotime($userDetail['banned_until']) < time()): ?>
                <form method="POST" action="" style="display: inline; margin-left: 0.5rem;">
                    <input type="hidden" name="user_id" value="<?php echo $userDetail['id']; ?>">
                    <input type="hidden" name="action" value="ban">
                    <input type="datetime-local" name="ban_until" required>
                    <button type="submit" class="btn btn-danger">Askıya Al</button>
                </form>
            <?php else: ?>
                <form method="POST" action="" style="display: inline; margin-left: 0.5rem;">
                    <input type="hidden" name="user_id" value="<?php echo $userDetail['id']; ?>">
                    <input type="hidden" name="action" value="unban">
                    <button type="submit" class="btn btn-success">Yeniden Etkinleştir</button>
                </form>
            <?php endif; ?>
        </div>
        
        <a href="users.php" style="display: inline-block; margin-top: 1rem;">← Listeye Dön</a>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kullanıcı Adı</th>
                <th>E-posta</th>
                <th>Kayıt Tarihi</th>
                <th>Check-in</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td>
                        <strong><?php echo escape($u['username']); ?></strong>
                        <?php if ($u['is_admin']): ?>
                            <span style="color: #3498db;">(Admin)</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo escape($u['email']); ?></td>
                    <td><?php echo formatDate($u['created_at']); ?></td>
                    <td><?php echo $u['total_checkins']; ?> (Bugün: <?php echo $u['today_checkins']; ?>)</td>
                    <td>
                        <?php if ($u['banned_until'] && strtotime($u['banned_until']) > time()): ?>
                            <span style="color: red;">Askıya Alınmış</span>
                        <?php elseif (!$u['is_active']): ?>
                            <span style="color: orange;">Pasif</span>
                        <?php else: ?>
                            <span style="color: green;">Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?action=view&id=<?php echo $u['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">Detay</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>

