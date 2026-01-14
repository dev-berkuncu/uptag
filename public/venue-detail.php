<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$venueId = (int)($_GET['id'] ?? 0);
$venue = new Venue();
$venueData = $venue->getVenueById($venueId);

if (!$venueData || !$venueData['is_active']) {
    $_SESSION['message'] = 'Mekan bulunamadı.';
    $_SESSION['message_type'] = 'error';
    header('Location: venues');
    exit;
}

$pageTitle = escape($venueData['name']);
$error = '';
$success = '';

// Check-in işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $note = trim($_POST['note'] ?? '');
        $checkin = new Checkin();
        $result = $checkin->createCheckin($_SESSION['user_id'], $venueId, $note);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Check-in sayısını al
$checkinCount = $venue->getCheckinCount($venueId);

// CSRF Token oluştur
$csrfToken = generateCsrfToken();

require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo escape($venueData['name']); ?> - Uptag'da check-in yap ve puan kazan!">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php require_once '../includes/head-bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
    <style>
        .venue-detail-card {
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
        }
        
        .venue-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .venue-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--orange-primary), var(--orange-accent));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        
        .venue-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-white);
            margin-bottom: 4px;
        }
        
        .venue-stats {
            display: flex;
            gap: 24px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .venue-stat {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .venue-info {
            margin-bottom: 24px;
        }
        
        .venue-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 16px;
        }
        
        .venue-address {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        
        .checkin-form {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            padding: 24px;
        }
        
        .checkin-form h3 {
            font-size: 1.1rem;
            margin-bottom: 16px;
            color: var(--text-white);
        }
        
        .checkin-textarea {
            width: 100%;
            padding: 14px 16px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            color: var(--text-white);
            font-size: 1rem;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 16px;
        }
        
        .checkin-textarea:focus {
            outline: none;
            border-color: var(--orange-primary);
        }
        
        .checkin-textarea::placeholder {
            color: var(--text-muted);
        }
        
        .btn-checkin {
            background: linear-gradient(135deg, var(--orange-primary), var(--orange-accent));
            color: white;
            font-weight: 600;
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-checkin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(192, 57, 1, 0.3);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            margin-bottom: 24px;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: var(--orange-accent);
        }
        
        .alert {
            padding: 14px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'venues'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            
            <a href="venues" class="back-link">← Mekanlara Dön</a>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo escape($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo escape($success); ?></div>
            <?php endif; ?>
            
            <!-- Venue Detail Card -->
            <div class="venue-detail-card">
                <div class="venue-header">
                    <div class="venue-icon">📍</div>
                    <div>
                        <h1 class="venue-title"><?php echo escape($venueData['name']); ?></h1>
                        <div class="venue-stats">
                            <span class="venue-stat">✔️ <?php echo $checkinCount; ?> check-in</span>
                        </div>
                    </div>
                </div>
                
                <div class="venue-info">
                    <?php if ($venueData['description']): ?>
                        <p class="venue-description"><?php echo nl2br(escape($venueData['description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($venueData['address']): ?>
                        <p class="venue-address">📍 <?php echo escape($venueData['address']); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Check-in Form -->
                <div class="checkin-form">
                    <h3>✨ Check-in Yap</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <textarea 
                            name="note" 
                            class="checkin-textarea" 
                            placeholder="Bu check-in hakkında bir not ekleyebilirsiniz... (Opsiyonel)"
                        ></textarea>
                        <button type="submit" name="checkin" class="btn-checkin">🎯 Check-in Yap</button>
                    </form>
                </div>
            </div>

        </main>

        <!-- Right Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-right.php'; ?>

    </div>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-about">
                <h3>Uptag</h3>
                <p>Uptag, sosyal keşif ve check-in platformudur. Favori mekanlarınızde anlarınızı paylaşın.</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Uptag. Tüm hakları saklıdır.</p>
        </div>
    </footer>

</body>
</html>

