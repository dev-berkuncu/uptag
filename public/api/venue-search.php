<?php
/**
 * Venue Search API - Autocomplete for @ mentions
 * Returns JSON list of venues matching search query
 */
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

// Check if logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) < 1) {
    echo json_encode(['venues' => []]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Search venues by name
    $stmt = $db->prepare("
        SELECT id, name, address 
        FROM venues 
        WHERE is_active = 1 AND name LIKE ? 
        ORDER BY name ASC 
        LIMIT 10
    ");
    $stmt->execute(['%' . $query . '%']);
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['venues' => $venues]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
