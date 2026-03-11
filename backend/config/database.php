<?php
/**
 * Database Configuration
 * 
 * This file contains database connection settings and helper functions.
 * In production, set environment variables instead of using defaults.
 * 
 * Environment Variables:
 * - DB_HOST: Database host (default: localhost)
 * - DB_USER: Database username (default: root)
 * - DB_PASS: Database password (default: empty)
 * - DB_NAME: Database name (default: kapada_station)
 * - JWT_SECRET: Secret key for JWT signing (REQUIRED in production)
 * - BASE_URL: Public URL of the site
 * 
 * @package KapadaStation
 * @version 1.0.0
 */

// ─── Configuration Constants ─────────────────────────────────────────────────

// Database credentials - Use environment variables in production
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_NAME',    getenv('DB_NAME')    ?: 'kapada_station');

// JWT Secret - IMPORTANT: Set a strong, random 64-character string in production
// Generate with: openssl rand -hex 32
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'kapada_station_jwt_secret_2024');

// File paths
define('UPLOAD_PATH', __DIR__ . '/../../uploads/');
define('BASE_URL',    getenv('BASE_URL')   ?: 'http://localhost');

// ─── Pagination Limits ───────────────────────────────────────────────────────
define('DEFAULT_PAGE_LIMIT', 20);
define('MAX_PAGE_LIMIT', 100);
define('MIN_PAGE_LIMIT', 1);

// ─── File Upload Limits ──────────────────────────────────────────────────────
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('MAX_SQL_FILE_SIZE', 50 * 1024 * 1024); // 50 MB for SQL imports

// ─── JWT Token Expiry ────────────────────────────────────────────────────────
define('JWT_EXPIRY_DAYS', 7);

/**
 * Get a database connection
 * 
 * Creates and returns a new MySQLi connection with UTF-8 charset.
 * On failure, returns a JSON error response and exits.
 * 
 * @return mysqli Database connection object
 */
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        // Log the actual error server-side; never expose infra details to clients
        error_log('Database connection failed: ' . $conn->connect_error);
        
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => 'Service temporarily unavailable']);
        exit;
    }
    
    $conn->set_charset('utf8mb4');
    return $conn;
}
