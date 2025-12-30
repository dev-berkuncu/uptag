<?php
/**
 * Check-in Sınıfı
 */

class Checkin {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check-in oluşturur (cooldown ve rate limit kontrolleri ile)
     */
    public function createCheckin($userId, $venueId, $note = null) {
        // Mekan kontrolü
        $venue = new Venue();
        $venueData = $venue->getVenueById($venueId);
        if (!$venueData || !$venueData['is_active']) {
            return ['success' => false, 'message' => 'Mekan bulunamadı veya aktif değil.'];
        }
        
        // Cooldown kontrolü (aynı mekana kısa sürede tekrar check-in)
        $cooldownSeconds = (int)getSetting('checkin_cooldown_seconds', 300);
        $cooldownStmt = $this->db->prepare("
            SELECT created_at
            FROM checkins
            WHERE user_id = ? AND venue_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $cooldownStmt->execute([$userId, $venueId]);
        $lastCheckin = $cooldownStmt->fetch();
        
        if ($lastCheckin) {
            $lastCheckinTime = strtotime($lastCheckin['created_at']);
            $timeSinceLastCheckin = time() - $lastCheckinTime;
            
            if ($timeSinceLastCheckin < $cooldownSeconds) {
                $remainingSeconds = $cooldownSeconds - $timeSinceLastCheckin;
                $remainingMinutes = ceil($remainingSeconds / 60);
                return [
                    'success' => false,
                    'message' => "Aynı mekana tekrar check-in yapmak için $remainingMinutes dakika beklemeniz gerekiyor."
                ];
            }
        }
        
        // Rate limit kontrolü (kısa sürede çok fazla check-in)
        $rateLimitCount = (int)getSetting('checkin_rate_limit_count', 10);
        $rateLimitWindow = (int)getSetting('checkin_rate_limit_window_seconds', 3600);
        $rateLimitStart = date('Y-m-d H:i:s', time() - $rateLimitWindow);
        
        $rateLimitStmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM checkins
            WHERE user_id = ? AND created_at >= ?
        ");
        $rateLimitStmt->execute([$userId, $rateLimitStart]);
        $rateLimitResult = $rateLimitStmt->fetch();
        
        if ($rateLimitResult['count'] >= $rateLimitCount) {
            return [
                'success' => false,
                'message' => "Çok fazla check-in yaptınız. Lütfen bir süre bekleyin."
            ];
        }
        
        // Check-in oluştur
        $stmt = $this->db->prepare("
            INSERT INTO checkins (user_id, venue_id, note)
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$userId, $venueId, $note])) {
            return [
                'success' => true,
                'message' => 'Check-in başarıyla oluşturuldu!',
                'id' => $this->db->lastInsertId()
            ];
        }
        
        return ['success' => false, 'message' => 'Check-in oluşturulurken bir hata oluştu.'];
    }
    
    /**
     * Kullanıcının check-in geçmişini getirir
     */
    public function getUserCheckins($userId, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT c.*, v.name as venue_name, v.address as venue_address
            FROM checkins c
            INNER JOIN venues v ON c.venue_id = v.id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check-in detayını getirir
     */
    public function getCheckinById($checkinId) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   u.username, u.email,
                   v.name as venue_name, v.address as venue_address
            FROM checkins c
            INNER JOIN users u ON c.user_id = u.id
            INNER JOIN venues v ON c.venue_id = v.id
            WHERE c.id = ?
        ");
        $stmt->execute([$checkinId]);
        return $stmt->fetch();
    }
    
    /**
     * Son check-in'leri listeler (admin)
     */
    public function getRecentCheckins($limit = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   u.username, u.email,
                   v.name as venue_name
            FROM checkins c
            INNER JOIN users u ON c.user_id = u.id
            INNER JOIN venues v ON c.venue_id = v.id
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check-in'i leaderboard'dan hariç tutar (admin)
     */
    public function excludeFromLeaderboard($checkinId, $reason = null) {
        $stmt = $this->db->prepare("
            UPDATE checkins
            SET is_excluded_from_leaderboard = 1
            WHERE id = ?
        ");
        
        if ($stmt->execute([$checkinId])) {
            logAdminAction('checkin_exclude', 'checkin', $checkinId, $reason);
            return ['success' => true, 'message' => 'Check-in leaderboard\'dan hariç tutuldu.'];
        }
        
        return ['success' => false, 'message' => 'İşlem başarısız.'];
    }
    
    /**
     * Check-in'i işaretler (admin)
     */
    public function flagCheckin($checkinId, $reason) {
        $stmt = $this->db->prepare("
            UPDATE checkins
            SET is_flagged = 1, flagged_reason = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$reason, $checkinId])) {
            logAdminAction('checkin_flag', 'checkin', $checkinId, $reason);
            return ['success' => true, 'message' => 'Check-in işaretlendi.'];
        }
        
        return ['success' => false, 'message' => 'İşlem başarısız.'];
    }
    
    /**
     * Check-in'i siler (admin)
     */
    public function deleteCheckin($checkinId) {
        $stmt = $this->db->prepare("DELETE FROM checkins WHERE id = ?");
        
        if ($stmt->execute([$checkinId])) {
            logAdminAction('checkin_delete', 'checkin', $checkinId, "Check-in silindi");
            return ['success' => true, 'message' => 'Check-in silindi.'];
        }
        
        return ['success' => false, 'message' => 'İşlem başarısız.'];
    }
}

