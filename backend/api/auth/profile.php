<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../middleware/auth.php');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $authUser = requireAuth();
    $userId   = (int)$authUser['user_id'];
    $db       = getDB();

    $stmt = $db->prepare(
        'SELECT u.id, u.name, u.email, u.phone, u.role, u.is_verified, u.created_at,
                up.address, up.city, up.state, up.postal_code, up.country,
                up.chest, up.waist, up.hips, up.height, up.weight, up.shoulder, up.inseam
         FROM users u
         LEFT JOIN user_profiles up ON u.id = up.user_id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();
    $db->close();

    if (!$user) {
        sendError('User not found', 404);
    }

    sendResponse(['user' => $user]);

} elseif ($method === 'PUT') {
    $authUser = requireAuth();
    $userId   = (int)$authUser['user_id'];
    $body     = json_decode(file_get_contents('php://input'), true);

    $db = getDB();

    // Update users table fields
    $fieldsToUpdate = [];
    $params         = [];
    $types          = '';

    if (!empty($body['name'])) {
        $name = trim($body['name']);
        $fieldsToUpdate[] = 'name = ?';
        $params[]         = $name;
        $types           .= 's';
    }

    if (!empty($body['phone'])) {
        $phone = trim($body['phone']);
        if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            $db->close();
            sendError('Invalid phone number format');
        }
        $fieldsToUpdate[] = 'phone = ?';
        $params[]         = $phone;
        $types           .= 's';
    }

    if (!empty($fieldsToUpdate)) {
        $params[] = $userId;
        $types   .= 'i';
        $sql      = 'UPDATE users SET ' . implode(', ', $fieldsToUpdate) . ' WHERE id = ?';
        $stmt     = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    // Update user_profiles table fields
    $profileFields = [];
    $profileParams = [];
    $profileTypes  = '';

    $profileKeys = ['address', 'city', 'state', 'postal_code', 'country'];
    foreach ($profileKeys as $key) {
        if (isset($body[$key])) {
            $profileFields[] = $key . ' = ?';
            $profileParams[] = trim($body[$key]);
            $profileTypes   .= 's';
        }
    }

    if (!empty($profileFields)) {
        // Check if profile row exists
        $checkStmt = $db->prepare('SELECT user_id FROM user_profiles WHERE user_id = ? LIMIT 1');
        $checkStmt->bind_param('i', $userId);
        $checkStmt->execute();
        $checkStmt->store_result();
        $exists = $checkStmt->num_rows > 0;
        $checkStmt->close();

        if ($exists) {
            $profileParams[] = $userId;
            $profileTypes   .= 'i';
            $sql             = 'UPDATE user_profiles SET ' . implode(', ', $profileFields) . ' WHERE user_id = ?';
            $stmt            = $db->prepare($sql);
            $stmt->bind_param($profileTypes, ...$profileParams);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $db->prepare('INSERT INTO user_profiles (user_id) VALUES (?)');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $profileParams[] = $userId;
            $profileTypes   .= 'i';
            $sql             = 'UPDATE user_profiles SET ' . implode(', ', $profileFields) . ' WHERE user_id = ?';
            $stmt            = $db->prepare($sql);
            $stmt->bind_param($profileTypes, ...$profileParams);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Return updated profile
    $stmt = $db->prepare(
        'SELECT u.id, u.name, u.email, u.phone, u.role, u.is_verified, u.created_at,
                up.address, up.city, up.state, up.postal_code, up.country
         FROM users u
         LEFT JOIN user_profiles up ON u.id = up.user_id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();
    $db->close();

    sendResponse(['success' => true, 'user' => $user]);

} else {
    sendError('Method not allowed', 405);
}
