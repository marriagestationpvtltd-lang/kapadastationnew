<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    sendError('Valid product ID is required');
}

$db   = getDB();
$stmt = $db->prepare(
    'SELECT p.id, p.name, p.description, p.size, p.color, p.rental_price, p.deposit_amount,
            p.stock, p.images, p.status, p.created_at,
            c.id AS category_id, c.name AS category_name, c.type AS category_type
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.id = ? LIMIT 1'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$result  = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();
$db->close();

if (!$product) {
    sendError('Product not found', 404);
}

$product['images'] = json_decode($product['images'] ?? '[]', true) ?: [];

sendResponse(['success' => true, 'product' => $product]);
