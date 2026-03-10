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

    $conditions = ["u.role != 'admin'"];
    $bindTypes  = '';
    $bindParams = [];

    if (!empty($search)) {
        $conditions[] = '(u.name LIKE ? OR u.email LIKE ?)';
        $bindTypes   .= 'ss';
        $term         = '%' . $search . '%';
        $bindParams[] = $term;
        $bindParams[] = $term;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    $db          = getDB();

    $countSql  = "SELECT COUNT(*) AS total FROM users u $whereClause";
    $countStmt = $db->prepare($countSql);
    if ($bindTypes) {
        $countStmt->bind_param($bindTypes, ...$bindParams);
    }
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $dataSql  = "SELECT u.id, u.name, u.email, u.phone, u.role, u.is_verified, u.created_at
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

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, name, email, phone, role, is_verified FROM users WHERE id = ? AND role != 'admin' LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $db->close();
        sendError('User not found', 404);
    }

    $newVerified = $user['is_verified'] ? 0 : 1;
    $updStmt     = $db->prepare('UPDATE users SET is_verified = ? WHERE id = ?');
    $updStmt->bind_param('ii', $newVerified, $id);
    $updStmt->execute();
    $updStmt->close();

    $user['is_verified'] = $newVerified;
    $db->close();

    sendResponse(['success' => true, 'user' => $user]);
}

else {
    sendError('Method not allowed', 405);
}
