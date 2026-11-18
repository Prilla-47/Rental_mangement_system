<?php
require_once __DIR__ . '/includes/auth.php';

$pdo = db();

// Already signed in -> go to the right dashboard
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    redirect(dashboard_url($_SESSION['role']));
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(post_str('email'));
    $password = (string)($_POST['password'] ?? '');

    $s = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $s->execute([$email]);
    $u = $s->fetch();

    // Always run password_verify to keep timing similar for unknown emails
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $ok = password_verify($password, $hash) && $u;

    if ($ok && $u['role'] === 'tenant') {
        $t = $pdo->prepare("SELECT 1 FROM tenants WHERE user_id = ? AND status = 'active'");
        $t->execute([$u['id']]);
        if (!$t->fetch()) {
            $ok = false;
            $error = 'This tenant account has been deactivated. Please contact your landlord.';
        }
    }

    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        $_SESSION['role'] = $u['role'];
        unset($_SESSION['csrf']);
        redirect(dashboard_url($u['role']));
    }
    if ($error === '') {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="card login-card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <div class="login-logo"><i class="bi bi-house-heart-fill"></i></div>
            <h1 class="h4 text-center mb-1"><?= e(APP_NAME) ?></h1>
            <p class="text-center text-muted mb-4">Sign in as landlord or tenant</p>

            <?= flash_render() ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="on" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
