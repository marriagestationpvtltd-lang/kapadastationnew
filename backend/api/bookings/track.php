<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

$trackingCode = trim($_GET['tracking_code'] ?? '');

if (empty($trackingCode) || !preg_match('/^[A-Z0-9]{8}$/', $trackingCode)) {
    sendError('Valid tracking code is required (8 uppercase alphanumeric characters)');
}

$db   = getDB();
$stmt = $db->prepare(
    'SELECT b.id, b.tracking_code, b.customer_name, b.customer_email, b.customer_phone,
            b.rental_start, b.rental_end, b.total_days, b.rental_amount, b.deposit_amount,
            b.status, b.notes, b.created_at,
            p.id AS product_id, p.name AS product_name, p.images AS product_images,
            p.rental_price, p.color, p.size
     FROM bookings b
     JOIN products p ON b.product_id = p.id
     WHERE b.tracking_code = ? LIMIT 1'
);
$stmt->bind_param('s', $trackingCode);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    $db->close();
    sendError('Booking not found', 404);
}

$bookingId = (int)$booking['id'];

// Get payment summary
$payStmt = $db->prepare(
    'SELECT type, SUM(amount) AS total_paid
     FROM payments
     WHERE booking_id = ?
     GROUP BY type'
);
$payStmt->bind_param('i', $bookingId);
$payStmt->execute();
$payResult  = $payStmt->get_result();
$paymentMap = [];
while ($row = $payResult->fetch_assoc()) {
    $paymentMap[$row['type']] = (float)$row['total_paid'];
}
$payStmt->close();
$db->close();

$totalPaid   = array_sum($paymentMap);
$balanceDue  = max(0, ((float)$booking['rental_amount'] + (float)$booking['deposit_amount']) - $totalPaid);

$booking['product_images'] = json_decode($booking['product_images'] ?? '[]', true) ?: [];

sendResponse([
    'booking'         => $booking,
    'payment_summary' => [
        'total_paid'  => $totalPaid,
        'balance_due' => $balanceDue,
        'breakdown'   => $paymentMap
    ]
]);
