<?php
session_start();

require_once __DIR__ . '/database.php';

$db = new Database();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../pages/login.php');
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
        header('Location: ../pages/login.php');
        exit;
    }
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit;
    }
}

function getSetting($key, $default = null) {
    global $db;
    return $db->getSetting($key, $default);
}
?>
