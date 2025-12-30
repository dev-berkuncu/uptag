<?php
/**
 * GTA World OAuth Callback Handler
 * OAuth akışından dönen kullanıcıyı işler
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

$error = '';

// OAuth ayarları
$clientId = defined('OAUTH_CLIENT_ID') ? OAUTH_CLIENT_ID : '';
$clientSecret = defined('OAUTH_CLIENT_SECRET') ? OAUTH_CLIENT_SECRET : '';
$redirectUri = defined('OAUTH_REDIRECT_URI') ? OAUTH_REDIRECT_URI : BASE_URL . '/oauth-callback';

// Hata veya code kontrolü
if (isset($_GET['error'])) {
    $error = 'OAuth hatası: ' . ($_GET['error_description'] ?? $_GET['error']);
} elseif (!isset($_GET['code'])) {
    $error = 'Yetkilendirme kodu bulunamadı.';
} elseif (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    $error = 'Geçersiz state parametresi. Güvenlik hatası.';
} else {
    // State'i temizle
    unset($_SESSION['oauth_state']);
    
    $code = $_GET['code'];
    
    try {
        // Access token al
        $tokenUrl = 'https://ucp-tr.gta.world/oauth/token';
        $tokenData = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $tokenResponse = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception('Token isteği başarısız: ' . curl_error($ch));
        }
        curl_close($ch);
        
        $tokenResult = json_decode($tokenResponse, true);
        
        if (!isset($tokenResult['access_token'])) {
            throw new Exception('Access token alınamadı: ' . ($tokenResult['error'] ?? 'Bilinmeyen hata'));
        }
        
        $accessToken = $tokenResult['access_token'];
        
        // Kullanıcı bilgilerini al
        $userUrl = 'https://ucp-tr.gta.world/api/user';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $userResponse = curl_exec($ch);
        
        if (curl_errno($ch)) {
            throw new Exception('Kullanıcı bilgisi alınamadı: ' . curl_error($ch));
        }
        curl_close($ch);
        
        $userData = json_decode($userResponse, true);
        
        if (!isset($userData['user'])) {
            throw new Exception('Kullanıcı bilgisi alınamadı.');
        }
        
        $gtaUser = $userData['user'];
        $gtaUserId = $gtaUser['id'];
        $gtaUsername = $gtaUser['username'];
        
        $db = Database::getInstance()->getConnection();
        
        // Bu GTA kullanıcısı daha önce kayıtlı mı?
        $stmt = $db->prepare("SELECT * FROM users WHERE gta_user_id = ?");
        $stmt->execute([$gtaUserId]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            // Mevcut kullanıcı - giriş yap
            $_SESSION['user_id'] = $existingUser['id'];
            $_SESSION['username'] = $existingUser['username'];
            $_SESSION['email'] = $existingUser['email'];
            $_SESSION['is_admin'] = $existingUser['is_admin'];
            
            // Son giriş zamanını güncelle
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$existingUser['id']]);
            
            $_SESSION['message'] = 'GTA World ile giriş başarılı!';
            $_SESSION['message_type'] = 'success';
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        } else {
            // Yeni kullanıcı - kayıt oluştur
            // Kullanıcı adı benzersiz olmalı
            $username = $gtaUsername;
            $checkUsername = $db->prepare("SELECT id FROM users WHERE username = ?");
            $checkUsername->execute([$username]);
            $counter = 1;
            while ($checkUsername->fetch()) {
                $username = $gtaUsername . $counter;
                $checkUsername->execute([$username]);
                $counter++;
            }
            
            // Kullanıcıyı oluştur (şifresiz - OAuth kullanıcısı)
            $insertStmt = $db->prepare("
                INSERT INTO users (username, email, gta_user_id, gta_username, password_hash, is_active) 
                VALUES (?, ?, ?, ?, NULL, 1)
            ");
            $insertStmt->execute([
                $username,
                $gtaUsername . '@gta.world', // Placeholder email
                $gtaUserId,
                $gtaUsername
            ]);
            
            $newUserId = $db->lastInsertId();
            
            // Oturumu başlat
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $gtaUsername . '@gta.world';
            $_SESSION['is_admin'] = 0;
            
            $_SESSION['message'] = 'GTA World hesabınızla kayıt oldunuz! Hoş geldiniz, ' . $username . '!';
            $_SESSION['message_type'] = 'success';
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Hata sayfası
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth Hatası - Sociaera</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>
    <div class="form-container" style="margin-top: 100px;">
        <h2>❌ Giriş Başarısız</h2>
        <div class="alert alert-error">
            <?php echo escape($error); ?>
        </div>
        <a href="<?php echo BASE_URL; ?>/login" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
            Tekrar Dene
        </a>
    </div>
</body>
</html>
