<?php
/**
 * Kapada Station — First-time Database Setup
 * ============================================================
 * Run this script ONCE from your browser or CLI to create the
 * database schema and insert the default admin user + sample data.
 *
 * URL: http(s)://yourdomain.com/backend/setup.php
 *
 * After the setup completes successfully, this script becomes a
 * no-op — it will not overwrite existing data.  If you need to
 * re-run it (e.g. on a fresh database), delete the lock file at
 *   backend/.setup-done
 * or drop / recreate the database first.
 *
 * ⚠️  For extra security, delete or rename this file once setup
 *    is done so it cannot be discovered by crawlers.
 */

// ── Security: block re-runs once setup has completed ─────────────────────────
$lockFile = __DIR__ . '/.setup-done';
if (file_exists($lockFile)) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Database was already set up. '
            . 'Delete backend/.setup-done to force a re-run.',
        'admin'   => [
            'email'    => 'admin@kapadastationnew.com',
            'password' => 'Admin@123',
        ],
    ]);
    exit;
}

// ── Load DB config ────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';

// ── Connect to MySQL (without selecting a DB yet) ─────────────────────────────
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    // Log details server-side; return only a generic message to the client
    error_log('Setup: MySQL connection failed: ' . $conn->connect_error);
    echo json_encode([
        'success' => false,
        'message' => 'Cannot connect to MySQL. '
            . 'Check DB_HOST, DB_USER and DB_PASS in backend/config/database.php '
            . '(or the corresponding environment variables).',
    ]);
    exit;
}

// ── Create the database if it does not exist ──────────────────────────────────
$dbName = $conn->real_escape_string(DB_NAME);
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create database: ' . $conn->error,
    ]);
    $conn->close();
    exit;
}

$conn->select_db(DB_NAME);

// ── Check whether the tables already exist ───────────────────────────────────
$result  = $conn->query("SHOW TABLES LIKE 'users'");
$hasData = ($result && $result->num_rows > 0);

if ($hasData) {
    // Tables exist — mark as done and return success without touching data
    file_put_contents($lockFile, date('c'));
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success'  => true,
        'message'  => 'Database tables already exist — nothing was changed.',
        'admin'    => [
            'email'    => 'admin@kapadastationnew.com',
            'password' => 'Admin@123',
        ],
    ]);
    $conn->close();
    exit;
}

// ── Read and execute the SQL file ─────────────────────────────────────────────
$sqlFile = __DIR__ . '/database.sql';
if (!file_exists($sqlFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'database.sql not found at backend/database.sql.',
    ]);
    $conn->close();
    exit;
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Could not read backend/database.sql.',
    ]);
    $conn->close();
    exit;
}

$conn->query('SET FOREIGN_KEY_CHECKS=0');

// Split on semicolons that end a statement (same parser used by the admin import)
$rawStatements = preg_split('/;\s*(?:\r?\n|$)/', $sql);

$executed = 0;
$errors   = [];

foreach ($rawStatements as $stmt) {
    // Strip comment lines
    $stmt = preg_replace('/^--.*$/m', '', $stmt);
    $stmt = trim($stmt);
    if ($stmt === '') {
        continue;
    }
    if ($conn->query($stmt)) {
        $executed++;
    } else {
        $errors[] = $conn->error;
    }
}

$conn->query('SET FOREIGN_KEY_CHECKS=1');
$conn->close();

header('Content-Type: application/json');

if (!empty($errors)) {
    http_response_code(500);
    echo json_encode([
        'success'   => false,
        'message'   => 'Setup finished with ' . count($errors) . ' error(s). '
            . $executed . ' statement(s) executed.',
        'errors'    => array_slice($errors, 0, 10),
    ]);
    exit;
}

// ── Write the lock file so this script becomes a no-op on future visits ───────
file_put_contents($lockFile, date('c'));

http_response_code(200);
echo json_encode([
    'success'  => true,
    'message'  => 'Database setup complete! '
        . $executed . ' statement(s) executed. '
        . 'You can now log in to the admin panel.',
    'admin'    => [
        'email'    => 'admin@kapadastationnew.com',
        'password' => 'Admin@123',
    ],
    'next_steps' => [
        '1. Log in at frontend/pages/login.html',
        '2. Change the admin password immediately',
        '3. Delete or rename backend/setup.php for extra security',
    ],
]);
