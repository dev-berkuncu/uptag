<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$pageTitle = 'Mekan Yönetimi';

$venue = new Venue();
$db = Database::getInstance()->getConnection();

$search = trim($_GET['search'] ?? '');
$action = $_GET['action'] ?? '';
$venueId = (int)($_GET['id'] ?? 0);

$error = '';
$success = '';

// İşlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $venueId = (int)($_POST['venue_id'] ?? 0);
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        $result = $venue->createVenue($name, $description, $address);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $result = $venue->updateVenue($venueId, $name, $description, $address, $isActive);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'delete') {
        $result = $venue->deleteVenue($venueId);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Mekan listesi
$venues = $venue->getAllVenues($search);

// Mekan detayı (düzenleme için)
$venueDetail = null;
if ($action === 'edit' && $venueId) {
    $venueDetail = $venue->getVenueById($venueId);
}

include '../includes/header.php';
?>

<h1>Mekan Yönetimi</h1>

<div style="display: flex; gap: 1rem; margin: 1rem 0;">
    <div class="search-box" style="flex: 1;">
        <form method="GET" action="">
            <input type="text" name="search" placeholder="Mekan ara..." value="<?php echo escape($search); ?>">
        </form>
    </div>
    <a href="?action=create" class="btn btn-success">Yeni Mekan Ekle</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo escape($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo escape($success); ?></div>
<?php endif; ?>

<?php if ($action === 'create' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-header"><?php echo $action === 'create' ? 'Yeni Mekan Ekle' : 'Mekan Düzenle'; ?></div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="venue_id" value="<?php echo $venueId; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="name">Mekan Adı *</label>
                <input type="text" id="name" name="name" required value="<?php echo $venueDetail ? escape($venueDetail['name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="description">Açıklama</label>
                <textarea id="description" name="description"><?php echo $venueDetail ? escape($venueDetail['description']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="address">Adres</label>
                <input type="text" id="address" name="address" value="<?php echo $venueDetail ? escape($venueDetail['address']) : ''; ?>">
            </div>
            
            <?php if ($action === 'edit'): ?>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" <?php echo $venueDetail['is_active'] ? 'checked' : ''; ?>>
                        Aktif
                    </label>
                </div>
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary"><?php echo $action === 'create' ? 'Oluştur' : 'Güncelle'; ?></button>
            <a href="venues.php" class="btn btn-secondary">İptal</a>
        </form>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Mekan Adı</th>
                <th>Adres</th>
                <th>Toplam Check-in</th>
                <th>Durum</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($venues as $v): ?>
                <tr>
                    <td><?php echo $v['id']; ?></td>
                    <td><strong><?php echo escape($v['name']); ?></strong></td>
                    <td><?php echo escape($v['address'] ?? '-'); ?></td>
                    <td><?php echo $v['total_checkins']; ?></td>
                    <td>
                        <?php echo $v['is_active'] ? '<span style="color: green;">Aktif</span>' : '<span style="color: red;">Pasif</span>'; ?>
                    </td>
                    <td>
                        <a href="?action=edit&id=<?php echo $v['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">Düzenle</a>
                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Bu mekanı silmek istediğinize emin misiniz?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="venue_id" value="<?php echo $v['id']; ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.9rem;">Sil</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>


