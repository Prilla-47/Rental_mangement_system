<?php
require_once __DIR__ . '/includes/auth.php';

// Logout is POST-only with a CSRF token (prevents forced logout via links/images).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(!empty($_SESSION['role']) ? dashboard_url($_SESSION['role']) : BASE_URL . '/index.php');
}
csrf_check();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
session_regenerate_id(true);
flash('success', 'You have been logged out.');
redirect(BASE_URL . '/index.php');
