<?php
/**
 * Karakter Seçim Sayfası
 * OAuth ile giriş yapan kullanıcılar buradan karakter seçer
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

// OAuth verisi yoksa login'e yönlendir
if (!isset($_SESSION['oauth_user_data']) || !isset($_SESSION['oauth_characters'])) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$userData = $_SESSION['oauth_user_data'];
$characters = $_SESSION['oauth_characters'];
$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['character_id'])) {
    $selectedCharId = (int) $_POST['character_id'];

    // Seçilen karakter geçerli mi?
    $selectedChar = null;
    foreach ($characters as $char) {
        if ($char['id'] === $selectedCharId) {
            $selectedChar = $char;
            break;
        }
    }

    if (!$selectedChar) {
        $error = 'Geçersiz karakter seçimi.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            $gtaUserId = $userData['id'];
            $gtaUsername = $userData['username'];
            $characterName = $selectedChar['firstname'] . ' ' . $selectedChar['lastname'];

            // Bu GTA kullanıcısı daha önce kayıtlı mı?
            $stmt = $db->prepare("SELECT * FROM users WHERE gta_user_id = ?");
            $stmt->execute([$gtaUserId]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                // Mevcut kullanıcı - karakter bilgilerini güncelle ve giriş yap

                // Kullanıcı adı benzersiz olmalı (kendi kullanıcı adı değilse kontrol et)
                $username = $characterName;
                if ($existingUser['username'] !== $username) {
                    $checkUsername = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $checkUsername->execute([$username, $existingUser['id']]);
                    $counter = 1;
                    $originalUsername = $username;
                    while ($checkUsername->fetch()) {
                        $username = $originalUsername . $counter;
                        $checkUsername->execute([$username, $existingUser['id']]);
                        $counter++;
                    }
                }

                $updateStmt = $db->prepare("
                    UPDATE users SET 
                        gta_character_id = ?,
                        gta_character_name = ?,
                        username = ?,
                        last_login = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $selectedChar['id'],
                    $characterName, // Orijinal karakter adı (gta_character_name)
                    $username,      // Benzersiz site kullanıcı adı (username)
                    $existingUser['id']
                ]);

                session_regenerate_id(true); // Session fixation koruması
                $_SESSION['user_id'] = $existingUser['id'];
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $existingUser['email'];
                $_SESSION['is_admin'] = $existingUser['is_admin'];

                $_SESSION['message'] = 'Hoş geldin, ' . $username . '!';
                $_SESSION['message_type'] = 'success';
            } else {
                // Yeni kullanıcı - kayıt oluştur

                // Kullanıcı adı benzersiz olmalı check et
                $username = $characterName;
                $checkUsername = $db->prepare("SELECT id FROM users WHERE username = ?");
                $checkUsername->execute([$username]);
                $counter = 1;
                $originalUsername = $username;
                while ($checkUsername->fetch()) {
                    $username = $originalUsername . $counter;
                    $checkUsername->execute([$username]);
                    $counter++;
                }

                $insertStmt = $db->prepare("
                    INSERT INTO users (username, email, gta_user_id, gta_username, gta_character_id, gta_character_name, password_hash, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, NULL, 1)
                ");
                $insertStmt->execute([
                    $username,
                    $gtaUsername . '@gta.world',
                    $gtaUserId,
                    $gtaUsername,
                    $selectedChar['id'],
                    $characterName
                ]);

                $newUserId = $db->lastInsertId();

                session_regenerate_id(true); // Session fixation koruması
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $gtaUsername . '@gta.world';
                $_SESSION['is_admin'] = 0;

                $_SESSION['message'] = 'Hoş geldin, ' . $username . '! Hesabın oluşturuldu.';
                $_SESSION['message_type'] = 'success';
            }

            // OAuth session verilerini temizle
            unset($_SESSION['oauth_user_data']);
            unset($_SESSION['oauth_characters']);

            header('Location: ' . BASE_URL . '/dashboard');
            exit;

        } catch (Exception $e) {
            $error = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karakter Seç -
        <?php echo SITE_NAME; ?>
    </title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
    <style>
        .character-select-container {
            max-width: 800px;
            margin: 60px auto;
            padding: 2rem;
        }

        .character-select-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .character-select-header h1 {
            color: #fff;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .character-select-header p {
            color: #aaa;
            font-size: 1rem;
        }

        .character-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .character-card {
            background: linear-gradient(145deg, #1a1a2e 0%, #16213e 100%);
            border: 2px solid #2a2a4a;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .character-card:hover {
            border-color: #ff6b35;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.2);
        }

        .character-card.selected {
            border-color: #ff6b35;
            background: linear-gradient(145deg, #2a1a1a 0%, #1e1a2e 100%);
        }

        .character-card.selected::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff6b35;
            color: #fff;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
        }

        .character-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #fff;
            font-weight: bold;
        }

        .character-name {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .character-id {
            color: #666;
            font-size: 0.8rem;
        }

        .submit-container {
            text-align: center;
            margin-top: 2rem;
        }

        .btn-select {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: #fff;
            border: none;
            padding: 1rem 3rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-select:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(255, 107, 53, 0.4);
        }

        .btn-select:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .alert-error {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid #ff4444;
            color: #ff4444;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .welcome-user {
            background: rgba(255, 107, 53, 0.1);
            border: 1px solid rgba(255, 107, 53, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #ff6b35;
        }

        @media (max-width: 600px) {
            .character-select-container {
                padding: 1rem;
                margin: 20px auto;
            }

            .character-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="character-select-container">
        <div class="character-select-header">
            <h1>🎮 Karakter Seç</h1>
            <p>Sitede kullanmak istediğin karakteri seç</p>
        </div>

        <div class="welcome-user">
            👋 Merhaba, <strong>
                <?php echo htmlspecialchars($userData['username']); ?>
            </strong>!
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="characterForm">
            <input type="hidden" name="character_id" id="selectedCharacterId" value="">

            <div class="character-grid">
                <?php foreach ($characters as $char): ?>
                    <div class="character-card" data-id="<?php echo $char['id']; ?>"
                        onclick="selectCharacter(this, <?php echo $char['id']; ?>)">
                        <div class="character-avatar">
                            <?php echo strtoupper(substr($char['firstname'], 0, 1) . substr($char['lastname'], 0, 1)); ?>
                        </div>
                        <div class="character-name">
                            <?php echo htmlspecialchars($char['firstname'] . ' ' . $char['lastname']); ?>
                        </div>
                        <div class="character-id">ID:
                            <?php echo $char['id']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="submit-container">
                <button type="submit" class="btn-select" id="submitBtn" disabled>
                    Seçilen Karakterle Devam Et
                </button>
            </div>
        </form>
    </div>

    <script>
        function selectCharacter(element, charId) {
            // Tüm kartlardan selected sınıfını kaldır
            document.querySelectorAll('.character-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Seçilen karta selected sınıfını ekle
            element.classList.add('selected');

            // Hidden input'u güncelle
            document.getElementById('selectedCharacterId').value = charId;

            // Butonu aktifleştir
            document.getElementById('submitBtn').disabled = false;
        }
    </script>
</body>

</html>