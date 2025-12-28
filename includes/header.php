<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? escape($pageTitle) . ' - ' : ''; ?><?php echo escape(getSetting('site_name', SITE_NAME)); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="<?php echo BASE_URL; ?>/index.php"><?php echo escape(getSetting('site_name', SITE_NAME)); ?></a>
            </div>
            <div class="nav-menu">
                <?php if (isLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>/venues.php">Mekanlar</a>
                    <a href="<?php echo BASE_URL; ?>/my-checkins.php">Check-in Geçmişim</a>
                    <a href="<?php echo BASE_URL; ?>/leaderboard.php">Liderlik Tablosu</a>
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo BASE_URL; ?>/admin/index.php">Admin Panel</a>
                    <?php endif; ?>
                    <span class="user-info"><?php echo escape($_SESSION['username']); ?></span>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="btn-logout">Çıkış</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login.php">Giriş Yap</a>
                    <a href="<?php echo BASE_URL; ?>/register.php">Kayıt Ol</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?>">
                <?php echo escape($_SESSION['message']); ?>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            </div>
        <?php endif; ?>

