<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('tenant');
$pdo = db();

generate_tenant_alerts($pdo, (int)$user['id']);
$t = tenant_rows($pdo, ['user_id' => $user['id']])[0];

$s = $pdo->prepare('SELECT pm.*, r.receipt_number FROM payments pm LEFT JOIN receipts r ON r.payment_id = pm.id
                    WHERE pm.tenant_id = ? ORDER BY pm.payment_date DESC, pm.id DESC LIMIT 5');
$s->execute([$t['id']]);
$recent = $s->fetchAll();

$s = $pdo->prepare('SELECT full_name, phone, email FROM users u JOIN tenants x ON x.landlord_id = u.id WHERE x.id = ?');
$s->execute([$t['id']]);
$landlord = $s->fetch();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
$bal = (float)$t['balance'];
?>
<?php if ($t['is_overdue']): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-octagon-fill me-2"></i>Your rent is overdue by <strong><?= (int)$t['days_overdue'] ?> day(s)</strong>. Amount owing: <strong><?= e(money($bal)) ?></strong>.</div>
<?php endif; ?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-soft-primary me-3"><i class="bi bi-door-open"></i></div>
        <div><div class="stat-value fs-5"><?= e($t['unit_name']) ?></div><div class="stat-label"><?= e($t['property_name']) ?></div></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-soft-info me-3"><i class="bi bi-cash"></i></div>
        <div><div class="stat-value"><?= e(money($t['rent_amount'])) ?></div><div class="stat-label">Monthly rent</div></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-soft-<?= $bal > 0 ? 'danger' : ($bal < 0 ? 'primary' : 'success') ?> me-3"><i class="bi bi-wallet2"></i></div>
        <div><div class="stat-value <?= balance_text_class($bal) ?>"><?= e(money(abs($bal))) ?></div><div class="stat-label">Current balance</div><?= balance_badge($bal, false) ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-soft-warning me-3"><i class="bi bi-calendar-event"></i></div>
        <div><div class="stat-value fs-5"><?= e(fdate($t['next_due'])) ?></div><div class="stat-label">Next due date (in <?= (int)$t['days_to_next_due'] ?> day(s))</div></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">Recent Payments <a href="payments.php" class="btn btn-sm btn-outline-primary">View all</a></div>
            <div class="card-body"><div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Balance After</th><th>Receipt</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $p): ?>
                        <tr>
                            <td><?= e(fdate($p['payment_date'])) ?></td>
                            <td class="fw-semibold"><?= e(money($p['amount_paid'])) ?></td>
                            <td class="text-capitalize"><?= e($p['payment_method']) ?></td>
                            <td><?= balance_badge((float)$p['balance_after']) ?></td>
                            <td><a href="receipt_download.php?id=<?= (int)$p['id'] ?>&dl=1" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-download"></i> PDF</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-4">No payments yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Tenancy &amp; Landlord</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Move-in</dt><dd class="col-7"><?= e(fdate($t['move_in_date'])) ?></dd>
                    <dt class="col-5">Due day</dt><dd class="col-7"><?= (int)$t['rent_due_day'] ?> of each month</dd>
                    <dt class="col-5">Total paid</dt><dd class="col-7"><?= e(money($t['total_paid'])) ?></dd>
                    <dt class="col-5">Landlord</dt><dd class="col-7"><?= e($landlord['full_name']) ?></dd>
                    <dt class="col-5">Phone</dt><dd class="col-7"><?= e($landlord['phone'] ?: '—') ?></dd>
                    <dt class="col-5">Email</dt><dd class="col-7 text-break"><?= e($landlord['email']) ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
