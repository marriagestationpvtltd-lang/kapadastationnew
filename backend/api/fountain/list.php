<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$db = getDB();

$sql  = "SELECT id, title, subtitle, description, image_url, button_text, button_link,
                bg_color, text_color, sort_order
         FROM fountain
         WHERE status = 'active'
         ORDER BY sort_order ASC, id ASC";
$stmt = $db->prepare($sql);
if (!$stmt) {
    error_log('Prepare failed (fountain list): ' . $db->error);
    $db->close();
    sendError('Failed to retrieve fountain items', 500);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();
$db->close();

sendResponse(['success' => true, 'items' => $items]);
