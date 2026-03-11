<?php
/**
 * Authentication Middleware
 * 
 * Provides authentication and authorization middleware functions
 * for protecting API endpoints.
 * 
 * @package KapadaStation
 * @version 1.0.0
 */

require_once(__DIR__ . '/../helpers/jwt.php');
require_once(__DIR__ . '/../helpers/response.php');

/**
 * Require authentication for the current request
 * 
 * Validates the JWT token from the Authorization header.
 * If authentication fails, sends a 401 error response and exits.
 * 
 * @return array The validated user payload from the JWT token
 * 
 * @example
 * $user = requireAuth();
 * $userId = $user['user_id'];
 */
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

    // Store the authenticated user globally for access in other functions
    $GLOBALS['_auth_user'] = $payload;

    return $payload;
}

/**
 * Require admin role for the current request
 * 
 * First validates authentication, then checks if the user has admin role.
 * If authorization fails, sends a 403 error response and exits.
 * 
 * @return array The validated admin user payload from the JWT token
 * 
 * @example
 * $admin = requireAdmin();
 * // Only admins can reach this point
 */
function requireAdmin() {
    $user = requireAuth();
    
    if ($user['role'] !== 'admin') {
        sendError('Forbidden: Admin access required', 403);
    }
    
    return $user;
}

/**
 * Optional authentication check
 * 
 * Attempts to validate authentication but doesn't fail if not provided.
 * Useful for endpoints that work for both guests and authenticated users.
 * 
 * @return array|null The user payload if authenticated, null otherwise
 * 
 * @example
 * $user = optionalAuth();
 * if ($user) {
 *     // User is logged in
 * } else {
 *     // Guest user
 * }
 */
function optionalAuth() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return null;
    }

    $payload = validateJWT($matches[1]);
    
    if ($payload) {
        $GLOBALS['_auth_user'] = $payload;
    }
    
    return $payload ?: null;
}

/**
 * Get the currently authenticated user
 * 
 * Returns the user payload if authentication was already performed.
 * 
 * @return array|null The user payload or null if not authenticated
 */
function getCurrentUser() {
    return $GLOBALS['_auth_user'] ?? null;
}
