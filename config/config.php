<?php
// Define the base URL for the application
function getBaseURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove any trailing slashes
    $baseURL = rtrim($protocol . $host . $scriptDir, '/');
    
    return $baseURL;
}

// Define constants
define('BASE_URL', getBaseURL());
define('ASSETS_URL', BASE_URL . '/assets');
?> 