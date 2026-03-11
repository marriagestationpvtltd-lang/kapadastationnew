<?php
/**
 * CORS Configuration and Error Handling
 * 
 * Sets up Cross-Origin Resource Sharing headers and global error handling.
 * 
 * Configuration:
 * - Set CORS_ORIGIN environment variable to your domain in production
 * - Default is '*' for development only (accepts all origins)
 * 
 * @package KapadaStation
 * @version 1.0.0
 */

// ─── CORS Headers ────────────────────────────────────────────────────────────
// In production, set the CORS_ORIGIN environment variable to your domain
// e.g., https://yourdomain.com
$corsOrigin = getenv('CORS_ORIGIN') ?: '*';

// Security warning: In production, always set a specific origin instead of '*'
header('Access-Control-Allow-Origin: ' . $corsOrigin);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// ─── Handle Preflight (OPTIONS) Requests ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── Error Constants ─────────────────────────────────────────────────────────
define('INTERNAL_ERROR_MSG', 'An internal server error occurred');

// ─── Global Exception Handler ────────────────────────────────────────────────
// Catches any uncaught exception or Error and returns a JSON response
// instead of a blank 500 page (which is what browsers see when display_errors
// is Off and a fatal PHP error occurs).
set_exception_handler(function (Throwable $e) {
    // Log the full error details server-side
    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    
    // Return generic message to client (don't expose internal details)
    echo json_encode(['error' => INTERNAL_ERROR_MSG]);
    exit;
});

// ─── Fatal Error Handler ─────────────────────────────────────────────────────
// Catches fatal errors (E_ERROR, E_PARSE, etc.) that cannot be trapped by
// set_exception_handler because they halt the engine before the exception path.
register_shutdown_function(function () {
    $error = error_get_last();
    
    if ($error && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
        // Log the full error details server-side
        error_log('Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        
        // Return generic message to client
        echo json_encode(['error' => INTERNAL_ERROR_MSG]);
    }
});
