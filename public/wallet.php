<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireLogin();
require_once '../includes/ads_logic.php';

$pageTitle = 'Cüzdan';
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

$db = Database::getInstance()->getConnection();

// Kullanıcı bilgileri
$avatarUrl = null;
try {
    $userStmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userInfo = $userStmt->fetch();
    if (!empty($userInfo['avatar'])) {
        $avatarUrl = 'uploads/avatars/' . $userInfo['avatar'];
    }
} catch (PDOException $e) {}

// Cüzdan bakiyesi
$balance = 0;
try {
    $walletStmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch();
    if ($wallet) {
        $balance = (float)$wallet['balance'];
    }
} catch (PDOException $e) {
    // wallets tablosu yoksa devam et
}

// Son işlemler
$transactions = [];
try {
    $txStmt = $db->prepare("
        SELECT * FROM transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $txStmt->execute([$userId]);
    $transactions = $txStmt->fetchAll();
} catch (PDOException $e) {
    // transactions tablosu yoksa devam et
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cüzdanınız ve işlem geçmişiniz">
    <title><?php echo escape($pageTitle); ?> - Sociaera</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/style.css">
    <style>
        .wallet-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .wallet-balance-card {
            background: linear-gradient(135deg, var(--orange-accent) 0%, #ff6b35 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(192, 57, 1, 0.3);
        }
        
        .wallet-balance-label {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .wallet-balance-amount {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
        }
        
        .wallet-balance-currency {
            font-size: 1.5rem;
            opacity: 0.8;
        }
        
        .wallet-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .wallet-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .wallet-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .transactions-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--card-border);
        }
        
        .transactions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .transactions-header h2 {
            font-size: 1.3rem;
            color: var(--text-white);
        }
        
        .transaction-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--card-border);
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-right: 15px;
        }
        
        .transaction-icon.deposit {
            background: rgba(34, 197, 94, 0.2);
        }
        
        .transaction-icon.withdraw {
            background: rgba(239, 68, 68, 0.2);
        }
        
        .transaction-icon.transfer_in {
            background: rgba(59, 130, 246, 0.2);
        }
        
        .transaction-icon.transfer_out {
            background: rgba(249, 115, 22, 0.2);
        }
        
        .transaction-info {
            flex: 1;
        }
        
        .transaction-type {
            font-weight: 600;
            color: var(--text-white);
            margin-bottom: 3px;
        }
        
        .transaction-date {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .transaction-amount {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .transaction-amount.positive {
            color: #22c55e;
        }
        
        .transaction-amount.negative {
            color: #ef4444;
        }
        
        .empty-transactions {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        
        .empty-transactions-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <?php $activeNav = 'wallet'; require_once '../includes/navbar.php'; ?>

    <!-- MAIN LAYOUT -->
    <div class="main-layout">
        
        <!-- Left Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-left.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            
            <div class="wallet-container">
                
                <!-- Balance Card -->
                <div class="wallet-balance-card">
                    <div class="wallet-balance-label">Mevcut Bakiye</div>
                    <div class="wallet-balance-amount">
                        $<?php echo number_format($balance, 2, ',', '.'); ?>
                    </div>
                    <div class="wallet-actions">
                        <button class="wallet-action-btn" disabled title="Yakında">
                            💸 Para Gönder
                        </button>
                        <button class="wallet-action-btn" disabled title="Yakında">
                            📊 İstatistikler
                        </button>
                    </div>
                </div>

                <!-- Transactions -->
                <div class="transactions-section">
                    <div class="transactions-header">
                        <h2>📋 İşlem Geçmişi</h2>
                    </div>
                    
                    <?php if (empty($transactions)): ?>
                        <div class="empty-transactions">
                            <div class="empty-transactions-icon">💰</div>
                            <h3>Henüz işlem yok</h3>
                            <p>İşlemleriniz burada görünecek.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <div class="transaction-item">
                                <div class="transaction-icon <?php echo $tx['type']; ?>">
                                    <?php 
                                    $icons = [
                                        'deposit' => '📥',
                                        'withdraw' => '📤',
                                        'transfer_in' => '↘️',
                                        'transfer_out' => '↗️'
                                    ];
                                    echo $icons[$tx['type']] ?? '💵';
                                    ?>
                                </div>
                                <div class="transaction-info">
                                    <div class="transaction-type">
                                        <?php 
                                        $typeLabels = [
                                            'deposit' => 'Para Yatırma',
                                            'withdraw' => 'Para Çekme',
                                            'transfer_in' => 'Gelen Transfer',
                                            'transfer_out' => 'Giden Transfer'
                                        ];
                                        echo $typeLabels[$tx['type']] ?? $tx['type'];
                                        ?>
                                    </div>
                                    <div class="transaction-date">
                                        <?php echo formatDate($tx['created_at'], true); ?>
                                        <?php if (!empty($tx['description'])): ?>
                                            - <?php echo escape($tx['description']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?php echo in_array($tx['type'], ['deposit', 'transfer_in']) ? 'positive' : 'negative'; ?>">
                                    <?php echo in_array($tx['type'], ['deposit', 'transfer_in']) ? '+' : '-'; ?>
                                    $<?php echo number_format($tx['amount'], 2, ',', '.'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

        </main>

        <!-- Right Sponsor Sidebar -->
        <?php require_once '../includes/sidebar-right.php'; ?>

    </div>

    <!-- FOOTER -->
    <footer class="footer footer-minimal">
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Sociaera. Tüm hakları saklıdır.</p>
        </div>
    </footer>

</body>
</html>
