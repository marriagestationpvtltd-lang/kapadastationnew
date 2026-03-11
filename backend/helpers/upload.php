<?php
/**
 * File Upload Helper Functions
 * 
 * Provides secure file upload and deletion functionality
 * with MIME type validation and path traversal protection.
 * 
 * @package KapadaStation
 * @version 1.0.0
 */

require_once(__DIR__ . '/../config/database.php');

// ─── Allowed File Types ──────────────────────────────────────────────────────
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg']);

/**
 * Handle secure file upload
 * 
 * Validates file size, MIME type, and saves with a unique filename.
 * 
 * @param array $file The $_FILES array entry for the uploaded file
 * @param string $folder Subfolder within uploads/ to store the file
 * @param array $allowedTypes Allowed MIME types (default: common image types)
 * @return string|false Relative path on success, false on failure
 * 
 * @example
 * $path = handleFileUpload($_FILES['photo'], 'photos');
 * if ($path) {
 *     // Save $path to database: 'uploads/photos/unique_name.jpg'
 * }
 */
function handleFileUpload($file, $folder, $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg']) {
    // Check if file was uploaded properly
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validate file size (use constant from database.php if defined)
    $maxSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return false;
    }

    // Validate MIME type using file contents (not the reported type)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedTypes)) {
        return false;
    }

    // Generate unique filename to prevent overwrites and directory traversal
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $uploadDir = UPLOAD_PATH . $folder . '/';

    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Failed to create upload directory: $uploadDir");
            return false;
        }
    }

    $destination = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return 'uploads/' . $folder . '/' . $filename;
    }
    
    return false;
}

/**
 * Safely delete an uploaded file
 * 
 * Validates the path is within the uploads directory to prevent
 * path traversal attacks, then deletes the file.
 * 
 * @param string $path Relative path to the file (e.g., 'uploads/photos/file.jpg')
 * @return bool True if file was deleted, false otherwise
 * 
 * @example
 * if (deleteFile($oldImagePath)) {
 *     // File deleted successfully
 * }
 */
function deleteFile($path) {
    if (empty($path)) {
        return false;
    }
    
    // Prevent path traversal: resolve and verify the file is inside UPLOAD_PATH
    $uploadsRoot = realpath(UPLOAD_PATH);
    $fullPath    = realpath(__DIR__ . '/../../' . $path);

    // Check that both paths resolved successfully
    if ($fullPath === false || $uploadsRoot === false) {
        return false;
    }

    // Ensure the resolved path starts with the uploads root
    if (strpos($fullPath, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0) {
        error_log("Attempted path traversal: $path");
        return false;
    }

    if (file_exists($fullPath)) {
        unlink($fullPath);
        return true;
    }
    
    return false;
}

/**
 * Get the full URL for an uploaded file
 * 
 * Validates the path to prevent URL manipulation.
 * 
 * @param string $relativePath Relative path from the database
 * @return string Full URL to the file, or empty string if invalid
 */
function getUploadUrl($relativePath) {
    if (empty($relativePath)) {
        return '';
    }
    
    // Validate path doesn't contain path traversal sequences
    if (strpos($relativePath, '..') !== false || 
        strpos($relativePath, '//') !== false ||
        strpos($relativePath, '\\') !== false) {
        error_log("Invalid upload path detected: $relativePath");
        return '';
    }
    
    // Ensure path starts with 'uploads/' or is a clean relative path
    if (strpos($relativePath, 'uploads/') !== 0 && 
        !preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $relativePath)) {
        error_log("Suspicious upload path format: $relativePath");
        return '';
    }
    
    return rtrim(BASE_URL, '/') . '/' . ltrim($relativePath, '/');
}
