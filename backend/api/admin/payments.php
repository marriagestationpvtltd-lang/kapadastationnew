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
    'payments'   => $payments,
    'totals'     => $totals,
    'pagination' => [
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit)
    ]
]);
