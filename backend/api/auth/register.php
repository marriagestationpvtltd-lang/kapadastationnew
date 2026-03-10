<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$body = json_decode(file_get_contents('php://input'), true);

$name     = trim($body['name'] ?? '');
$email    = trim($body['email'] ?? '');
$phone    = trim($body['phone'] ?? '');
$password = $body['password'] ?? '';

if (empty($name) || empty($email) || empty($phone) || empty($password)) {
    sendError('Name, email, phone, and password are required');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError('Invalid email format');
}

if (strlen($password) < 6) {
    sendError('Password must be at least 6 characters');
}

if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
    sendError('Invalid phone number format');
}

$db = getDB();

$stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $db->close();
    sendError('Email already registered', 409);
}
$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role           = 'user';
$isVerified     = 0;

$stmt = $db->prepare('INSERT INTO users (name, email, phone, password, role, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('sssssi', $name, $email, $phone, $hashedPassword, $role, $isVerified);

if (!$stmt->execute()) {
    $stmt->close();
    $db->close();
    sendError('Registration failed. Please try again.', 500);
}

$userId = $stmt->insert_id;
$stmt->close();

$profileStmt = $db->prepare('INSERT INTO user_profiles (user_id) VALUES (?)');
$profileStmt->bind_param('i', $userId);
$profileStmt->execute();
$profileStmt->close();

$db->close();

$payload = [
    'user_id' => $userId,
    'email'   => $email,
    'role'    => $role,
    'exp'     => time() + 86400 * 7
];

$token = generateJWT($payload);

sendResponse([
    'success' => true,
    'token'   => $token,
    'user'    => [
        'id'    => $userId,
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
        'role'  => $role
    ]
], 201);
