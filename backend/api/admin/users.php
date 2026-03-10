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
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $conditions = [];
    $bindTypes  = '';
    $bindParams = [];

    // Optional role filter
    $roleFilter = trim($_GET['role'] ?? '');
    if ($roleFilter === 'admin' || $roleFilter === 'user') {
        $conditions[] = 'u.role = ?';
        $bindTypes   .= 's';
        $bindParams[] = $roleFilter;
    }

    if (!empty($search)) {
        $conditions[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $bindTypes   .= 'sss';
        $term         = '%' . $search . '%';
        $bindParams[] = $term;
        $bindParams[] = $term;
        $bindParams[] = $term;
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $db          = getDB();

    $countSql  = "SELECT COUNT(*) AS total FROM users u $whereClause";
    $countStmt = $db->prepare($countSql);
    if ($bindTypes) {
        $countStmt->bind_param($bindTypes, ...$bindParams);
    }
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $dataSql  = "SELECT u.id, u.name, u.email, u.phone, u.role, u.is_verified, u.created_at,
                         (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) AS booking_count
                 FROM users u
                 $whereClause
                 ORDER BY u.created_at DESC
                 LIMIT ? OFFSET ?";
    $dataStmt = $db->prepare($dataSql);
    $dataTypes  = $bindTypes . 'ii';
    $dataParams = array_merge($bindParams, [$limit, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $result = $dataStmt->get_result();
    $users  = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $dataStmt->close();
    $db->close();

    sendResponse([
        'success'    => true,
        'users'      => $users,
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
        sendError('Valid user ID is required');
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name, email, phone, role, is_verified FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $db->close();
        sendError('User not found', 404);
    }

    $fields = [];
    $types  = '';
    $params = [];

    // Toggle or set is_verified
    if (isset($body['is_verified'])) {
        $newVerified      = (int)(bool)$body['is_verified'];
        $fields[]         = 'is_verified = ?';
        $types           .= 'i';
        $params[]         = $newVerified;
        $user['is_verified'] = $newVerified;
    } else {
        // Legacy toggle behavior when no field specified
        $newVerified      = $user['is_verified'] ? 0 : 1;
        $fields[]         = 'is_verified = ?';
        $types           .= 'i';
        $params[]         = $newVerified;
        $user['is_verified'] = $newVerified;
    }

    // Optionally update role (only user roles, not admin promotion from here)
    if (isset($body['role']) && in_array($body['role'], ['user', 'admin'])) {
        $fields[]     = 'role = ?';
        $types       .= 's';
        $params[]     = $body['role'];
        $user['role'] = $body['role'];
    }

    if (!empty($fields)) {
        $params[] = $id;
        $types   .= 'i';
        $sql      = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $updStmt  = $db->prepare($sql);
        $updStmt->bind_param($types, ...$params);
        $updStmt->execute();
        $updStmt->close();
    }

    $db->close();

    sendResponse(['success' => true, 'user' => $user]);
}

else {
    sendError('Method not allowed', 405);
}
