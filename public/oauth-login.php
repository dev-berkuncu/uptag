<?php
/**
 * GTA World OAuth Login - Yönlendirme Sayfası
 * Kullanıcıyı GTA World UCP OAuth sayfasına yönlendirir
 */

require_once '../config/config.php';

// Zaten giriş yapmışsa ana sayfaya yönlendir
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

// OAuth ayarları - config.php'de tanımlanacak
$clientId = defined('OAUTH_CLIENT_ID') ? OAUTH_CLIENT_ID : '';
$redirectUri = defined('OAUTH_REDIRECT_URI') ? OAUTH_REDIRECT_URI : BASE_URL . '/oauth-callback';

// CSRF koruması için state oluştur
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

// OAuth ayarları eksikse hata göster
if (empty($clientId)) {
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>OAuth Yapılandırılmamış - Sociaera</title>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
    </head>
    <body>
        <div class="form-container" style="margin-top: 100px;">
            <h2>⚠️ OAuth Yapılandırılmamış</h2>
            <p style="color: var(--text-muted); text-align: center;">
                GTA World OAuth entegrasyonu henüz yapılandırılmamış.
                <br><br>
                Lütfen yönetici ile iletişime geçin.
            </p>
            <a href="<?php echo BASE_URL; ?>/login" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                Geri Dön
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// GTA World OAuth URL'sine yönlendir
$oauthUrl = 'https://ucp-tr.gta.world/oauth/authorize?' . http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => '',
    'state' => $state
]);

header('Location: ' . $oauthUrl);
exit;
