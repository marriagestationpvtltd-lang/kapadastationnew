<?php
require_once(__DIR__ . '/../config/database.php');

function handleFileUpload($file, $folder, $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes)) {
        return false;
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $uploadDir = UPLOAD_PATH . $folder . '/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0750, true)) {
            return false;
        }
    }

    $destination = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return 'uploads/' . $folder . '/' . $filename;
    }
    return false;
}

function deleteFile($path) {
    // Prevent path traversal: resolve and verify the file is inside UPLOAD_PATH
    $uploadsRoot = realpath(UPLOAD_PATH);
    $fullPath    = realpath(__DIR__ . '/../../' . $path);

    if ($fullPath === false || $uploadsRoot === false) {
        return false;
    }

    // Ensure the resolved path starts with the uploads root
    if (strpos($fullPath, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    if (file_exists($fullPath)) {
        unlink($fullPath);
        return true;
    }
    return false;
}
