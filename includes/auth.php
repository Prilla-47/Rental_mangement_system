<?php
/** Session start + role-based access control. Include at the top of every page. */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function dashboard_url(string $role): string
{
    return BASE_URL . '/' . $role . '/dashboard.php';
}

/**
 * Requires a logged-in user of the given role; returns the fresh user row.
 * Anyone else is redirected to the login page (or their own dashboard).
 */
function require_role(string $role): array
{
    if (empty($_SESSION['user_id'])) {
        redirect(BASE_URL . '/index.php');
    }
    $pdo = db();
    $s = $pdo->prepare('SELECT id, full_name, email, phone, role FROM users WHERE id = ?');
    $s->execute([$_SESSION['user_id']]);
    $user = $s->fetch();
    if (!$user) {
        session_unset();
        session_destroy();
        redirect(BASE_URL . '/index.php');
    }
    if ($user['role'] === 'tenant') {
        $t = $pdo->prepare("SELECT 1 FROM tenants WHERE user_id = ? AND status = 'active'");
        $t->execute([$user['id']]);
        if (!$t->fetch()) {
            session_unset();
            session_destroy();
            redirect(BASE_URL . '/index.php');
        }
    }
    if ($user['role'] !== $role) {
        redirect(dashboard_url($user['role']));
    }
    $_SESSION['role'] = $user['role'];
    return $user;
}
