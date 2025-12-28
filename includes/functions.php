<?php
/**
 * Yardımcı Fonksiyonlar
 */

/**
 * Kullanıcının giriş yapıp yapmadığını kontrol eder
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Kullanıcının admin olup olmadığını kontrol eder
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Admin kontrolü yapar, değilse yönlendirir
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Giriş kontrolü yapar, değilse yönlendirir
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Güvenli çıktı (XSS koruması)
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Haftanın başlangıç ve bitiş zamanını döndürür (Europe/Istanbul)
 */
function getWeekRange($date = null) {
    $timezone = new DateTimeZone('Europe/Istanbul');
    
    if ($date === null) {
        $now = new DateTime('now', $timezone);
    } else {
        $now = new DateTime($date, $timezone);
    }
    
    // Pazartesi gününü bul
    $dayOfWeek = (int)$now->format('N'); // 1=Monday, 7=Sunday
    $daysToMonday = ($dayOfWeek == 1) ? 0 : 1 - $dayOfWeek;
    
    $weekStart = clone $now;
    $weekStart->modify($daysToMonday . ' days');
    $weekStart->setTime(0, 0, 0);
    
    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 days');
    $weekEnd->setTime(23, 59, 59);
    
    return [
        'start' => $weekStart->format('Y-m-d H:i:s'),
        'end' => $weekEnd->format('Y-m-d H:i:s'),
        'start_datetime' => $weekStart,
        'end_datetime' => $weekEnd
    ];
}

/**
 * Tarih formatla (Türkçe)
 */
function formatDate($date, $includeTime = false) {
    $timestamp = strtotime($date);
    $format = $includeTime ? 'd.m.Y H:i' : 'd.m.Y';
    return date($format, $timestamp);
}

/**
 * Zaman farkını insan okunabilir formatta gösterir
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 604800) return floor($time/86400) . ' gün önce';
    
    return formatDate($datetime);
}

/**
 * Admin log kaydı oluşturur
 */
function logAdminAction($actionType, $targetType, $targetId = null, $details = null) {
    if (!isAdmin()) return false;
    
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO admin_logs (admin_id, action_type, target_type, target_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $ipAddress = null; // Privacy enhancement: Do not log IP address
    
    return $stmt->execute([
        $_SESSION['user_id'],
        $actionType,
        $targetType,
        $targetId,
        $details,
        $ipAddress
    ]);
}

/**
 * Sistem ayarı getirir
 */
function getSetting($key, $default = null) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    return $result ? $result['setting_value'] : $default;
}

/**
 * Sistem ayarı günceller
 */
function setSetting($key, $value) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    
    return $stmt->execute([$key, $value, $value]);
}

/**
 * CSRF Token Oluştur
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Token Doğrula
 */
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF Token Input Alanı
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . escape(generateCsrfToken()) . '">';
}

