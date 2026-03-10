<?php
// Load from environment variables first, fall back to defaults for local dev.
// In production, set these env vars and do NOT commit real credentials to VCS.
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_NAME',    getenv('DB_NAME')    ?: 'kapada_station');
// In production, set JWT_SECRET to a long, randomly-generated string via env var.
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'kapada_station_jwt_secret_2024');
define('UPLOAD_PATH', __DIR__ . '/../../uploads/');
define('BASE_URL',   getenv('BASE_URL')   ?: 'http://localhost');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}
