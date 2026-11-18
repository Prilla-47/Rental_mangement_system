<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();

$list = array_filter(tenant_rows($pdo, ['landlord_id' => $user['id'], 'status' => 'active']), fn($t) => $t['days_to_next_due'] <= 30);
usort($list, fn($a, $b) => $a['days_to_next_due'] <=> $b['days_to_next_due']);

$pageTitle = 'Upcoming Dues';
require __DIR__ . '/../includes/header.php';
?>
<p class="text-muted">Tenants whose next rent is due within the next 30 days.</p>
<div class="card"><div class="card-body"><div class="table-responsive">
    <table class="table table-hover datatable w-100">
        <thead><tr><th>Tenant</th><th>Property / Unit</th><th>Next Due Date</th><th>Due In</th><th>Monthly Rent</th><th>Current Balance</th><th>Expected On Due Date</th><th class="text-end">Action</th></tr></thead>
        <tbody>
        <?php foreach ($list as $t): ?>
            <tr>
                <td class="fw-semibold"><a href="tenant_view.php?id=<?= (int)$t['id'] ?>" class="text-decoration-none"><?= e($t['full_name']) ?></a></td>
                <td><?= e($t['property_name']) ?> - <?= e($t['unit_name']) ?></td>
                <td data-order="<?= e($t['next_due']) ?>"><?= e(fdate($t['next_due'])) ?></td>
                <td data-order="<?= (int)$t['days_to_next_due'] ?>"><span class="badge bg-<?= $t['days_to_next_due'] <= 7 ? 'warning text-dark' : 'info text-dark' ?>"><?= (int)$t['days_to_next_due'] ?> day(s)</span></td>
                <td><?= e(money($t['rent_amount'])) ?></td>
                <td><?= balance_badge((float)$t['balance']) ?></td>
                <td data-order="<?= e($t['expected_next']) ?>"><?= e(money($t['expected_next'])) ?></td>
                <td class="text-end"><a href="payments.php?tenant_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-cash-coin me-1"></i> Record payment</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
