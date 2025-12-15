<?php
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
