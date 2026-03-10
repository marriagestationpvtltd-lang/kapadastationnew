<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../helpers/upload.php');
require_once(__DIR__ . '/../../middleware/auth.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

requireAuth();

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
if ($productId <= 0) {
    sendError('Valid product_id is required');
}

if (!isset($_FILES['user_photo']) || $_FILES['user_photo']['error'] !== UPLOAD_ERR_OK) {
    sendError('user_photo file is required');
}

$userPhotoPath = handleFileUpload($_FILES['user_photo'], 'tryon');
if (!$userPhotoPath) {
    sendError('Failed to upload photo. Ensure it is a JPEG or PNG under 5MB.');
}

$db   = getDB();
$stmt = $db->prepare('SELECT id, images FROM products WHERE id = ? AND status = ? LIMIT 1');
$active = 'active';
$stmt->bind_param('is', $productId, $active);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();
$db->close();

if (!$product) {
    sendError('Product not found', 404);
}

$productImages  = json_decode($product['images'] ?? '[]', true) ?: [];
$productImgPath = $productImages[0] ?? null;

$userPhotoUrl    = BASE_URL . '/' . $userPhotoPath;
$productImageUrl = $productImgPath ? BASE_URL . '/' . $productImgPath : null;

sendResponse([
    'success'           => true,
    'user_photo_url'    => $userPhotoUrl,
    'product_image_url' => $productImageUrl,
    'tryon_instructions' => 'frontend_composite',
    'message'           => 'Use these two images to create a virtual try-on overlay',
    'overlay_config'    => [
        'user_photo'    => $userPhotoUrl,
        'product_image' => $productImageUrl,
        'blend_mode'    => 'overlay',
        'opacity'       => 0.85
    ]
]);
