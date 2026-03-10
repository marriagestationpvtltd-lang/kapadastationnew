<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../middleware/auth.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$authUser = requireAuth();
$userId   = (int)$authUser['user_id'];

$db   = getDB();
$stmt = $db->prepare(
    'SELECT b.id, b.tracking_code, b.rental_start, b.rental_end, b.total_days,
            b.rental_amount, b.deposit_amount, b.status, b.created_at,
            p.id AS product_id, p.name AS product_name, p.images AS product_images,
            p.color, p.size
     FROM bookings b
     JOIN products p ON b.product_id = p.id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result   = $stmt->get_result();
$bookings = [];

while ($row = $result->fetch_assoc()) {
    $images = json_decode($row['product_images'] ?? '[]', true) ?: [];
    $row['product_first_image'] = $images[0] ?? null;
    unset($row['product_images']);
    $bookings[] = $row;
}
$stmt->close();
$db->close();

sendResponse(['bookings' => $bookings]);
