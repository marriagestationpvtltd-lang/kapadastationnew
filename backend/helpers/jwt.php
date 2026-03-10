<?php
require_once(__DIR__ . '/../config/database.php');

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $headerEncoded  = base64url_encode($header);
    $payloadEncoded = base64url_encode(json_encode($payload));

    $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, JWT_SECRET, true);
    $signatureEncoded = base64url_encode($signature);

    return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
}

function validateJWT($token) {
    if (empty($token)) {
        return false;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

    $expectedSignature = base64url_encode(
        hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, JWT_SECRET, true)
    );

    if (!hash_equals($expectedSignature, $signatureEncoded)) {
        return false;
    }

    $payload = json_decode(base64url_decode($payloadEncoded), true);

    if (!is_array($payload)) {
        return false;
    }

    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }

    return $payload;
}
