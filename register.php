<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Zaten giriş yapmışsa yönlendir
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pageTitle = 'Kayıt Ol';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if ($password !== $passwordConfirm) {
        $error = 'Şifreler eşleşmiyor.';
    } else {
        $user = new User();
        $result = $user->register($username, $email, $password);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

include 'includes/header.php';
?>

<div class="form-container">
    <h2>Kayıt Ol</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo escape($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo escape($success); ?></div>
        <p style="text-align: center; margin-top: 1rem;">
            <a href="login.php" class="btn btn-primary">Giriş Yap</a>
        </p>
    <?php else: ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Kullanıcı Adı</label>
                <input type="text" id="username" name="username" required autofocus minlength="3" maxlength="50">
            </div>
            
            <div class="form-group">
                <label for="email">E-posta</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Şifre Tekrar</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="6">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">Kayıt Ol</button>
        </form>
        
        <p style="text-align: center; margin-top: 1rem;">
            Zaten hesabınız var mı? <a href="login.php">Giriş yapın</a>
        </p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

