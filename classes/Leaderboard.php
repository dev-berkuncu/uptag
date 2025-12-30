<?php
/**
 * Leaderboard Sınıfı
 */

class Leaderboard {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Haftalık top kullanıcıları getirir
     */
    public function getTopUsers($limit = 20, $weekStart = null, $weekEnd = null) {
        if ($weekStart === null || $weekEnd === null) {
            $weekRange = getWeekRange();
            $weekStart = $weekRange['start'];
            $weekEnd = $weekRange['end'];
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                COUNT(c.id) as checkin_count,
                MIN(c.created_at) as first_checkin
            FROM users u
            INNER JOIN checkins c ON u.id = c.user_id
            WHERE c.created_at >= ? 
              AND c.created_at <= ?
              AND c.is_excluded_from_leaderboard = 0
              AND u.is_active = 1
              AND u.username != 'GTAW'
              AND (u.banned_until IS NULL OR u.banned_until < NOW())
            GROUP BY u.id, u.username, u.email
            ORDER BY checkin_count DESC, first_checkin ASC
            LIMIT ?
        ");
        
        $stmt->execute([$weekStart, $weekEnd, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Haftalık top mekanları getirir
     */
    public function getTopVenues($limit = 20, $weekStart = null, $weekEnd = null) {
        if ($weekStart === null || $weekEnd === null) {
            $weekRange = getWeekRange();
            $weekStart = $weekRange['start'];
            $weekEnd = $weekRange['end'];
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                v.id,
                v.name,
                v.address,
                COUNT(c.id) as checkin_count,
                MIN(c.created_at) as first_checkin
            FROM venues v
            INNER JOIN checkins c ON v.id = c.venue_id
            WHERE c.created_at >= ? 
              AND c.created_at <= ?
              AND c.is_excluded_from_leaderboard = 0
              AND v.is_active = 1
            GROUP BY v.id, v.name, v.address
            ORDER BY checkin_count DESC, first_checkin ASC
            LIMIT ?
        ");
        
        $stmt->execute([$weekStart, $weekEnd, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Haftalık aralık bilgisini getirir
     */
    public function getWeekInfo() {
        $weekRange = getWeekRange();
        return [
            'start' => $weekRange['start'],
            'end' => $weekRange['end'],
            'start_formatted' => formatDate($weekRange['start'], true),
            'end_formatted' => formatDate($weekRange['end'], true)
        ];
    }
}


