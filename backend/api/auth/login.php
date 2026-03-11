<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$body     = json_decode(file_get_contents('php://input'), true);
$email    = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

if (empty($email) || empty($password)) {
    sendError('Email and password are required');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError('Invalid email format');
}

$db   = getDB();
$stmt = $db->prepare('SELECT id, name, email, phone, password, role, is_verified FROM users WHERE email = ? LIMIT 1');
if (!$stmt) {
    error_log('Login prepare failed: ' . $db->error);
    $db->close();
    sendError('Service temporarily unavailable', 500);
}
$stmt->bind_param('s', $email);

if (!$stmt->execute()) {
    error_log('Login execute failed: ' . $stmt->error);
    $stmt->close();
    $db->close();
    sendError('Service temporarily unavailable', 500);
}

$result = $stmt->get_result();
if ($result === false) {
    error_log('Login get_result failed: ' . $stmt->error);
    $stmt->close();
    $db->close();
    sendError('Service temporarily unavailable', 500);
}
$user   = $result->fetch_assoc();
$stmt->close();
$db->close();

if (!$user || !password_verify($password, $user['password'])) {
    sendError('Invalid email or password', 401);
}

// Use JWT_EXPIRY_DAYS constant from database.php config
$jwtExpiry = defined('JWT_EXPIRY_DAYS') ? JWT_EXPIRY_DAYS : 7;

$payload = [
    'user_id' => (int)$user['id'],
    'email'   => $user['email'],
    'role'    => $user['role'],
    'exp'     => time() + 86400 * $jwtExpiry
];

$token = generateJWT($payload);

sendResponse([
    'success' => true,
    'token'   => $token,
    'user'    => [
        'id'          => (int)$user['id'],
        'name'        => $user['name'],
        'email'       => $user['email'],
        'phone'       => $user['phone'],
        'role'        => $user['role'],
        'is_verified' => (int)$user['is_verified']
    ]
]);
