<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Debug: Check tag value for user
$db = Database::getInstance()->getConnection();

$userId = $_GET['id'] ?? 1;

$stmt = $db->prepare("SELECT id, username, tag FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode([
    'user_id' => $user['id'] ?? null,
    'username' => $user['username'] ?? null,
    'tag' => $user['tag'] ?? null,
    'tag_empty' => empty($user['tag']),
    'display_tag' => !empty($user['tag']) ? $user['tag'] : strtolower($user['username'] ?? '')
]);
