<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Giriş gerekli
requireLogin();

$pageTitle = 'Mekan Ekle';
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$success = '';
$error = '';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    // Validasyon
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif (empty($name)) {
        $error = 'Mekan adı gereklidir.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error = 'Mekan adı 2-100 karakter arasında olmalıdır.';
    } elseif (empty($website)) {
        $error = 'Facebrowser linki gereklidir.';
    } elseif (!filter_var($website, FILTER_VALIDATE_URL)) {
        $error = 'Geçersiz Facebrowser adresi.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Aynı isimde mekan var mı kontrol et
            $checkStmt = $db->prepare("SELECT id FROM venues WHERE name = ?");
            $checkStmt->execute([$name]);
            
            if ($checkStmt->fetch()) {
                $error = 'Bu isimde bir mekan zaten mevcut.';
            } else {
                // Mekan ekle (onay bekliyor - is_active = 0)
                $insertStmt = $db->prepare("
                    INSERT INTO venues (name, description, address, website, is_active, created_by, created_at) 
                    VALUES (?, ?, ?, ?, 0, ?, NOW())
                ");
                $insertStmt->execute([$name, $description, $address, $website, $userId]);
                
                $success = 'Mekanınız başarıyla gönderildi! Onay sürecinden sonra yayınlanacaktır.';
                
                // Formu temizle
                $name = $description = $address = $phone = $website = $category = '';
            }
        } catch (PDOException $e) {
            // created_by kolonu yoksa ekle
            if (strpos($e->getMessage(), 'created_by') !== false) {
                try {
                    $db->exec("ALTER TABLE venues ADD COLUMN created_by INT DEFAULT NULL");
                    // Tekrar dene
                    $insertStmt = $db->prepare("
                        INSERT INTO venues (name, description, address, website, is_active, created_by, created_at) 
                        VALUES (?, ?, ?, ?, 0, ?, NOW())
                    ");
                    $insertStmt->execute([$name, $description, $address, $website, $userId]);
                    $success = 'Mekanınız başarıyla gönderildi! Onay sürecinden sonra yayınlanacaktır.';
                    $name = $description = $address = $phone = $website = $category = '';
                } catch (PDOException $e2) {
                    $error = 'Bir hata oluştu. Lütfen tekrar deneyin.';
                }
            } else {
                $error = 'Bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Uptag'a mekan ekle - İşletmenizi sisteme kaydedin">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'venues'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="add-venue-page">
        <div class="add-venue-container">
            
            <!-- Header -->
            <div class="add-venue-header">
                <h1>🏢 Mekan Ekle</h1>
                <p>İşletmenizi Uptag'a ekleyin ve müşterilerinizin check-in yapmasını sağlayın</p>
            </div>

            <!-- Info Box -->
            <div class="add-venue-info">
                <div class="info-icon">ℹ️</div>
                <div class="info-text">
                    <strong>Nasıl çalışır?</strong>
                    <p>Mekanınızı ekledikten sonra, ekibimiz bilgileri doğrulayacak ve onaylayacaktır. Onay sürecinden sonra mekanınız Uptag'da yayınlanacak ve kullanıcılar check-in yapabilecektir.</p>
                </div>
            </div>

            <!-- Form -->
            <form method="POST" class="add-venue-form">
                <?php echo csrfField(); ?>
                
                <?php if ($error): ?>
                    <div class="form-error"><?php echo escape($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="form-success"><?php echo escape($success); ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Mekan Adı <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo escape($name ?? ''); ?>" placeholder="Örn: Pillbox Casino" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="category">Kategori</label>
                    <select id="category" name="category">
                        <option value="">Seçiniz...</option>
                        <option value="restoran">Restoran</option>
                        <option value="kafe">Kafe</option>
                        <option value="bar">Bar & Gece Kulübü</option>
                        <option value="otel">Otel & Konaklama</option>
                        <option value="alisveris">Alışveriş</option>
                        <option value="eglence">Eğlence</option>
                        <option value="spor">Spor & Fitness</option>
                        <option value="saglik">Sağlık & Güzellik</option>
                        <option value="kultur">Kültür & Sanat</option>
                        <option value="diger">Diğer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Açıklama</label>
                    <textarea id="description" name="description" rows="3" placeholder="Mekanınız hakkında kısa bir açıklama..." maxlength="500"><?php echo escape($description ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="address">Adres</label>
                    <input type="text" id="address" name="address" value="<?php echo escape($address ?? ''); ?>" placeholder="Tam adres">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Telefon</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo escape($phone ?? ''); ?>" placeholder="XXX XXX XX XX">
                    </div>
                    <div class="form-group">
                        <label for="website">Facebrowser <span class="required">*</span></label>
                        <input type="url" id="website" name="website" value="<?php echo escape($website ?? ''); ?>" placeholder="https://..." required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">Mekan Ekle</button>
                    <a href="dashboard" class="btn btn-secondary">İptal</a>
                </div>

            </form>

        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Uptag. Tüm hakları saklıdır.</p>
        </div>
    </footer>

</body>
</html>
