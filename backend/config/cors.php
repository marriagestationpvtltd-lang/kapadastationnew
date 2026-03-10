<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Catch any uncaught exception or Error and return a JSON response instead of
// a blank 500 page (which is what browsers and clients see when display_errors
// is Off and a fatal PHP error occurs).
define('INTERNAL_ERROR_MSG', 'An internal server error occurred');

set_exception_handler(function (Throwable $e) {
    error_log('Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => INTERNAL_ERROR_MSG]);
    exit;
});

// Catch fatal errors (E_ERROR, E_PARSE, etc.) that cannot be trapped by
// set_exception_handler because they halt the engine before the exception path.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE))) {
        error_log('Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => INTERNAL_ERROR_MSG]);
    }
});
