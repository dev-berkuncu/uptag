<?php
require_once 'config/config.php';
require_once 'config/database.php';

$pageTitle = 'Ana Sayfa';

include 'includes/header.php';
?>

<div class="hero">
    <h1>Uptag'a Hoş Geldiniz!</h1>
    <p>Mekanları keşfedin, check-in yapın ve liderlik tablosunda yer alın.</p>
    
    <?php if (!isLoggedIn()): ?>
        <div style="margin-top: 2rem;">
            <a href="register.php" class="btn btn-primary">Kayıt Ol</a>
            <a href="login.php" class="btn btn-secondary" style="margin-left: 1rem;">Giriş Yap</a>
        </div>
    <?php else: ?>
        <div style="margin-top: 2rem;">
            <a href="venues.php" class="btn btn-primary">Mekanları Görüntüle</a>
            <a href="leaderboard.php" class="btn btn-secondary" style="margin-left: 1rem;">Liderlik Tablosu</a>
        </div>
    <?php endif; ?>
</div>

<?php if (isLoggedIn()): ?>
    <div class="card" style="margin-top: 2rem;">
        <h2>Son Check-in'leriniz</h2>
        <?php
        $checkin = new Checkin();
        $recentCheckins = $checkin->getUserCheckins($_SESSION['user_id'], 5);
        
        if (empty($recentCheckins)):
        ?>
            <p>Henüz check-in yapmadınız. <a href="venues.php">Mekanları görüntüleyin</a> ve ilk check-in'inizi yapın!</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($recentCheckins as $ci): ?>
                    <li style="padding: 0.5rem 0; border-bottom: 1px solid #eee;">
                        <strong><?php echo escape($ci['venue_name']); ?></strong>
                        <span style="color: #7f8c8d; font-size: 0.9rem;"> - <?php echo timeAgo($ci['created_at']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="my-checkins.php" style="margin-top: 1rem; display: inline-block;">Tüm geçmişi görüntüle →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
.hero {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin: 2rem 0;
}

.hero h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #2c3e50;
}

.hero p {
    font-size: 1.2rem;
    color: #7f8c8d;
}
</style>

<?php include 'includes/footer.php'; ?>

