<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../middleware/auth.php');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    sendError('Method not allowed', 405);
}

requireAdmin();

// ─── POST: Record a payment ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $bookingId       = isset($body['booking_id']) ? (int)$body['booking_id'] : 0;
    $trackingCode    = trim($body['tracking_code'] ?? '');
    $type            = trim($body['type'] ?? $body['payment_type'] ?? '');
    $method          = trim($body['method'] ?? $body['payment_method'] ?? '');
    $amount          = isset($body['amount']) ? (float)$body['amount'] : 0;
    $referenceNumber = trim($body['reference_number'] ?? '');
    $notes           = trim($body['notes'] ?? '');

    $validTypes   = ['deposit', 'rental', 'refund'];
    $validMethods = ['cash', 'upi', 'bank_transfer'];

    if (!in_array($type, $validTypes)) {
        sendError('Valid type (deposit/rental/refund) is required');
    }
    if (!in_array($method, $validMethods)) {
        sendError('Valid method (cash/upi/bank_transfer) is required');
    }
    if ($amount <= 0) {
        sendError('Amount must be positive');
    }

    $db = getDB();

    // Resolve booking_id from tracking code if not provided directly
    if ($bookingId <= 0 && !empty($trackingCode)) {
        $tStmt = $db->prepare('SELECT id FROM bookings WHERE tracking_code = ? LIMIT 1');
        $tStmt->bind_param('s', $trackingCode);
        $tStmt->execute();
        $tRow = $tStmt->get_result()->fetch_assoc();
        $tStmt->close();
        if (!$tRow) {
            $db->close();
            sendError('Booking not found with that tracking code', 404);
        }
        $bookingId = (int)$tRow['id'];
    }

    if ($bookingId <= 0) {
        $db->close();
        sendError('Valid booking_id or tracking_code is required');
    }

    // Get the admin user ID from the already-validated token
    $recordedBy = (int)($GLOBALS['_auth_user']['user_id'] ?? 0);

    $stmt = $db->prepare(
        'INSERT INTO payments (booking_id, type, method, amount, reference_number, notes, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('issdss', $bookingId, $type, $method, $amount, $referenceNumber, $notes);

    if (!$stmt->execute()) {
        $stmt->close();
        $db->close();
        sendError('Failed to record payment', 500);
    }
    $paymentId = $stmt->insert_id;
    $stmt->close();

    // If deposit payment, auto-confirm the booking (status: pending → confirmed)
    if ($type === 'deposit') {
        $updStmt = $db->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
        $updStmt->bind_param('i', $bookingId);
        $updStmt->execute();
        $updStmt->close();
    }

    $db->close();

    sendResponse(['success' => true, 'payment_id' => $paymentId, 'message' => 'Payment recorded successfully'], 201);
}

// ─── GET: List payments ───────────────────────────────────────────────────────

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$type      = trim($_GET['type'] ?? '');
$method    = trim($_GET['method'] ?? '');
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset    = ($page - 1) * $limit;

$conditions = [];
$bindTypes  = '';
$bindParams = [];

if ($bookingId > 0) {
    $conditions[] = 'pay.booking_id = ?';
    $bindTypes   .= 'i';
    $bindParams[] = $bookingId;
}

$validTypes = ['deposit', 'rental', 'refund'];
if (!empty($type) && in_array($type, $validTypes)) {
    $conditions[] = 'pay.type = ?';
    $bindTypes   .= 's';
    $bindParams[] = $type;
}

$validMethods = ['cash', 'upi', 'bank_transfer'];
if (!empty($method) && in_array($method, $validMethods)) {
    $conditions[] = 'pay.method = ?';
    $bindTypes   .= 's';
    $bindParams[] = $method;
}

if (!empty($dateFrom) && DateTime::createFromFormat('Y-m-d', $dateFrom)) {
    $conditions[] = 'DATE(pay.created_at) >= ?';
    $bindTypes   .= 's';
    $bindParams[] = $dateFrom;
}

if (!empty($dateTo) && DateTime::createFromFormat('Y-m-d', $dateTo)) {
    $conditions[] = 'DATE(pay.created_at) <= ?';
    $bindTypes   .= 's';
    $bindParams[] = $dateTo;
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$db          = getDB();

// Count total
$countSql  = "SELECT COUNT(*) AS total FROM payments pay $whereClause";
$countStmt = $db->prepare($countSql);
if ($bindTypes) {
    $countStmt->bind_param($bindTypes, ...$bindParams);
}
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Fetch payments
$dataSql  = "SELECT pay.id, pay.booking_id, pay.type, pay.method, pay.amount,
                    pay.reference_number, pay.notes, pay.created_at,
                    b.tracking_code, b.customer_name,
                    u.name AS recorded_by_name
             FROM payments pay
             JOIN bookings b ON pay.booking_id = b.id
             LEFT JOIN users u ON pay.recorded_by = u.id
             $whereClause
             ORDER BY pay.created_at DESC
             LIMIT ? OFFSET ?";

$dataStmt   = $db->prepare($dataSql);
$dataTypes  = $bindTypes . 'ii';
$dataParams = array_merge($bindParams, [$limit, $offset]);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$result   = $dataStmt->get_result();
$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}
$dataStmt->close();

// Totals per type
$totalsSql  = "SELECT pay.type, COALESCE(SUM(pay.amount), 0) AS total_amount
               FROM payments pay
               $whereClause
               GROUP BY pay.type";
$totalsStmt = $db->prepare($totalsSql);
if ($bindTypes) {
    $totalsStmt->bind_param($bindTypes, ...$bindParams);
}
$totalsStmt->execute();
$totalsResult = $totalsStmt->get_result();
$totals       = [];
while ($row = $totalsResult->fetch_assoc()) {
    $totals[$row['type']] = (float)$row['total_amount'];
}
$totalsStmt->close();
$db->close();

sendResponse([
    'success'    => true,
    'payments'   => $payments,
    'totals'     => $totals,
    'pagination' => [
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit)
    ]
]);
