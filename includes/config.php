<?php
session_start();

require_once __DIR__ . '/database.php';

$db = new Database();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Determine correct path based on current script location
        $scriptPath = $_SERVER['PHP_SELF'];
        if (strpos($scriptPath, '/pages/') !== false || strpos($scriptPath, '/games/') !== false) {
            $loginPath = '../pages/login.php';
        } else {
            $loginPath = 'pages/login.php';
        }
        header('Location: ' . $loginPath);
        exit;
    }
}

function getCurrentUser() {
    global $db;
    if (isLoggedIn()) {
        return $db->getUserById($_SESSION['user_id']);
    }
    return null;
}

function isAdmin() {
    $user = getCurrentUser();
    return $user && isset($user['is_admin']) && $user['is_admin'] == 1;
}

function requireAdmin() {
    if (!isLoggedIn()) {
        // Determine correct path based on current script location
        $scriptPath = $_SERVER['PHP_SELF'];
        if (strpos($scriptPath, '/pages/') !== false || strpos($scriptPath, '/games/') !== false) {
            $loginPath = '../pages/login.php';
        } else {
            $loginPath = 'pages/login.php';
        }
        header('Location: ' . $loginPath);
        exit;
    }
    if (!isAdmin()) {
        // Determine correct path based on current script location
        $scriptPath = $_SERVER['PHP_SELF'];
        if (strpos($scriptPath, '/pages/') !== false || strpos($scriptPath, '/games/') !== false) {
            $indexPath = '../index.php';
        } else {
            $indexPath = 'index.php';
        }
        header('Location: ' . $indexPath);
        exit;
    }
}

function getSetting($key, $default = null) {
    global $db;
    return $db->getSetting($key, $default);
}

// Include utility functions
require_once __DIR__ . '/functions.php';
?>
