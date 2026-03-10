<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../middleware/auth.php');

$method   = $_SERVER['REQUEST_METHOD'];
$authUser = requireAuth();
$userId   = (int)$authUser['user_id'];

// ─── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT chest, waist, hips, height, weight, shoulder, inseam
         FROM user_profiles WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $measurements = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    sendResponse(['success' => true, 'measurements' => $measurements ?? []]);
}

// ─── PUT ─────────────────────────────────────────────────────────────────────
elseif ($method === 'PUT') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $fields = ['chest', 'waist', 'hips', 'height', 'weight', 'shoulder', 'inseam'];

    $updateFields = [];
    $types        = '';
    $params       = [];

    foreach ($fields as $field) {
        if (isset($body[$field]) && is_numeric($body[$field])) {
            $val = (float)$body[$field];
            if ($val < 0) {
                sendError("$field must be a non-negative value");
            }
            $updateFields[] = "$field = ?";
            $types         .= 'd';
            $params[]       = $val;
        }
    }

    if (empty($updateFields)) {
        sendError('No valid measurement fields provided');
    }

    $db = getDB();

    // Upsert: ensure profile row exists
    $checkStmt = $db->prepare('SELECT user_id FROM user_profiles WHERE user_id = ? LIMIT 1');
    $checkStmt->bind_param('i', $userId);
    $checkStmt->execute();
    $checkStmt->store_result();
    $exists = $checkStmt->num_rows > 0;
    $checkStmt->close();

    if (!$exists) {
        $insStmt = $db->prepare('INSERT INTO user_profiles (user_id) VALUES (?)');
        $insStmt->bind_param('i', $userId);
        $insStmt->execute();
        $insStmt->close();
    }

    $params[] = $userId;
    $types   .= 'i';
    $sql      = 'UPDATE user_profiles SET ' . implode(', ', $updateFields) . ' WHERE user_id = ?';
    $stmt     = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        $stmt->close();
        $db->close();
        sendError('Failed to update measurements', 500);
    }
    $stmt->close();

    // Return updated measurements
    $getStmt = $db->prepare(
        'SELECT chest, waist, hips, height, weight, shoulder, inseam
         FROM user_profiles WHERE user_id = ? LIMIT 1'
    );
    $getStmt->bind_param('i', $userId);
    $getStmt->execute();
    $measurements = $getStmt->get_result()->fetch_assoc();
    $getStmt->close();
    $db->close();

    sendResponse(['success' => true, 'measurements' => $measurements]);
}

else {
    sendError('Method not allowed', 405);
}
