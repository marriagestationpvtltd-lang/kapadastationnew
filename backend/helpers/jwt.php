<?php
/**
 * JWT Helper Functions
 * 
 * Pure PHP implementation of JWT (JSON Web Token) using HMAC-SHA256.
 * Provides token generation and validation without external dependencies.
 * 
 * @package KapadaStation
 * @version 1.0.0
 */

require_once(__DIR__ . '/../config/database.php');

/**
 * Encode data using URL-safe Base64
 * 
 * @param string $data Raw binary data
 * @return string URL-safe Base64 encoded string
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decode URL-safe Base64 data
 * 
 * @param string $data URL-safe Base64 encoded string
 * @return string Decoded binary data
 */
function base64url_decode($data) {
    // Restore standard base64 chars and re-add '=' padding
    // (4 - len%4)%4 gives 0,1,2,3 → 0,3,2,1 needed pads
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

/**
 * Generate a JWT token
 * 
 * Creates a signed JWT token with the given payload.
 * The token is signed using HMAC-SHA256.
 * 
 * @param array $payload Data to include in the token (user_id, email, role, exp, etc.)
 * @return string The complete JWT token string
 * 
 * @example
 * $token = generateJWT([
 *     'user_id' => 123,
 *     'email' => 'user@example.com',
 *     'role' => 'user',
 *     'exp' => time() + 86400 * 7 // 7 days
 * ]);
 */
function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $headerEncoded  = base64url_encode($header);
    $payloadEncoded = base64url_encode(json_encode($payload));

    $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, JWT_SECRET, true);
    $signatureEncoded = base64url_encode($signature);

    return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
}

/**
 * Validate and decode a JWT token
 * 
 * Verifies the token signature and checks expiration.
 * Uses timing-safe comparison to prevent timing attacks.
 * 
 * @param string $token The JWT token string
 * @return array|false The decoded payload if valid, false otherwise
 * 
 * @example
 * $payload = validateJWT($token);
 * if ($payload) {
 *     $userId = $payload['user_id'];
 * }
 */
function validateJWT($token) {
    if (empty($token)) {
        return false;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

    // Verify signature using timing-safe comparison
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

    // Check token expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }

    return $payload;
}
