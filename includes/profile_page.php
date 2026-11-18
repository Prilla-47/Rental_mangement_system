<?php
/**
 * Shared profile page. Landlords can edit name/phone/email/password; tenants only phone/password.
 * Expects $user from require_role().
 */
$pdo = db();
$uid = (int)$user['id'];
$isLandlord = $user['role'] === 'landlord';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');

    if ($action === 'details') {
        $phone = post_str('phone');
        if ($isLandlord) {
            $name = post_str('full_name');
            $email = strtolower(post_str('email'));
            $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $dup->execute([$email, $uid]);
            if ($name === '') {
                flash('error', 'Full name is required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address.');
            } elseif ($dup->fetch()) {
                flash('error', 'That email address is already in use.');
            } else {
                $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?')->execute([$name, $email, $phone, $uid]);
                flash('success', 'Profile updated.');
            }
        } else {
            $pdo->prepare('UPDATE users SET phone = ? WHERE id = ?')->execute([$phone, $uid]);
            flash('success', 'Phone number updated.');
        }
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $s = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $s->execute([$uid]);
        if (!password_verify($current, (string)$s->fetchColumn())) {
            flash('error', 'Your current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('error', 'The new password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'The new passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', 'Password changed successfully.');
        }
    }
    redirect(basename($_SERVER['SCRIPT_NAME']));
}

$s = $pdo->prepare('SELECT full_name, email, phone, created_at FROM users WHERE id = ?');
$s->execute([$uid]);
$me = $s->fetch();
$user = array_merge($user, $me);

$pageTitle = 'My Profile';
require __DIR__ . '/header.php';
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person me-1"></i> Account Details</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="details">
                    <div class="mb-3"><label class="form-label">Full name</label>
                        <input type="text" name="full_name" class="form-control" maxlength="120" value="<?= e($me['full_name']) ?>" <?= $isLandlord ? 'required' : 'readonly disabled' ?>></div>
                    <div class="mb-3"><label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" maxlength="150" value="<?= e($me['email']) ?>" <?= $isLandlord ? 'required' : 'readonly disabled' ?>></div>
                    <div class="mb-3"><label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" maxlength="30" value="<?= e($me['phone']) ?>"></div>
                    <?php if (!$isLandlord): ?><div class="form-text mb-3">Contact your landlord to change your name or email.</div><?php endif; ?>
                    <div class="small text-muted mb-3">Member since <?= e(fdate($me['created_at'])) ?></div>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save changes</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-shield-lock me-1"></i> Change Password</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="password">
                    <div class="mb-3"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" autocomplete="current-password" required></div>
                    <div class="mb-3"><label class="form-label">New password</label><input type="password" name="new_password" class="form-control" minlength="6" autocomplete="new-password" required></div>
                    <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" name="confirm_password" class="form-control" minlength="6" autocomplete="new-password" required></div>
                    <button class="btn btn-warning"><i class="bi bi-key me-1"></i> Update password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
