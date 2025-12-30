<?php
require_once 'config/config.php';
require_once 'config/database.php';

requireLogin();

$pageTitle = 'Mekanlar';

$search = trim($_GET['search'] ?? '');
$venue = new Venue();
$venues = $venue->getActiveVenues($search);

include 'includes/header.php';
?>

<h1>Mekanlar</h1>

<div class="search-box">
    <form method="GET" action="">
        <input type="text" name="search" placeholder="Mekan ara..." value="<?php echo escape($search); ?>">
    </form>
</div>

<?php if (empty($venues)): ?>
    <div class="card">
        <p><?php echo $search ? 'Arama sonucu bulunamadı.' : 'Henüz mekan eklenmemiş.'; ?></p>
    </div>
<?php else: ?>
    <div class="venue-grid">
        <?php foreach ($venues as $v): ?>
            <div class="venue-card">
                <h3><?php echo escape($v['name']); ?></h3>
                <?php if ($v['description']): ?>
                    <p><?php echo escape($v['description']); ?></p>
                <?php endif; ?>
                <?php if ($v['address']): ?>
                    <p style="font-size: 0.9rem; color: #7f8c8d;">📍 <?php echo escape($v['address']); ?></p>
                <?php endif; ?>
                <a href="venue-detail.php?id=<?php echo $v['id']; ?>" class="btn btn-primary">Detay</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

