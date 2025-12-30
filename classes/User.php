<?php
/**
 * Kullanıcı Sınıfı
 */

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Kullanıcı kaydı oluşturur
     */
    public function register($username, $email, $password) {
        // Validasyon
        if (empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Tüm alanlar zorunludur.'];
        }
        
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => 'Kullanıcı adı 3-50 karakter arasında olmalıdır.'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Geçerli bir e-posta adresi giriniz.'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Şifre en az 6 karakter olmalıdır.'];
        }
        
        // Kullanıcı adı ve e-posta kontrolü
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Bu kullanıcı adı veya e-posta zaten kullanılıyor.'];
        }
        
        // Şifreyi hashle
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Kullanıcıyı kaydet
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash)
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$username, $email, $passwordHash])) {
            return ['success' => true, 'message' => 'Kayıt başarılı! Giriş yapabilirsiniz.'];
        }
        
        return ['success' => false, 'message' => 'Kayıt sırasında bir hata oluştu.'];
    }
    
    /**
     * Kullanıcı girişi yapar
     */
    public function login($username, $password) {
        $stmt = $this->db->prepare("
            SELECT id, username, email, password_hash, is_admin, is_active, banned_until
            FROM users
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
        }
        
        // Şifre kontrolü
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
        }
        
        // Aktiflik kontrolü
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Hesabınız askıya alınmış.'];
        }
        
        // Ban kontrolü
        if ($user['banned_until'] && strtotime($user['banned_until']) > time()) {
            $banDate = formatDate($user['banned_until'], true);
            return ['success' => false, 'message' => "Hesabınız $banDate tarihine kadar askıya alınmış."];
        }
        
        // Son giriş zamanını güncelle
        $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // Session oluştur
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        
        return ['success' => true, 'message' => 'Giriş başarılı!'];
    }
    
    /**
     * Kullanıcı çıkışı yapar
     */
    public function logout() {
        session_destroy();
        return true;
    }
    
    /**
     * Kullanıcı bilgilerini getirir
     */
    public function getUserById($userId) {
        $stmt = $this->db->prepare("
            SELECT id, username, tag, email, avatar, banner, created_at, last_login, is_active, is_admin, banned_until
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Kullanıcının check-in sayısını getirir
     */
    public function getCheckinCount($userId, $weekStart = null, $weekEnd = null) {
        $sql = "SELECT COUNT(*) as count FROM checkins WHERE user_id = ? AND is_excluded_from_leaderboard = 0";
        $params = [$userId];
        
        if ($weekStart && $weekEnd) {
            $sql .= " AND created_at >= ? AND created_at <= ?";
            $params[] = $weekStart;
            $params[] = $weekEnd;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }
}


