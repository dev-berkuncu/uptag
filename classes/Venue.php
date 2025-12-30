<?php
/**
 * Mekan Sınıfı
 */

class Venue {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Aktif mekanları listeler
     */
    public function getActiveVenues($search = null, $limit = null, $offset = 0) {
        $sql = "SELECT * FROM venues WHERE is_active = 1";
        $params = [];
        
        if ($search) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY name ASC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Mekan detayını getirir
     */
    public function getVenueById($venueId) {
        $stmt = $this->db->prepare("SELECT * FROM venues WHERE id = ?");
        $stmt->execute([$venueId]);
        return $stmt->fetch();
    }
    
    /**
     * Mekan oluşturur (admin)
     */
    public function createVenue($name, $description = null, $address = null) {
        if (empty($name)) {
            return ['success' => false, 'message' => 'Mekan adı zorunludur.'];
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO venues (name, description, address)
            VALUES (?, ?, ?)
        ");
        
        if ($stmt->execute([$name, $description, $address])) {
            $venueId = $this->db->lastInsertId();
            logAdminAction('venue_create', 'venue', $venueId, "Mekan oluşturuldu: $name");
            return ['success' => true, 'message' => 'Mekan başarıyla oluşturuldu.', 'id' => $venueId];
        }
        
        return ['success' => false, 'message' => 'Mekan oluşturulurken bir hata oluştu.'];
    }
    
    /**
     * Mekan günceller (admin)
     */
    public function updateVenue($venueId, $name, $description = null, $address = null, $isActive = 1) {
        $stmt = $this->db->prepare("
            UPDATE venues
            SET name = ?, description = ?, address = ?, is_active = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $description, $address, $isActive, $venueId])) {
            logAdminAction('venue_update', 'venue', $venueId, "Mekan güncellendi");
            return ['success' => true, 'message' => 'Mekan başarıyla güncellendi.'];
        }
        
        return ['success' => false, 'message' => 'Mekan güncellenirken bir hata oluştu.'];
    }
    
    /**
     * Mekan siler (admin)
     */
    public function deleteVenue($venueId) {
        // Önce check-in'leri kontrol et
        $checkStmt = $this->db->prepare("SELECT COUNT(*) as count FROM checkins WHERE venue_id = ?");
        $checkStmt->execute([$venueId]);
        $result = $checkStmt->fetch();
        
        if ($result['count'] > 0) {
            // Check-in varsa pasife al
            $this->updateVenue($venueId, null, null, null, 0);
            logAdminAction('venue_deactivate', 'venue', $venueId, "Mekan pasife alındı (check-in'ler mevcut)");
            return ['success' => true, 'message' => 'Mekan pasife alındı (check-in kayıtları korundu).'];
        }
        
        // Check-in yoksa sil
        $stmt = $this->db->prepare("DELETE FROM venues WHERE id = ?");
        if ($stmt->execute([$venueId])) {
            logAdminAction('venue_delete', 'venue', $venueId, "Mekan silindi");
            return ['success' => true, 'message' => 'Mekan başarıyla silindi.'];
        }
        
        return ['success' => false, 'message' => 'Mekan silinirken bir hata oluştu.'];
    }
    
    /**
     * Mekanın check-in sayısını getirir
     */
    public function getCheckinCount($venueId, $weekStart = null, $weekEnd = null) {
        $sql = "SELECT COUNT(*) as count FROM checkins WHERE venue_id = ? AND is_excluded_from_leaderboard = 0";
        $params = [$venueId];
        
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
    
    /**
     * Tüm mekanları listeler (admin)
     */
    public function getAllVenues($search = null) {
        $sql = "SELECT v.*, 
                (SELECT COUNT(*) FROM checkins WHERE venue_id = v.id AND is_excluded_from_leaderboard = 0) as total_checkins
                FROM venues v";
        $params = [];
        
        if ($search) {
            $sql .= " WHERE (v.name LIKE ? OR v.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY v.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}


