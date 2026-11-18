<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

$id = (int)get_str('id');
$rows = tenant_rows($pdo, ['tenant_id' => $id, 'landlord_id' => $lid]);
if (!$rows) {
    flash('error', 'Tenant not found.');
    redirect('tenants.php');
}
$t = $rows[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'deactivate' && $t['status'] === 'active') {
    csrf_check();
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE tenants SET status = 'inactive' WHERE id = ? AND landlord_id = ?")->execute([$id, $lid]);
    $pdo->prepare("UPDATE units SET status = 'vacant' WHERE id = ?")->execute([$t['unit_id']]);
    $pdo->commit();
    flash('success', 'Tenant deactivated and the unit is now vacant.');
    redirect('tenant_view.php?id=' . $id);
}

$s = $pdo->prepare('SELECT pm.*, r.receipt_number FROM payments pm LEFT JOIN receipts r ON r.payment_id = pm.id
                    WHERE pm.tenant_id = ? ORDER BY pm.payment_date DESC, pm.id DESC');
$s->execute([$id]);
$payments = $s->fetchAll();

$pageTitle = 'Tenant Profile';
$activeNav = 'tenants.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge me-1"></i> <?= e($t['full_name']) ?></span>
                <span class="badge bg-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?> text-capitalize"><?= e($t['status']) ?></span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5">Email</dt><dd class="col-7 text-break"><?= e($t['email']) ?></dd>
                    <dt class="col-5">Phone</dt><dd class="col-7"><?= e($t['phone'] ?: '—') ?></dd>
                    <dt class="col-5">Property</dt><dd class="col-7"><?= e($t['property_name']) ?></dd>
                    <dt class="col-5">Unit</dt><dd class="col-7"><?= e($t['unit_name']) ?> <?php if ($t['unit_type']): ?><small class="text-muted">(<?= e($t['unit_type']) ?>)</small><?php endif; ?></dd>
                    <dt class="col-5">Monthly rent</dt><dd class="col-7"><?= e(money($t['rent_amount'])) ?></dd>
                    <dt class="col-5">Move-in date</dt><dd class="col-7"><?= e(fdate($t['move_in_date'])) ?></dd>
                    <dt class="col-5">Rent due day</dt><dd class="col-7"><?= (int)$t['rent_due_day'] ?> of every month</dd>
                    <dt class="col-5">Next due date</dt><dd class="col-7"><?= e(fdate($t['next_due'])) ?></dd>
                </dl>
            </div>
            <?php if ($t['status'] === 'active'): ?>
                <div class="card-footer bg-white d-flex flex-wrap gap-2">
                    <a href="payments.php?tenant_id=<?= $id ?>" class="btn btn-success btn-sm"><i class="bi bi-cash-coin me-1"></i> Record payment</a>
                    <a href="tenant_form.php?id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
                    <form method="post" class="confirm-form" data-title="Deactivate this tenant?" data-text="The tenant will no longer be able to log in and the unit becomes vacant." data-confirm="Yes, deactivate">
                        <?= csrf_field() ?><input type="hidden" name="action" value="deactivate">
                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-person-x me-1"></i> Deactivate</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Account Summary</div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-sm-4"><div class="text-muted small">Total rent due</div><div class="fs-5 fw-bold"><?= e(money($t['total_billed'])) ?></div></div>
                    <div class="col-sm-4"><div class="text-muted small">Total paid</div><div class="fs-5 fw-bold"><?= e(money($t['total_paid'])) ?></div></div>
                    <div class="col-sm-4"><div class="text-muted small">Current balance</div><div class="fs-5 fw-bold <?= balance_text_class((float)$t['balance']) ?>"><?= e(money(abs($t['balance']))) ?></div><?= balance_badge((float)$t['balance'], false) ?></div>
                </div>
                <?php if ($t['is_overdue']): ?>
                    <div class="alert alert-danger mt-3 mb-0"><i class="bi bi-exclamation-octagon me-1"></i> Overdue by <strong><?= (int)$t['days_overdue'] ?> day(s)</strong> (oldest unpaid due date <?= e(fdate($t['oldest_unpaid_due'])) ?>).</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Payment History</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable w-100">
                <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Balance Before</th><th>Balance After</th><th>Notes</th><th>Receipt</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td data-order="<?= e($p['payment_date']) ?>"><?= e(fdate($p['payment_date'])) ?></td>
                        <td class="fw-semibold"><?= e(money($p['amount_paid'])) ?></td>
                        <td class="text-capitalize"><?= e($p['payment_method']) ?></td>
                        <td><?= e(money($p['balance_before'])) ?></td>
                        <td><?= balance_badge((float)$p['balance_after']) ?></td>
                        <td><?= e($p['notes'] ?: '—') ?></td>
                        <td><a href="receipt_download.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> <?= e($p['receipt_number']) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
