<?php
// /linen-closet/includes/autoload.php

// Define the root path
define('ROOT_PATH', dirname(__DIR__));

// Function to include files with absolute paths
function requireFromRoot($relativePath) {
    require_once ROOT_PATH . '/' . ltrim($relativePath, '/');
}

// Function to include files with relative paths from current file
function requireRelative($relativePath) {
    $callerPath = debug_backtrace()[0]['file'];
    $callerDir = dirname($callerPath);
    $absolutePath = realpath($callerDir . '/' . $relativePath);
    
    if ($absolutePath === false) {
        throw new Exception("File not found: $relativePath from $callerDir");
    }
    
    require_once $absolutePath;
}

// Autoload core classes
spl_autoload_register(function ($className) {
    $classFile = ROOT_PATH . '/includes/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Load configuration
requireFromRoot('includes/config.php');
?>