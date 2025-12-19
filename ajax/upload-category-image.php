<?php
// /linen-closet/admin/ajax/upload-category-image.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session FIRST
session_start();

// Set JSON header - NO OUTPUT BEFORE THIS
header('Content-Type: application/json');

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/linen-closet/');
}

// Simple auth check - remove debug from session to avoid issues
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'Not logged in. Please login first.'
    ]);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['image'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'No file uploaded. Please select an image.'
    ]);
    exit();
}

$file = $_FILES['image'];

// Simple validation
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.'
    ];
    
    echo json_encode([
        'success' => false, 
        'error' => 'Upload error: ' . ($errorMessages[$file['error']] ?? 'Unknown error code: ' . $file['error'])
    ]);
    exit();
}

// Check file size (max 5MB for better compatibility)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode([
        'success' => false, 
        'error' => 'File too large. Maximum size is 5MB.',
        'file_size' => round($file['size'] / 1024 / 1024, 2) . 'MB'
    ]);
    exit();
}

// Create upload directory - FIXED PATH
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/linen-closet/uploads/categories/';

// Debug directory path
$debugInfo = [
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'upload_dir' => $uploadDir,
    'dir_exists' => file_exists($uploadDir),
    'is_writable' => is_writable($uploadDir)
];

if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to create upload directory.',
            'debug' => $debugInfo
        ]);
        exit();
    }
}

// Check if directory is writable
if (!is_writable($uploadDir)) {
    // Try to fix permissions
    if (!chmod($uploadDir, 0755)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Upload directory is not writable and cannot fix permissions.',
            'debug' => $debugInfo
        ]);
        exit();
    }
}

// Generate filename
$originalName = $file['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if (!in_array($extension, $allowedExtensions)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid file type. Allowed formats: JPG, PNG, GIF, WebP',
        'file_type' => $file['type'],
        'extension' => $extension
    ]);
    exit();
}

// Clean filename
$safeName = preg_replace('/[^a-zA-Z0-9\._-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
$filename = 'category_' . $safeName . '_' . time() . '.' . $extension;
$destination = $uploadDir . $filename;

// Try to move the file
if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Return success with path
    $relativePath = 'uploads/categories/' . $filename;
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully!',
        'filepath' => $relativePath,
        'filename' => $filename,
        'url' => SITE_URL . $relativePath,
        'debug' => $debugInfo // Optional debug info
    ]);
} else {
    // Check error
    $lastError = error_get_last();
    
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to save uploaded file.',
        'error_details' => $lastError ? $lastError['message'] : 'Unknown error',
        'source' => $file['tmp_name'],
        'destination' => $destination,
        'debug' => $debugInfo
    ]);
}
?>