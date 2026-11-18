<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

$editId = (int)get_str('id');
$tenant = null;
if ($editId) {
    $rows = tenant_rows($pdo, ['tenant_id' => $editId, 'landlord_id' => $lid, 'status' => 'active']);
    $tenant = $rows[0] ?? null;
    if (!$tenant) {
        flash('error', 'Tenant not found or inactive.');
        redirect('tenants.php');
    }
}

// Vacant units (plus the tenant's current unit when editing)
$s = $pdo->prepare('SELECT un.id, un.unit_name, un.rent_amount, un.status, p.property_name
    FROM units un JOIN properties p ON p.id = un.property_id
    WHERE p.landlord_id = ? AND (un.status = \'vacant\' OR un.id = ?) ORDER BY p.property_name, un.unit_name');
$s->execute([$lid, $tenant['unit_id'] ?? 0]);
$units = $s->fetchAll();

$form = [
    'full_name' => $tenant['full_name'] ?? '', 'email' => $tenant['email'] ?? '', 'phone' => $tenant['phone'] ?? '',
    'unit_id' => $tenant['unit_id'] ?? (int)get_str('unit_id'), 'move_in_date' => $tenant['move_in_date'] ?? date('Y-m-d'),
    'rent_due_day' => $tenant['rent_due_day'] ?? 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['full_name', 'email', 'phone', 'move_in_date'] as $k) {
        $form[$k] = post_str($k);
    }
    $form['email'] = strtolower($form['email']);
    $form['unit_id'] = (int)post_str('unit_id');
    $form['rent_due_day'] = (int)post_str('rent_due_day');
    $password = (string)($_POST['password'] ?? '');

    $errors = [];
    if ($form['full_name'] === '') $errors[] = 'Full name is required.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (!valid_date($form['move_in_date'])) $errors[] = 'Move-in date is invalid.';
    if ($form['rent_due_day'] < 1 || $form['rent_due_day'] > 28) $errors[] = 'Rent due day must be between 1 and 28.';
    if (!in_array($form['unit_id'], array_map('intval', array_column($units, 'id')), true)) $errors[] = 'Please select an available unit.';
    if (!$tenant && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($tenant && $password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';

    if (!$errors) {
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $dup->execute([$form['email'], $tenant['user_id'] ?? 0]);
        if ($dup->fetch()) $errors[] = 'That email address is already registered.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($tenant) {
                $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?')
                    ->execute([$form['full_name'], $form['email'], $form['phone'], $tenant['user_id']]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $tenant['user_id']]);
                }
                if ((int)$tenant['unit_id'] !== $form['unit_id']) {
                    $pdo->prepare("UPDATE units SET status = 'vacant' WHERE id = ?")->execute([$tenant['unit_id']]);
                    $pdo->prepare("UPDATE units SET status = 'occupied' WHERE id = ?")->execute([$form['unit_id']]);
                }
                $pdo->prepare('UPDATE tenants SET unit_id = ?, move_in_date = ?, rent_due_day = ? WHERE id = ? AND landlord_id = ?')
                    ->execute([$form['unit_id'], $form['move_in_date'], $form['rent_due_day'], $editId, $lid]);
                $msg = 'Tenant updated.';
                $goto = $editId;
            } else {
                $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?,?,?,?,'tenant')")
                    ->execute([$form['full_name'], $form['email'], $form['phone'], password_hash($password, PASSWORD_DEFAULT)]);
                $uid = (int)$pdo->lastInsertId();
                $pdo->prepare('INSERT INTO tenants (user_id, unit_id, landlord_id, move_in_date, rent_due_day) VALUES (?,?,?,?,?)')
                    ->execute([$uid, $form['unit_id'], $lid, $form['move_in_date'], $form['rent_due_day']]);
                $goto = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE units SET status = 'occupied' WHERE id = ?")->execute([$form['unit_id']]);
                notify($pdo, $uid, 'Welcome to ' . APP_NAME . '! You can view your rent balance, due dates and receipts here.');
                $msg = 'Tenant registered and assigned to the unit.';
            }
            $pdo->commit();
            flash('success', $msg);
            redirect('tenant_view.php?id=' . $goto);
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errors[] = 'Could not save the tenant. Please try again.';
        }
    }
    foreach ($errors as $err) flash('error', $err);
}

$pageTitle = $tenant ? 'Edit Tenant' : 'Register Tenant';
$activeNav = 'tenants.php';
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$units): ?>
    <div class="alert alert-warning">There are no vacant units. <a href="units.php">Add a unit</a> first.</div>
<?php endif; ?>
<div class="card" style="max-width: 820px">
    <div class="card-header"><?= e($pageTitle) ?></div>
    <div class="card-body">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Full name *</label><input type="text" name="full_name" class="form-control" maxlength="120" required value="<?= e($form['full_name']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Email (login) *</label><input type="email" name="email" class="form-control" maxlength="150" required value="<?= e($form['email']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" maxlength="30" value="<?= e($form['phone']) ?>"></div>
                <div class="col-md-6"><label class="form-label"><?= $tenant ? 'New password (leave blank to keep)' : 'Initial password *' ?></label>
                    <input type="password" name="password" class="form-control" autocomplete="new-password" <?= $tenant ? '' : 'required' ?> minlength="6"></div>
                <div class="col-12"><label class="form-label">Unit *</label>
                    <select name="unit_id" class="form-select" required>
                        <option value="">Select a unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)$form['unit_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= e($u['property_name']) ?> - <?= e($u['unit_name']) ?> (<?= e(money($u['rent_amount'])) ?>/month)
                            </option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-md-6"><label class="form-label">Move-in date *</label><input type="date" name="move_in_date" class="form-control" required value="<?= e($form['move_in_date']) ?>"></div>
                <div class="col-md-6"><label class="form-label">Rent due day of month (1-28) *</label><input type="number" name="rent_due_day" class="form-control" min="1" max="28" required value="<?= (int)$form['rent_due_day'] ?>"></div>
            </div>
            <?php if ($tenant): ?>
                <div class="alert alert-warning mt-3 mb-0 small">Changing the move-in date, due day or unit recalculates the tenant's balance from the new rent schedule.</div>
            <?php endif; ?>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" <?= $units ? '' : 'disabled' ?>><i class="bi bi-check-lg me-1"></i> <?= $tenant ? 'Save changes' : 'Register tenant' ?></button>
                <a href="tenants.php" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
