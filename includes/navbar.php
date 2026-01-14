<?php
/**
 * Shared Navbar Component
 * Requires: config.php, database.php (already included in main pages)
 * Optional: $activeNav variable to highlight current link
 * Refactored for better responsive support
 */

// Initialize variables
$navBalance = '0';
$isUserLoggedIn = isset($_SESSION['user_id']);
$navUsername = $_SESSION['username'] ?? '';
$pendingCount = 0; 

// Fetch balance if logged in
if ($isUserLoggedIn) {
    try {
        if (!isset($db)) {
            $db = Database::getInstance()->getConnection();
        }
        $stmtNav = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
        $stmtNav->execute([$_SESSION['user_id']]);
        $walletNav = $stmtNav->fetch(PDO::FETCH_ASSOC);
        if ($walletNav) {
            $navBalance = number_format($walletNav['balance'], 0, '', '.');
        }
    } catch (PDOException $e) { }
}

// Ensure activeNav is set
if (!isset($activeNav)) $activeNav = '';

// Define Links Structure
$leftLinks = [
    ['url' => ($isUserLoggedIn ? 'dashboard' : 'index'), 'label' => 'Ana Sayfa', 'key' => 'home'],
    ['url' => 'venues', 'label' => 'Mekanlar', 'key' => 'venues'],
    ['url' => 'leaderboard', 'label' => 'Liderlik', 'key' => 'leaderboard'],
    ['url' => 'members', 'label' => 'Üyeler', 'key' => 'members'],
    ['url' => 'sponsors', 'label' => 'Sponsorlar', 'key' => 'sponsors'],
];

?>
<nav class="navbar">
    <div class="navbar-inner">
        
        <!-- DESKTOP LEFT LINKS -->
        <div class="nav-links left desktop-only">
            <?php foreach ($leftLinks as $link): ?>
                <a href="<?php echo BASE_URL; ?>/<?php echo $link['url']; ?>" 
                   class="nav-link <?php echo ($activeNav === $link['key']) ? 'active' : ''; ?>">
                   <?php echo $link['label']; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- BRAND / LOGO -->
        <div class="nav-brand">
            <?php if (file_exists(ROOT_PATH . '/assets/common/site-mark.png')): ?>
                <a href="<?php echo BASE_URL; ?>/<?php echo $isUserLoggedIn ? 'dashboard.php' : 'index.php'; ?>">
                    <img src="<?php echo BASE_URL; ?>/assets/common/site-mark.png" alt="Uptag">
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/dashboard" class="nav-brand-fallback">Uptag</a>
            <?php endif; ?>
        </div>
        
        <!-- DESKTOP RIGHT LINKS -->
        <div class="nav-links right desktop-only">
            <?php if ($isUserLoggedIn): ?>
                <a href="<?php echo BASE_URL; ?>/wallet" class="nav-link <?php echo ($activeNav === 'wallet') ? 'active' : ''; ?>" title="Cüzdan">💰 $<?php echo $navBalance; ?></a>
                <a href="<?php echo BASE_URL; ?>/profile" class="nav-link <?php echo ($activeNav === 'profile') ? 'active' : ''; ?>"><?php echo escape($navUsername); ?></a>
                <a href="<?php echo BASE_URL; ?>/logout" class="nav-link">Çıkış</a>
                <a href="<?php echo BASE_URL; ?>/premium" class="nav-btn-premium">✨ Reklam Kaldır</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login" class="nav-link <?php echo ($activeNav === 'login') ? 'active' : ''; ?>">Giriş Yap</a>
                <a href="<?php echo BASE_URL; ?>/register" class="nav-link <?php echo ($activeNav === 'register') ? 'active' : ''; ?>">Kayıt Ol</a>
            <?php endif; ?>
        </div>

        <!-- HAMBURGER TOGGLE -->
        <button class="nav-toggle" id="navToggle" aria-label="Menüyü Aç">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</nav>

<!-- MOBILE MENU OVERLAY -->
<div class="mobile-menu-overlay" id="mobileMenu">
    <div class="mobile-menu-content">
        <!-- Close Button -->
        <button class="mobile-close-btn" id="mobileCloseBtn">×</button>

        <!-- Profile Summary (Mobile Only) -->
        <?php if ($isUserLoggedIn): ?>
        <div class="mobile-profile-summary">
            <div class="mobile-avatar">
                <?php echo strtoupper(substr($navUsername, 0, 1)); ?>
            </div>
            <div class="mobile-user-info">
                <div class="name"><?php echo escape($navUsername); ?></div>
                <div class="balance">💰 $<?php echo $navBalance; ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mobile-links">
            <?php foreach ($leftLinks as $link): ?>
                <a href="<?php echo BASE_URL; ?>/<?php echo $link['url']; ?>" 
                   class="mobile-nav-link <?php echo ($activeNav === $link['key']) ? 'active' : ''; ?>">
                   <?php echo $link['label']; ?>
                </a>
            <?php endforeach; ?>
            
            <hr class="mobile-divider">
            
            <?php if ($isUserLoggedIn): ?>
                <a href="<?php echo BASE_URL; ?>/wallet" class="mobile-nav-link <?php echo ($activeNav === 'wallet') ? 'active' : ''; ?>">Cüzdanım</a>
                <a href="<?php echo BASE_URL; ?>/profile" class="mobile-nav-link <?php echo ($activeNav === 'profile') ? 'active' : ''; ?>">Profilim</a>
                <a href="<?php echo BASE_URL; ?>/premium" class="mobile-nav-link highlight">✨ Premium</a>
                <a href="<?php echo BASE_URL; ?>/logout" class="mobile-nav-link logout">Çıkış Yap</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login" class="mobile-nav-link <?php echo ($activeNav === 'login') ? 'active' : ''; ?>">Giriş Yap</a>
                <a href="<?php echo BASE_URL; ?>/register" class="mobile-nav-link highlight">Kayıt Ol</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.getElementById('navToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    const closeBtn = document.getElementById('mobileCloseBtn');
    
    function toggleMenu() {
        if (!mobileMenu) return;
        mobileMenu.classList.toggle('active');
        document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
    }

    if (navToggle) navToggle.addEventListener('click', toggleMenu);
    if (closeBtn) closeBtn.addEventListener('click', toggleMenu);
});
</script>