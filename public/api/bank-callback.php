<?php
/**
 * GTA World Bankacılık API Callback Endpoint
 * Bu endpoint GTA World bankacılık sisteminden gelen webhook bildirimlerini işler
 */

require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Gelen veriyi al
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log the incoming request for debugging
$logFile = __DIR__ . '/../../logs/bank_callbacks.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $input . "\n", FILE_APPEND);

// Veri doğrulama
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // API dokümantasyonu gelince burayı güncelleyeceğiz
    // Şimdilik temel yapı hazır
    
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'deposit':
            // Para yatırma
            $userId = (int)($data['user_id'] ?? 0);
            $amount = (float)($data['amount'] ?? 0);
            $referenceId = $data['reference_id'] ?? null;
            
            if ($userId <= 0 || $amount <= 0) {
                throw new Exception('Invalid user_id or amount');
            }
            
            $db->beginTransaction();
            
            // Cüzdanı güncelle veya oluştur
            $stmt = $db->prepare("
                INSERT INTO wallets (user_id, balance) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE balance = balance + ?
            ");
            $stmt->execute([$userId, $amount, $amount]);
            
            // İşlemi kaydet
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, reference_id, description)
                VALUES (?, 'deposit', ?, ?, 'GTA World para yatırma')
            ");
            $stmt->execute([$userId, $amount, $referenceId]);
            
            $db->commit();
            
            echo json_encode(['success' => true, 'message' => 'Deposit completed']);
            break;
            
        case 'withdraw':
            // Para çekme
            $userId = (int)($data['user_id'] ?? 0);
            $amount = (float)($data['amount'] ?? 0);
            $referenceId = $data['reference_id'] ?? null;
            
            if ($userId <= 0 || $amount <= 0) {
                throw new Exception('Invalid user_id or amount');
            }
            
            $db->beginTransaction();
            
            // Bakiye kontrolü
            $stmt = $db->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch();
            
            if (!$wallet || $wallet['balance'] < $amount) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => 'Insufficient balance']);
                exit;
            }
            
            // Bakiyeyi düş
            $stmt = $db->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
            $stmt->execute([$amount, $userId]);
            
            // İşlemi kaydet
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, reference_id, description)
                VALUES (?, 'withdraw', ?, ?, 'GTA World para çekme')
            ");
            $stmt->execute([$userId, $amount, $referenceId]);
            
            $db->commit();
            
            echo json_encode(['success' => true, 'message' => 'Withdrawal completed']);
            break;
            
        default:
            // Bilinmeyen action - log'a yaz ve başarılı döndür
            echo json_encode(['success' => true, 'message' => 'Callback received', 'action' => $action]);
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    // Hatayı logla
    file_put_contents($logFile, date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
