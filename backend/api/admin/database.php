<?php
require_once(__DIR__ . '/../../config/cors.php');
require_once(__DIR__ . '/../../config/database.php');
require_once(__DIR__ . '/../../helpers/response.php');
require_once(__DIR__ . '/../../helpers/jwt.php');
require_once(__DIR__ . '/../../middleware/auth.php');

requireAdmin();

// ─── EXPORT (GET) ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = getDB();

    // Collect table names
    $tables = [];
    $result = $db->query('SHOW TABLES');
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $lines = [];
    $lines[] = '-- ============================================================';
    $lines[] = '-- Kapada Station — Database Backup';
    $lines[] = '-- Generated : ' . date('Y-m-d H:i:s') . ' UTC';
    $lines[] = '-- Database  : ' . DB_NAME;
    $lines[] = '-- ============================================================';
    $lines[] = '';
    $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
    $lines[] = "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';";
    $lines[] = 'SET NAMES utf8mb4;';
    $lines[] = '';

    foreach ($tables as $table) {
        $lines[] = '-- ─────────────────────────────────────────────────────────────';
        $lines[] = "-- Table: `{$table}`";
        $lines[] = '-- ─────────────────────────────────────────────────────────────';

        // CREATE TABLE statement
        $row = $db->query("SHOW CREATE TABLE `{$table}`")->fetch_row();
        $lines[] = "DROP TABLE IF EXISTS `{$table}`;";
        $lines[] = $row[1] . ';';
        $lines[] = '';

        // Row data
        $rows = $db->query("SELECT * FROM `{$table}`");
        if ($rows && $rows->num_rows > 0) {
            while ($rowData = $rows->fetch_assoc()) {
                $cols   = implode('`, `', array_keys($rowData));
                $values = array_map(function ($v) use ($db) {
                    return $v === null ? 'NULL' : "'" . $db->real_escape_string($v) . "'";
                }, array_values($rowData));
                $lines[] = "INSERT INTO `{$table}` (`{$cols}`) VALUES (" . implode(', ', $values) . ');';
            }
            $lines[] = '';
        }
    }

    $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

    $db->close();

    $sql      = implode("\n", $lines);
    $filename = 'kapada_station_backup_' . date('Y-m-d_H-i-s') . '.sql';

    // Override the JSON content-type set by cors.php
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $sql;
    exit;
}

// ─── IMPORT (POST) ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = $_FILES['sql_file']['error'] ?? -1;
        sendError('No SQL file received or upload error (code ' . $uploadError . ')', 400);
    }

    $file = $_FILES['sql_file'];

    // Extension check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        sendError('Only .sql files are accepted', 400);
    }

    // Size limit: 50 MB
    if ($file['size'] > 50 * 1024 * 1024) {
        sendError('File exceeds the 50 MB size limit', 400);
    }

    // Read the uploaded file from the temp location
    $tmpPath = $file['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        sendError('Invalid file upload', 400);
    }

    $sql = file_get_contents($tmpPath);
    if ($sql === false) {
        sendError('Could not read the uploaded file', 500);
    }

    $db = getDB();
    $db->query('SET FOREIGN_KEY_CHECKS=0');

    // Split into individual statements.
    // Splits on semicolons followed by optional whitespace + newline, OR at EOF.
    // Note: this parser handles standard mysqldump-style files well; SQL files
    // with inline comments (e.g. "... -- comment") or semicolons inside string
    // literals may not import correctly. Use a dedicated tool (phpMyAdmin, mysql
    // CLI) for those cases.
    $raw = preg_split('/;\s*(?:\r?\n|$)/', $sql);

    $executed = 0;
    $errors   = [];
    foreach ($raw as $stmt) {
        // Remove pure comment lines
        $stmt = preg_replace('/^--.*$/m', '', $stmt);
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        if (!$db->query($stmt)) {
            $errors[] = $db->error;
        } else {
            $executed++;
        }
    }

    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->close();

    if (!empty($errors)) {
        sendResponse([
            'success'   => false,
            'message'   => 'Import finished with ' . count($errors) . ' error(s). ' . $executed . ' statement(s) executed.',
            'errors'    => array_slice($errors, 0, 10),
        ], 207);
    }

    sendResponse([
        'success'   => true,
        'message'   => 'Database imported successfully. ' . $executed . ' statement(s) executed.',
        'executed'  => $executed,
    ]);
}

sendError('Method not allowed', 405);
