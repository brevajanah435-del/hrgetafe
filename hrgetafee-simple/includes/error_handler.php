<?php
/**
 * Error Handler and Logging
 */

define('LOG_DIR', __DIR__ . '/../logs/');

// Create logs directory if not exists
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Custom Error Handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_message = "[$errno] $errstr in $errfile on line $errline";
    logError($error_message);
    
    // Log to file
    error_log($error_message, 3, LOG_DIR . 'errors.log');
    
    // Show user-friendly message
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<pre>Error: $error_message</pre>";
    } else {
        // Show generic error
        echo "An error occurred. Please contact support.";
    }
    
    return true;
});

// Custom Exception Handler
set_exception_handler(function($exception) {
    $error_message = "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine();
    logError($error_message);
    error_log($error_message, 3, LOG_DIR . 'exceptions.log');
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<pre>$error_message</pre>";
    } else {
        echo "An unexpected error occurred. Please contact support.";
    }
});

// Log Error Function
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message";
    file_put_contents(LOG_DIR . 'system.log', $log_entry . PHP_EOL, FILE_APPEND);
}

// Log Info
function logInfo($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] INFO: $message";
    file_put_contents(LOG_DIR . 'info.log', $log_entry . PHP_EOL, FILE_APPEND);
}

// Log Database Query
function logDatabaseQuery($query) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] QUERY: $query";
    file_put_contents(LOG_DIR . 'database.log', $log_entry . PHP_EOL, FILE_APPEND);
}
?>