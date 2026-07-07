<?php
/**
 * Security Functions
 * Handles input validation, sanitization, and CSRF protection
 */

// CSRF Token Generation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Verification
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Input Sanitization
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Email Validation
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Username Validation (Alphanumeric + underscore only)
function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
}

// Password Validation (Min 8 chars, 1 uppercase, 1 number, 1 special char)
function validatePassword($password) {
    return preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*])(.{8,})$/', $password) === 1;
}

// Phone Number Validation
function validatePhone($phone) {
    return preg_match('/^[0-9\-\+\(\)\s]{10,}$/', $phone) === 1;
}

// Password Hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify Password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Check Session and User Role
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

// Check User Role Access
function requireRole($required_roles) {
    requireLogin();
    $roles = is_array($required_roles) ? $required_roles : [$required_roles];
    if (!in_array($_SESSION['role_id'], $roles)) {
        die('Access Denied: You do not have permission to access this page.');
    }
}

// Prevent XSS
function escapeOutput($output) {
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}

// Log Security Events
function logSecurityEvent($connection, $user_id, $action, $description = '') {
    $stmt = $connection->prepare(
        "INSERT INTO security_logs (user_id, action, description, ip_address, timestamp) 
         VALUES (?, ?, ?, ?, NOW())"
    );
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param('isss', $user_id, $action, $description, $ip);
    return $stmt->execute();
}

// Rate Limiting
function checkRateLimit($key, $limit = 5, $window = 300) {
    $cache_key = 'rate_limit:' . $key;
    
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = ['count' => 0, 'reset_time' => time() + $window];
    }
    
    if (time() > $_SESSION[$cache_key]['reset_time']) {
        $_SESSION[$cache_key] = ['count' => 0, 'reset_time' => time() + $window];
    }
    
    $_SESSION[$cache_key]['count']++;
    
    return $_SESSION[$cache_key]['count'] <= $limit;
}
?>