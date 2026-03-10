<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../middleware/auth.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

requireAdmin();

$db = getDB();

// Total products
$row = $db->query('SELECT COUNT(*) AS total FROM products')->fetch_assoc();
$totalProducts = (int)$row['total'];

// Total bookings
$row = $db->query('SELECT COUNT(*) AS total FROM bookings')->fetch_assoc();
$totalBookings = (int)$row['total'];

// Pending bookings
$stmt = $db->prepare('SELECT COUNT(*) AS total FROM bookings WHERE status = ?');
$pending = 'pending';
$stmt->bind_param('s', $pending);
$stmt->execute();
$pendingBookings = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Total revenue (rental payments)
$stmt = $db->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE type = ?');
$rental = 'rental';
$stmt->bind_param('s', $rental);
$stmt->execute();
$totalRevenue = (float)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Total registered users
$row = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'user'")->fetch_assoc();
$totalUsers = (int)$row['total'];

// Active bookings (status = active)
$stmt = $db->prepare('SELECT COUNT(*) AS total FROM bookings WHERE status = ?');
$active = 'active';
$stmt->bind_param('s', $active);
$stmt->execute();
$activeBookings = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Available products (status = active, stock > 0)
$row = $db->query("SELECT COUNT(*) AS total FROM products WHERE status = 'active' AND stock > 0")->fetch_assoc();
$availableProducts = (int)$row['total'];

// Recent 5 bookings
$recentBookingsResult = $db->query(
    'SELECT b.id, b.tracking_code, b.customer_name, b.rental_start, b.rental_end,
            b.status, b.created_at, p.name AS product_name
     FROM bookings b
     JOIN products p ON b.product_id = p.id
     ORDER BY b.created_at DESC LIMIT 5'
);
$recentBookings = [];
while ($row = $recentBookingsResult->fetch_assoc()) {
    $recentBookings[] = $row;
}

// Recent 5 payments
$recentPaymentsResult = $db->query(
    'SELECT pay.id, pay.amount, pay.type, pay.method, pay.created_at,
            b.tracking_code
     FROM payments pay
     JOIN bookings b ON pay.booking_id = b.id
     ORDER BY pay.created_at DESC LIMIT 5'
);
$recentPayments = [];
while ($row = $recentPaymentsResult->fetch_assoc()) {
    $recentPayments[] = $row;
}

$db->close();

sendResponse([
    'success' => true,
    'stats' => [
        'total_products'    => $totalProducts,
        'total_bookings'    => $totalBookings,
        'pending_bookings'  => $pendingBookings,
        'total_revenue'     => $totalRevenue,
        'total_users'       => $totalUsers,
        'active_bookings'   => $activeBookings,
        'available_products' => $availableProducts
    ],
    'recent_bookings' => $recentBookings,
    'recent_payments' => $recentPayments
]);
