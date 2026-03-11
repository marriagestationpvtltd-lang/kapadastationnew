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
    $status = trim($_GET['status'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $conditions  = [];
    $bindTypes   = '';
    $bindParams  = [];

    if ($status !== '') {
        $conditions[] = 'status = ?';
        $bindTypes   .= 's';
        $bindParams[] = $status;
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $db = getDB();

    $countSql  = "SELECT COUNT(*) AS total FROM fountain $whereClause";
    $countStmt = $db->prepare($countSql);
    if ($bindTypes) {
        $countStmt->bind_param($bindTypes, ...$bindParams);
    }
    $countStmt->execute();
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $dataSql  = "SELECT * FROM fountain $whereClause ORDER BY sort_order ASC, id ASC LIMIT ? OFFSET ?";
    $dataStmt = $db->prepare($dataSql);
    $dataTypes  = $bindTypes . 'ii';
    $dataParams = array_merge($bindParams, [$limit, $offset]);
    $dataStmt->bind_param($dataTypes, ...$dataParams);
    $dataStmt->execute();
    $result = $dataStmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $dataStmt->close();
    $db->close();

    sendResponse([
        'success'    => true,
        'items'      => $items,
        'pagination' => [
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit)
        ]
    ]);
}

// ─── POST ────────────────────────────────────────────────────────────────────
elseif ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $title      = trim($body['title'] ?? '');
    $subtitle   = trim($body['subtitle'] ?? '');
    $description= trim($body['description'] ?? '');
    $imageUrl   = trim($body['image_url'] ?? '');
    $buttonText = trim($body['button_text'] ?? 'Shop Now');
    $buttonLink = trim($body['button_link'] ?? '');
    $bgColor    = trim($body['bg_color'] ?? '#6c3483');
    $textColor  = trim($body['text_color'] ?? '#ffffff');
    $sortOrder  = isset($body['sort_order']) ? (int)$body['sort_order'] : 0;
    $status     = in_array($body['status'] ?? '', ['active', 'inactive']) ? $body['status'] : 'active';

    if (empty($title)) {
        sendError('Title is required');
    }

    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO fountain (title, subtitle, description, image_url, button_text,
                               button_link, bg_color, text_color, sort_order, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'ssssssssis',
        $title, $subtitle, $description, $imageUrl, $buttonText,
        $buttonLink, $bgColor, $textColor, $sortOrder, $status
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        $db->close();
        error_log('Fountain insert failed: ' . $err);
        sendError('Failed to create fountain item', 500);
    }

    $id = $db->insert_id;
    $stmt->close();
    $db->close();

    sendResponse(['success' => true, 'message' => 'Fountain item created', 'id' => $id], 201);
}

// ─── PUT ─────────────────────────────────────────────────────────────────────
elseif ($method === 'PUT') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        sendError('Valid fountain item ID is required');
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $db   = getDB();
    $chk  = $db->prepare('SELECT id FROM fountain WHERE id = ? LIMIT 1');
    $chk->bind_param('i', $id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        $chk->close();
        $db->close();
        sendError('Fountain item not found', 404);
    }
    $chk->close();

    $fields = [];
    $types  = '';
    $params = [];

    $stringFields = ['title', 'subtitle', 'description', 'image_url',
                     'button_text', 'button_link', 'bg_color', 'text_color'];
    foreach ($stringFields as $f) {
        if (array_key_exists($f, $body)) {
            $fields[] = "$f = ?";
            $types   .= 's';
            $params[] = trim((string)$body[$f]);
        }
    }

    if (array_key_exists('sort_order', $body)) {
        $fields[] = 'sort_order = ?';
        $types   .= 'i';
        $params[] = (int)$body['sort_order'];
    }

    if (array_key_exists('status', $body) && in_array($body['status'], ['active', 'inactive'])) {
        $fields[] = 'status = ?';
        $types   .= 's';
        $params[] = $body['status'];
    }

    if (empty($fields)) {
        $db->close();
        sendError('No fields to update');
    }

    $types   .= 'i';
    $params[] = $id;
    $sql      = 'UPDATE fountain SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt     = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        $db->close();
        error_log('Fountain update failed: ' . $err);
        sendError('Failed to update fountain item', 500);
    }
    $stmt->close();
    $db->close();

    sendResponse(['success' => true, 'message' => 'Fountain item updated']);
}

// ─── DELETE ──────────────────────────────────────────────────────────────────
elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        sendError('Valid fountain item ID is required');
    }

    $db   = getDB();
    $stmt = $db->prepare('DELETE FROM fountain WHERE id = ?');
    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        $db->close();
        error_log('Fountain delete failed: ' . $err);
        sendError('Failed to delete fountain item', 500);
    }

    $affected = $stmt->affected_rows;
    $stmt->close();
    $db->close();

    if ($affected === 0) {
        sendError('Fountain item not found', 404);
    }

    sendResponse(['success' => true, 'message' => 'Fountain item deleted']);
}

else {
    sendError('Method not allowed', 405);
}
