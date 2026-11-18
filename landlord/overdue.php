<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();

$list = array_filter(tenant_rows($pdo, ['landlord_id' => $user['id'], 'status' => 'active']), fn($t) => $t['is_overdue']);
usort($list, fn($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);
$totalOwing = array_sum(array_column($list, 'balance'));

$pageTitle = 'Overdue Monitor';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-sm-6"><div class="card stat-card"><div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-soft-danger me-3"><i class="bi bi-exclamation-octagon"></i></div>
        <div><div class="stat-value"><?= count($list) ?></div><div class="stat-label">Overdue tenants</div></div></div></div></div>
    <div class="col-sm-6"><div class="card stat-card"><div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-soft-warning me-3"><i class="bi bi-cash-stack"></i></div>
        <div><div class="stat-value"><?= e(money($totalOwing)) ?></div><div class="stat-label">Total outstanding from overdue tenants</div></div></div></div></div>
</div>
<div class="card"><div class="card-body"><div class="table-responsive">
    <table class="table table-hover datatable w-100">
        <thead><tr><th>Tenant</th><th>Property / Unit</th><th>Phone</th><th>Oldest Unpaid Due Date</th><th>Days Overdue</th><th>Amount Owing</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($list as $t): ?>
            <tr>
                <td class="fw-semibold"><a href="tenant_view.php?id=<?= (int)$t['id'] ?>" class="text-decoration-none"><?= e($t['full_name']) ?></a></td>
                <td><?= e($t['property_name']) ?> - <?= e($t['unit_name']) ?></td>
                <td><?= e($t['phone'] ?: '—') ?></td>
                <td data-order="<?= e($t['oldest_unpaid_due']) ?>"><?= e(fdate($t['oldest_unpaid_due'])) ?></td>
                <td data-order="<?= (int)$t['days_overdue'] ?>"><span class="badge bg-danger"><?= (int)$t['days_overdue'] ?> day(s)</span></td>
                <td data-order="<?= e($t['balance']) ?>" class="text-danger fw-semibold"><?= e(money($t['balance'])) ?></td>
                <td class="text-end"><a href="payments.php?tenant_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-success"><i class="bi bi-cash-coin me-1"></i> Record payment</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
