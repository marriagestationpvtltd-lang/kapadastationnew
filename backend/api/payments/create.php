<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../middleware/auth.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$authUser = requireAuth();
$userId   = (int)$authUser['user_id'];

$body            = json_decode(file_get_contents('php://input'), true);
$bookingId       = isset($body['booking_id']) ? (int)$body['booking_id'] : 0;
$type            = trim($body['type'] ?? '');
$method          = trim($body['method'] ?? '');
$amount          = isset($body['amount']) ? (float)$body['amount'] : 0;
$referenceNumber = trim($body['reference_number'] ?? '');
$notes           = trim($body['notes'] ?? '');

if ($bookingId <= 0) {
    sendError('Valid booking_id is required');
}

$validTypes = ['deposit', 'rental', 'refund'];
if (!in_array($type, $validTypes)) {
    sendError('type must be one of: deposit, rental, refund');
}

$validMethods = ['cash', 'upi', 'bank_transfer'];
if (!in_array($method, $validMethods)) {
    sendError('method must be one of: cash, upi, bank_transfer');
}

if ($amount <= 0) {
    sendError('amount must be a positive number');
}

$db = getDB();

// Verify booking exists and user has access (own booking or admin)
if ($authUser['role'] === 'admin') {
    $stmt = $db->prepare('SELECT id, status FROM bookings WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $bookingId);
} else {
    $stmt = $db->prepare('SELECT id, status FROM bookings WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $bookingId, $userId);
}
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    $db->close();
    sendError('Booking not found or access denied', 404);
}

// Insert payment
$stmt = $db->prepare(
    'INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, recorded_by, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->bind_param('issdssi', $bookingId, $type, $method, $amount, $referenceNumber, $notes, $userId);

if (!$stmt->execute()) {
    $stmt->close();
    $db->close();
    sendError('Failed to record payment', 500);
}

$paymentId = $stmt->insert_id;
$stmt->close();

// Auto-confirm booking on deposit payment
if ($type === 'deposit' && $booking['status'] === 'pending') {
    $updateStmt = $db->prepare('UPDATE bookings SET status = ? WHERE id = ?');
    $confirmed  = 'confirmed';
    $updateStmt->bind_param('si', $confirmed, $bookingId);
    $updateStmt->execute();
    $updateStmt->close();
}

$db->close();

sendResponse([
    'success' => true,
    'payment' => [
        'id'               => $paymentId,
        'booking_id'       => $bookingId,
        'type'             => $type,
        'method'           => $method,
        'amount'           => $amount,
        'reference_number' => $referenceNumber,
        'notes'            => $notes,
        'recorded_by'      => $userId
    ]
], 201);
