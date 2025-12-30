<?php
/**
 * Shared Navbar Component
 * Requires: config.php, database.php (already included in main pages)
 * Optional: $activeNav variable to highlight current link
 */

// Initialize variables
$navBalance = '0';
$isUserLoggedIn = isset($_SESSION['user_id']);
$navUsername = $_SESSION['username'] ?? '';
$pendingCount = 0; // Initialize pending count

// Fetch balance if logged in
if ($isUserLoggedIn) {
    try {
        if (!isset($db)) {
            $db = Database::getInstance()->getConnection();
        }
        
        // Balance
        $stmtNav = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmtNav->execute([$_SESSION['user_id']]);
        $userNav = $stmtNav->fetch(PDO::FETCH_ASSOC);
        if ($userNav) {
            $navBalance = number_format($userNav['balance'], 0, '', '.');
        }
    } catch (PDOException $e) {
        // Silent fail
    }
}

// Ensure activeNav is set
if (!isset($activeNav)) $activeNav = '';
?>
<nav class="navbar">
    <div class="navbar-inner">
        <div class="nav-links left">
            <a href="<?php echo BASE_URL; ?>/<?php echo $isUserLoggedIn ? 'dashboard.php' : 'index.php'; ?>" class="nav-link <?php echo ($activeNav === 'home') ? 'active' : ''; ?>">Ana Sayfa</a>
            <a href="<?php echo BASE_URL; ?>/venues" class="nav-link <?php echo ($activeNav === 'venues') ? 'active' : ''; ?>">Mekanlar</a>
            <a href="<?php echo BASE_URL; ?>/leaderboard" class="nav-link <?php echo ($activeNav === 'leaderboard') ? 'active' : ''; ?>">Liderlik</a>
            <a href="<?php echo BASE_URL; ?>/members" class="nav-link <?php echo ($activeNav === 'members') ? 'active' : ''; ?>">Üyeler</a>
            <a href="<?php echo BASE_URL; ?>/sponsors" class="nav-link <?php echo ($activeNav === 'sponsors') ? 'active' : ''; ?>">Sponsorlar</a>
        </div>
        
        <div class="nav-brand">
            <?php if (file_exists(ROOT_PATH . '/assets/common/site-mark.png')): ?>
                <a href="<?php echo BASE_URL; ?>/<?php echo $isUserLoggedIn ? 'dashboard.php' : 'index.php'; ?>">
                    <img src="<?php echo BASE_URL; ?>/assets/common/site-mark.png" alt="Uptag">
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/dashboard" class="nav-brand-fallback">Uptag</a>
            <?php endif; ?>
        </div>
        
        <div class="nav-links right">
            <?php if ($isUserLoggedIn): ?>
                
                <a href="<?php echo BASE_URL; ?>/profile" class="nav-link <?php echo ($activeNav === 'profile') ? 'active' : ''; ?>"><?php echo escape($navUsername); ?></a>
                <a href="<?php echo BASE_URL; ?>/logout" class="nav-link">Çıkış</a>
                <a href="<?php echo BASE_URL; ?>/premium" class="nav-btn-premium">✨ Reklam Kaldır</a>
                
                <!-- Balance Display (Far Right) -->
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login" class="nav-link <?php echo ($activeNav === 'login') ? 'active' : ''; ?>">Giriş Yap</a>
                <a href="<?php echo BASE_URL; ?>/register" class="nav-link <?php echo ($activeNav === 'register') ? 'active' : ''; ?>">Kayıt Ol</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
