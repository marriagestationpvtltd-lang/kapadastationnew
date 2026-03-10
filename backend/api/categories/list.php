<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$db   = getDB();
$stmt = $db->prepare('SELECT id, name, type, description, image, status FROM categories WHERE status = ? ORDER BY type, name');
$active = 'active';
$stmt->bind_param('s', $active);
$stmt->execute();
$result = $stmt->get_result();

$grouped = [];
while ($row = $result->fetch_assoc()) {
    $type = $row['type'] ?? 'other';
    if (!isset($grouped[$type])) {
        $grouped[$type] = [];
    }
    $grouped[$type][] = $row;
}
$stmt->close();
$db->close();

sendResponse(['categories' => $grouped]);
