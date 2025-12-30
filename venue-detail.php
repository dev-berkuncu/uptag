<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$venueId = (int)($_GET['id'] ?? 0);
$venue = new Venue();
$venueData = $venue->getVenueById($venueId);

if (!$venueData || !$venueData['is_active']) {
    $_SESSION['message'] = 'Mekan bulunamadı.';
    $_SESSION['message_type'] = 'error';
    header('Location: ' . BASE_URL . '/venues.php');
    exit;
}

$pageTitle = escape($venueData['name']);
$error = '';
$success = '';

// Check-in işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin'])) {
    $note = trim($_POST['note'] ?? '');
    $checkin = new Checkin();
    $result = $checkin->createCheckin($_SESSION['user_id'], $venueId, $note);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Check-in sayısını al
$checkinCount = $venue->getCheckinCount($venueId);

include 'includes/header.php';
?>

<h1><?php echo escape($venueData['name']); ?></h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo escape($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo escape($success); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Mekan Bilgileri</div>
    <?php if ($venueData['description']): ?>
        <p><?php echo nl2br(escape($venueData['description'])); ?></p>
    <?php endif; ?>
    
    <?php if ($venueData['address']): ?>
        <p><strong>Adres:</strong> <?php echo escape($venueData['address']); ?></p>
    <?php endif; ?>
    
    <p><strong>Toplam Check-in:</strong> <?php echo $checkinCount; ?></p>
</div>

<div class="card">
    <div class="card-header">Check-in Yap</div>
    <form method="POST" action="">
        <div class="form-group">
            <label for="note">Not (Opsiyonel)</label>
            <textarea id="note" name="note" placeholder="Bu check-in hakkında bir not ekleyebilirsiniz..."></textarea>
        </div>
        
        <button type="submit" name="checkin" class="btn btn-success">Check-in Yap</button>
    </form>
</div>

<div style="margin-top: 1rem;">
    <a href="venues.php" class="btn btn-secondary">← Mekanlara Dön</a>
</div>

<?php include 'includes/footer.php'; ?>


