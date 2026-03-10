<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../middleware/auth.php');

requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $status    = trim($_GET['status'] ?? '');
    $dateFrom  = trim($_GET['date_from'] ?? '');
    $dateTo    = trim($_GET['date_to'] ?? '');
    $search    = trim($_GET['search'] ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $limit     = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset    = ($page - 1) * $limit;

    $conditions = [];
    $bindTypes  = '';
    $bindParams = [];

    // Filter by user_id (for viewing a specific user's bookings)
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($userId > 0) {
        $conditions[] = 'b.user_id = ?';
        $bindTypes   .= 'i';
        $bindParams[] = $userId;
    }

    $validStatuses = ['pending', 'confirmed', 'active', 'returned', 'cancelled'];
    if (!empty($status) && in_array($status, $validStatuses)) {
        $conditions[] = 'b.status = ?';
        $bindTypes   .= 's';
        $bindParams[] = $status;
    }

    if (!empty($dateFrom) && DateTime::createFromFormat('Y-m-d', $dateFrom)) {
        $conditions[] = 'b.rental_start >= ?';
        $bindTypes   .= 's';
        $bindParams[] = $dateFrom;
    }

    if (!empty($dateTo) && DateTime::createFromFormat('Y-m-d', $dateTo)) {
        $conditions[] = 'b.rental_start <= ?';
        $bindTypes   .= 's';
        $bindParams[] = $dateTo;
    }

    if (!empty($search)) {
        $conditions[] = '(b.customer_name LIKE ? OR b.customer_email LIKE ? OR b.tracking_code LIKE ?)';
        $bindTypes   .= 'sss';
        $term         = '%' . $search . '%';
        $bindParams[] = $term;
        $bindParams[] = $term;
        $bindParams[] = $term;
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $db          = getDB();

    $countSql  = "SELECT COUNT(*) AS total FROM bookings b $whereClause";
    $countStmt = $db->prepare($countSql);
    if ($bindTypes) {
        $countStmt->bind_param($bindTypes, ...$bindParams);
    }
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $dataSql = "SELECT b.id, b.tracking_code, b.customer_name, b.customer_email,
                       b.customer_phone, b.rental_start, b.rental_end, b.total_days,
                       b.rental_amount, b.deposit_amount, b.status, b.notes, b.created_at,
                       p.name AS product_name,
                       COALESCE(SUM(pay.amount), 0) AS total_paid
                FROM bookings b
                JOIN products p ON b.product_id = p.id
                LEFT JOIN payments pay ON b.id = pay.booking_id
                $whereClause
                GROUP BY b.id
                ORDER BY b.created_at DESC
                LIMIT ? OFFSET ?";

    $dataStmt   = $db->prepare($dataSql);
    $dataTypes  = $bindTypes . 'ii';
    $dataParams = array_merge($bindParams, [$limit, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $result   = $dataStmt->get_result();
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $row['balance_due'] = max(0,
            ((float)$row['rental_amount'] + (float)$row['deposit_amount']) - (float)$row['total_paid']
        );
        $bookings[] = $row;
    }
    $dataStmt->close();
    $db->close();

    sendResponse([
        'success'    => true,
        'bookings'   => $bookings,
        'pagination' => [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit)
        ]
    ]);
}

// ─── PUT ─────────────────────────────────────────────────────────────────────
elseif ($method === 'PUT') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        sendError('Valid booking ID is required');
    }

    $body      = json_decode(file_get_contents('php://input'), true);
    $newStatus = trim($body['status'] ?? '');
    $notes     = isset($body['notes']) ? trim($body['notes']) : null;

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, status FROM bookings WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        $db->close();
        sendError('Booking not found', 404);
    }

    $validTransitions = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['active', 'cancelled'],
        'active'    => ['returned', 'cancelled'],
        'returned'  => [],
        'cancelled' => []
    ];

    $fields = [];
    $types  = '';
    $params = [];

    if (!empty($newStatus)) {
        $currentStatus = $booking['status'];
        if (!isset($validTransitions[$currentStatus]) ||
            !in_array($newStatus, $validTransitions[$currentStatus])) {
            $db->close();
            sendError("Invalid status transition from '$currentStatus' to '$newStatus'", 422);
        }
        $fields[] = 'status = ?';
        $types   .= 's';
        $params[] = $newStatus;
    }

    if ($notes !== null) {
        $fields[] = 'notes = ?';
        $types   .= 's';
        $params[] = $notes;
    }

    if (empty($fields)) {
        $db->close();
        sendError('No fields to update');
    }

    $params[] = $id;
    $types   .= 'i';
    $sql      = 'UPDATE bookings SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt     = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        $stmt->close();
        $db->close();
        sendError('Failed to update booking', 500);
    }
    $stmt->close();
    $db->close();

    sendResponse(['success' => true, 'message' => 'Booking updated']);
}

else {
    sendError('Method not allowed', 405);
}
