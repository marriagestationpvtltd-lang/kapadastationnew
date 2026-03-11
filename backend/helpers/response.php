<?php
/**
 * Response Helper Functions
 * 
 * Provides standardized JSON response functions for the API.
 * 
 * @package KapadaStation
 * @version 1.0.0
 */

/**
 * Send a successful JSON response
 * 
 * @param mixed $data Response data to send
 * @param int $status HTTP status code (default: 200)
 * @return void
 */
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Send an error JSON response
 * 
 * @param string $message Error message
 * @param int $status HTTP status code (default: 400)
 * @return void
 */
function sendError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Send a validation error with field-specific messages
 * 
 * @param array $errors Associative array of field => error message
 * @param int $status HTTP status code (default: 422)
 * @return void
 */
function sendValidationError($errors, $status = 422) {
    http_response_code($status);
    echo json_encode([
        'error' => 'Validation failed',
        'validation_errors' => $errors
    ]);
    exit;
}
