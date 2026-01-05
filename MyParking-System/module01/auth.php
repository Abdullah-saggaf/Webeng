<?php
/**
 * Auth & Configuration
 * MyParking System - Module 01
 */

// ========== Configuration Settings ==========
// Demo mode - shows password reset link on screen (DO NOT use in production)
define('DEMO_MODE', true);

// Application settings
define('APP_NAME', 'MyParking System');
define('SESSION_LIFETIME', 3600); // 1 hour

// Password reset settings
define('RESET_TOKEN_EXPIRY_MINUTES', 15);

// ========== Auth Helpers ==========
// Auth/bootstrap helpers for Module 01 pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL path from web root to this project
if (!defined('APP_BASE_PATH')) {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $projectRoot = realpath(__DIR__ . '/..');
    
    if ($docRoot && $projectRoot && strpos($projectRoot, $docRoot) === 0) {
        $relative = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
        define('APP_BASE_PATH', '/' . trim($relative, '/'));
    } else {
        define('APP_BASE_PATH', '/Webeng/MyParking-System');
    }
}

// Full URL for QR codes (includes protocol and host for mobile access)
if (!defined('QR_BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    
    // Replace localhost with actual network IP for mobile accessibility
    if ($host === 'localhost' || $host === '127.0.0.1' || 
        strpos($host, 'localhost:') === 0 || 
        strpos($host, '127.0.0.1:') === 0 || 
        strpos($host, '[::1]') === 0) {
        
        $serverIP = '127.0.0.1';
        
        // Detect actual network IP (exclude VirtualBox adapters)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $ipconfig = shell_exec('ipconfig');
            
            if (preg_match_all('/IPv4 Address[.\s]*:\s*([0-9.]+)/', $ipconfig, $matches)) {
                foreach ($matches[1] as $ip) {
                    if ($ip !== '127.0.0.1' && 
                        !preg_match('/^192\.168\.56\./', $ip) && 
                        !preg_match('/^169\.254\./', $ip)) {
                        $serverIP = $ip;
                        break;
                    }
                }
            }
        } else {
            $serverIP = gethostbyname(gethostname());
        }
        
        $host = $serverIP;
    }
    
    define('QR_BASE_URL', $scheme . '://' . $host . APP_BASE_PATH);
}

function appUrl($path) {
    return APP_BASE_PATH . '/module01' . $path;
}

function currentUser() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'user_type' => $_SESSION['user_type'] ?? null,
    ];
}

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

// Send users to their home screen based on role
function redirectByRole($role) {
    switch ($role) {
        case 'student':
            header('Location: ' . APP_BASE_PATH . '/user.php');
            exit();
        case 'fk_staff':
            header('Location: ' . APP_BASE_PATH . '/admin.php');
            exit();
        case 'safety_staff':
            header('Location: ' . APP_BASE_PATH . '/staff.php');
            exit();
        default:
            header('Location: ' . appUrl('/login.php'));
            exit();
    }
}

// Guard pages for signed-in users
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . appUrl('/login.php'));
        exit();
    }
}

// Guard pages to specific roles
function requireRole(array $allowedRoles) {
    requireLogin();
    $role = $_SESSION['user_type'] ?? null;
    if (!in_array($role, $allowedRoles, true)) {
        redirectByRole($role);
    }
}

// Clear session and send back to login
function logoutAndRedirect() {
    header('Location: ' . appUrl('/login.php?logout=1'));
    exit();
}
?>
