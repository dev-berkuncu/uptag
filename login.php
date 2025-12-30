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
    
    <p style="text-align: center; margin-top: 1rem;">
        Hesabınız yok mu? <a href="register.php">Kayıt olun</a>
    </p>
</div>

<?php include 'includes/footer.php'; ?>


