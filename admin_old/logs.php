<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$pageTitle = 'İşlem Logları';

$db = Database::getInstance()->getConnection();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$actionFilter = $_GET['action'] ?? '';
$targetFilter = $_GET['target'] ?? '';

// Log listesi
$sql = "SELECT al.*, u.username as admin_username
        FROM admin_logs al
        INNER JOIN users u ON al.admin_id = u.id
        WHERE 1=1";
$params = [];

if ($actionFilter) {
    $sql .= " AND al.action_type = ?";
    $params[] = $actionFilter;
}

if ($targetFilter) {
    $sql .= " AND al.target_type = ?";
    $params[] = $targetFilter;
}

$sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Toplam sayfa
$countSql = "SELECT COUNT(*) FROM admin_logs WHERE 1=1";
$countParams = [];
if ($actionFilter) {
    $countSql .= " AND action_type = ?";
    $countParams[] = $actionFilter;
}
if ($targetFilter) {
    $countSql .= " AND target_type = ?";
    $countParams[] = $targetFilter;
}
$totalLogs = $db->prepare($countSql);
$totalLogs->execute($countParams);
$totalCount = $totalLogs->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// Benzersiz action ve target tipleri (filtre için)
$actionTypes = $db->query("SELECT DISTINCT action_type FROM admin_logs ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
$targetTypes = $db->query("SELECT DISTINCT target_type FROM admin_logs ORDER BY target_type")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>

<h1>İşlem Logları</h1>

<div class="card" style="margin: 1rem 0;">
    <form method="GET" action="" style="display: flex; gap: 1rem; align-items: end;">
        <div class="form-group" style="flex: 1;">
            <label for="action">İşlem Tipi</label>
            <select id="action" name="action">
                <option value="">Tümü</option>
                <?php foreach ($actionTypes as $at): ?>
                    <option value="<?php echo escape($at); ?>" <?php echo $actionFilter === $at ? 'selected' : ''; ?>>
                        <?php echo escape($at); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="flex: 1;">
            <label for="target">Hedef Tip</label>
            <select id="target" name="target">
                <option value="">Tümü</option>
                <?php foreach ($targetTypes as $tt): ?>
                    <option value="<?php echo escape($tt); ?>" <?php echo $targetFilter === $tt ? 'selected' : ''; ?>>
                        <?php echo escape($tt); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn btn-primary">Filtrele</button>
        <a href="logs.php" class="btn btn-secondary">Temizle</a>
    </form>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Admin</th>
                <th>İşlem Tipi</th>
                <th>Hedef Tip</th>
                <th>Hedef ID</th>
                <th>Detaylar</th>
                <th>IP Adresi</th>
                <th>Tarih</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 2rem;">Log kaydı bulunamadı.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td><?php echo escape($log['admin_username']); ?></td>
                        <td><code><?php echo escape($log['action_type']); ?></code></td>
                        <td><code><?php echo escape($log['target_type']); ?></code></td>
                        <td><?php echo $log['target_id'] ?: '-'; ?></td>
                        <td><?php echo escape($log['details'] ?? '-'); ?></td>
                        <td><?php echo escape($log['ip_address'] ?? '-'); ?></td>
                        <td><?php echo formatDate($log['created_at'], true); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $targetFilter ? '&target=' . urlencode($targetFilter) : ''; ?>">Önceki</a>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $targetFilter ? '&target=' . urlencode($targetFilter) : ''; ?>" 
               class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $actionFilter ? '&action=' . urlencode($actionFilter) : ''; ?><?php echo $targetFilter ? '&target=' . urlencode($targetFilter) : ''; ?>">Sonraki</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

