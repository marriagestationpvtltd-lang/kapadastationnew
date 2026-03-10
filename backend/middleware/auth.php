<?php
require_once(__DIR__ . '/../helpers/jwt.php');
require_once(__DIR__ . '/../helpers/response.php');

function requireAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        sendError('Unauthorized', 401);
    }

    $token   = $matches[1];
    $payload = validateJWT($token);

    if (!$payload) {
        sendError('Invalid or expired token', 401);
    }

    $GLOBALS['_auth_user'] = $payload;

    return $payload;
}

function requireAdmin() {
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        sendError('Forbidden: Admin access required', 403);
    }
    return $user;
}
