<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Zaten giriş yapmışsa yönlendir
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pageTitle = 'Giriş Yap';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        $user = new User();
        $result = $user->login($username, $password);
        
        if ($result['success']) {
            $_SESSION['message'] = $result['message'];
            $_SESSION['message_type'] = 'success';
            header('Location: ' . BASE_URL . '/index.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

include 'includes/header.php';
?>

<div class="form-container">
    <h2>Giriş Yap</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo escape($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Kullanıcı Adı veya E-posta</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        
        <div class="form-group">
            <label for="password">Şifre</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%;">Giriş Yap</button>
    </form>
    
    <div class="login-divider">
        <span>veya</span>
    </div>
    
    <a href="<?php echo BASE_URL; ?>/oauth-login" class="btn-oauth-gta">
        <svg class="gta-logo" viewBox="0 0 24 24" width="20" height="20">
            <circle cx="12" cy="12" r="10" fill="#4CAF50"/>
            <path d="M12 6C8.69 6 6 8.69 6 12s2.69 6 6 6c1.66 0 3.14-.69 4.22-1.78L12 12V6z" fill="#2E7D32"/>
        </svg>
        GTA World TR ile Giriş Yap
    </a>
    
    <p style="text-align: center; margin-top: 1rem;">
        Hesabınız yok mu? <a href="register.php">Kayıt olun</a>
    </p>
</div>

<style>
.login-divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 1.5rem 0;
    color: var(--text-muted, #888);
}

.login-divider::before,
.login-divider::after {
    content: '';
    flex: 1;
    border-bottom: 1px solid var(--card-border, #333);
}

.login-divider span {
    padding: 0 1rem;
    font-size: 0.9rem;
}

.btn-oauth-gta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    padding: 14px 20px;
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-oauth-gta:hover {
    background: #252525;
    border-color: #4CAF50;
}

.btn-oauth-gta .gta-logo {
    flex-shrink: 0;
}
</style>

<?php include 'includes/footer.php'; ?>

