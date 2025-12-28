<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();

$pageTitle = 'Reklamsız Kullanım';
$username = $_SESSION['username'];

require_once '../includes/ads_logic.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Uptag Premium - Reklamsız deneyim için hemen abone ol!">
    <title><?php echo escape($pageTitle); ?> - Uptag</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
    <style>
        .premium-page {
            min-height: 100vh;
            padding-top: 100px;
            padding-bottom: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .premium-card {
            max-width: 500px;
            width: 90%;
            background: linear-gradient(145deg, rgba(26, 26, 26, 0.95), rgba(40, 40, 40, 0.9));
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 215, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .premium-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #FFD700, #FFA500, #FF8C00, #FFD700);
            background-size: 300% 100%;
            animation: shimmer 3s linear infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
        }
        
        .premium-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 24px;
            box-shadow: 0 10px 40px rgba(255, 215, 0, 0.3);
        }
        
        .premium-title {
            font-size: 2rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .premium-subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 32px;
        }
        
        .premium-features {
            list-style: none;
            padding: 0;
            margin: 0 0 32px 0;
        }
        
        .premium-feature {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .premium-feature:last-child {
            border-bottom: none;
        }
        
        .feature-icon {
            width: 44px;
            height: 44px;
            background: rgba(255, 215, 0, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        
        .feature-text {
            flex: 1;
        }
        
        .feature-text strong {
            display: block;
            font-size: 1rem;
            color: var(--text-white);
            margin-bottom: 2px;
        }
        
        .feature-text span {
            font-size: 0.85rem;
            color: var(--text-subtle);
        }
        
        .premium-price {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .price-tag {
            font-size: 3rem;
            font-weight: 800;
            color: #FFD700;
        }
        
        .price-period {
            font-size: 1rem;
            color: var(--text-muted);
        }
        
        .premium-btn {
            display: block;
            width: 100%;
            padding: 18px 24px;
            background: linear-gradient(135deg, #FFD700, #FFA500, #FF8C00);
            color: #1a1a1a;
            font-size: 1.1rem;
            font-weight: 700;
            text-align: center;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            box-shadow: 0 8px 30px rgba(255, 215, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .premium-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(255, 215, 0, 0.5);
        }
        
        .premium-note {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
            color: var(--text-subtle);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 24px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .back-link:hover {
            color: var(--text-white);
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = ''; require_once '../includes/navbar.php'; ?>

    <!-- Premium Page -->
    <div class="premium-page">
        <div class="premium-card">
            <div class="premium-icon">👑</div>
            <h1 class="premium-title">Uptag Premium</h1>
            <p class="premium-subtitle">Reklamsız ve kesintisiz bir deneyim için</p>
            
            <ul class="premium-features">
                <li class="premium-feature">
                    <div class="feature-icon">🚫</div>
                    <div class="feature-text">
                        <strong>Reklamsız Deneyim</strong>
                        <span>Sidebar ve feed reklamları tamamen kaldırılır</span>
                    </div>
                </li>
                <li class="premium-feature">
                    <div class="feature-icon">⚡</div>
                    <div class="feature-text">
                        <strong>Daha Hızlı Yükleme</strong>
                        <span>Reklam yükü olmadan sayfalar anında açılır</span>
                    </div>
                </li>
                <li class="premium-feature">
                    <div class="feature-icon">🎨</div>
                    <div class="feature-text">
                        <strong>Temiz Arayüz</strong>
                        <span>Dikkat dağıtıcı öğeler olmadan saf içerik</span>
                    </div>
                </li>
                <li class="premium-feature">
                    <div class="feature-icon">👑</div>
                    <div class="feature-text">
                        <strong>Premium Rozet</strong>
                        <span>Profilinizde özel premium rozeti görünsün</span>
                    </div>
                </li>
                <li class="premium-feature">
                    <div class="feature-icon">💪</div>
                    <div class="feature-text">
                        <strong>Platformu Destekle</strong>
                        <span>Uptag'ın gelişimine katkıda bulunun</span>
                    </div>
                </li>
            </ul>
            
            <div class="premium-price">
                <span class="price-tag">$10,000</span>
                <span class="price-period">/ haftalık</span>
            </div>
            
            <button class="premium-btn" onclick="alert('Ödeme sistemi yakında aktif olacak!')">
                ✨ Premium'a Geç
            </button>
            
            <p class="premium-note">
                İstediğin zaman iptal edebilirsin. Otomatik yenileme.
            </p>
            
            <a href="dashboard" class="back-link">← Dashboard'a Dön</a>
        </div>
    </div>

</body>
</html>
