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

// Try optional auth (guest bookings allowed)
$authUser = null;
$headers  = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
if (!empty($authHeader) && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $payload = validateJWT($matches[1]);
    if ($payload) {
        $authUser = $payload;
    }
}

// Use $_POST for multipart/form-data
$productId    = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
// Accept both start_date/end_date (frontend) and rental_start/rental_end (API standard)
$rentalStart  = trim($_POST['rental_start'] ?? $_POST['start_date'] ?? '');
$rentalEnd    = trim($_POST['rental_end']   ?? $_POST['end_date']   ?? '');
$notes        = trim($_POST['notes'] ?? '');

if ($productId <= 0) {
    sendError('Valid product_id is required');
}
if (empty($rentalStart) || empty($rentalEnd)) {
    sendError('rental_start and rental_end are required');
}

// Validate dates
$today       = new DateTime('today');
$startDate   = DateTime::createFromFormat('Y-m-d', $rentalStart);
$endDate     = DateTime::createFromFormat('Y-m-d', $rentalEnd);

if (!$startDate || !$endDate) {
    sendError('Dates must be in YYYY-MM-DD format');
}
if ($startDate < $today) {
    sendError('rental_start must be today or a future date');
}
if ($endDate <= $startDate) {
    sendError('rental_end must be after rental_start');
}

$db = getDB();

// Fetch product
$stmt = $db->prepare('SELECT id, name, rental_price, deposit_amount, stock, status FROM products WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    $db->close();
    sendError('Product not found', 404);
}
if ($product['status'] !== 'active' || (int)$product['stock'] <= 0) {
    $db->close();
    sendError('Product is not available for rental', 422);
}

// Customer details
$userId        = null;
$customerName  = trim($_POST['customer_name'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');

if ($authUser) {
    $userId = (int)$authUser['user_id'];
    if (empty($customerName) || empty($customerEmail) || empty($customerPhone)) {
        $userStmt = $db->prepare('SELECT name, email, phone FROM users WHERE id = ? LIMIT 1');
        $userStmt->bind_param('i', $userId);
        $userStmt->execute();
        $userData = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        if ($userData) {
            $customerName  = $customerName  ?: $userData['name'];
            $customerEmail = $customerEmail ?: $userData['email'];
            $customerPhone = $customerPhone ?: $userData['phone'];
        }
    }
} else {
    if (empty($customerName) || empty($customerEmail) || empty($customerPhone)) {
        $db->close();
        sendError('customer_name, customer_email, and customer_phone are required for guest bookings');
    }
}

if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $db->close();
    sendError('Invalid customer email format');
}

// Calculate amounts
$totalDays    = $startDate->diff($endDate)->days;
$rentalAmount = $totalDays * (float)$product['rental_price'];
$depositAmount = (float)$product['deposit_amount'];

// Handle file uploads
$customerPhotoPath = null;
$idDocumentPath    = null;

if (isset($_FILES['customer_photo']) && $_FILES['customer_photo']['error'] === UPLOAD_ERR_OK) {
    $customerPhotoPath = handleFileUpload($_FILES['customer_photo'], 'photos');
    if (!$customerPhotoPath) {
        $db->close();
        sendError('Customer photo upload failed. Ensure it is a JPEG or PNG image under 5 MB.');
    }
}

if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
    $idDocumentPath = handleFileUpload($_FILES['id_document'], 'id_documents');
    if (!$idDocumentPath) {
        $db->close();
        sendError('ID document upload failed. Ensure it is a JPEG or PNG image under 5 MB.');
    }
}

// Generate unique tracking code (max 10 attempts)
$trackingCode = null;
$maxAttempts  = 10;
for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code   = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $checkStmt = $db->prepare('SELECT id FROM bookings WHERE tracking_code = ? LIMIT 1');
    $checkStmt->bind_param('s', $code);
    $checkStmt->execute();
    $checkStmt->store_result();
    $exists = $checkStmt->num_rows > 0;
    $checkStmt->close();
    if (!$exists) {
        $trackingCode = $code;
        break;
    }
}

if ($trackingCode === null) {
    $db->close();
    sendError('Unable to generate unique tracking code. Please try again.', 500);
}

$status = 'pending';
$stmt   = $db->prepare(
    'INSERT INTO bookings (user_id, product_id, tracking_code, customer_name, customer_email,
                           customer_phone, customer_photo, id_document, rental_start, rental_end,
                           total_days, rental_amount, deposit_amount, status, notes, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->bind_param(
    'iissssssssiidss',
    $userId, $productId, $trackingCode,
    $customerName, $customerEmail, $customerPhone,
    $customerPhotoPath, $idDocumentPath,
    $rentalStart, $rentalEnd,
    $totalDays, $rentalAmount, $depositAmount,
    $status, $notes
);

if (!$stmt->execute()) {
    $stmt->close();
    $db->close();
    sendError('Booking creation failed. Please try again.', 500);
}

$bookingId = $stmt->insert_id;
$stmt->close();
$db->close();

sendResponse([
    'success'       => true,
    'tracking_code' => $trackingCode,
    'booking'       => [
        'id'             => $bookingId,
        'product_id'     => $productId,
        'product_name'   => $product['name'],
        'tracking_code'  => $trackingCode,
        'customer_name'  => $customerName,
        'customer_email' => $customerEmail,
        'customer_phone' => $customerPhone,
        'rental_start'   => $rentalStart,
        'rental_end'     => $rentalEnd,
        'total_days'     => $totalDays,
        'rental_amount'  => $rentalAmount,
        'deposit_amount' => $depositAmount,
        'status'         => $status
    ]
], 201);
