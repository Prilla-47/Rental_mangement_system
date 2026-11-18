<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

generate_landlord_alerts($pdo, $lid);

$count = function (string $sql, array $params) use ($pdo): int {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return (int)$s->fetchColumn();
};
$totalProperties = $count('SELECT COUNT(*) FROM properties WHERE landlord_id = ?', [$lid]);
$totalUnits = $count('SELECT COUNT(*) FROM units un JOIN properties p ON p.id = un.property_id WHERE p.landlord_id = ?', [$lid]);
$vacantUnits = $count("SELECT COUNT(*) FROM units un JOIN properties p ON p.id = un.property_id WHERE p.landlord_id = ? AND un.status = 'vacant'", [$lid]);

$s = $pdo->prepare('SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE landlord_id = ? AND payment_date >= ? AND payment_date <= ?');
$s->execute([$lid, date('Y-m-01'), date('Y-m-t')]);
$incomeMonth = (float)$s->fetchColumn();

$tenants = tenant_rows($pdo, ['landlord_id' => $lid, 'status' => 'active']);
$totalTenants = count($tenants);
$overdue = count(array_filter($tenants, fn($t) => $t['is_overdue']));
$upcoming = count(array_filter($tenants, fn($t) => $t['days_to_next_due'] <= 30));

$s = $pdo->prepare('SELECT pm.*, u.full_name, un.unit_name, pr.property_name, r.receipt_number
    FROM payments pm
    JOIN tenants t ON t.id = pm.tenant_id JOIN users u ON u.id = t.user_id
    JOIN units un ON un.id = pm.unit_id JOIN properties pr ON pr.id = un.property_id
    LEFT JOIN receipts r ON r.payment_id = pm.id
    WHERE pm.landlord_id = ? ORDER BY pm.payment_date DESC, pm.id DESC LIMIT 8');
$s->execute([$lid]);
$recent = $s->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';

$cards = [
    ['Properties', $totalProperties, 'buildings', 'primary'],
    ['Total Units', $totalUnits . ' <small class="text-muted fs-6">(' . $vacantUnits . ' vacant)</small>', 'door-open', 'info'],
    ['Active Tenants', $totalTenants, 'people', 'purple'],
    ['Income This Month', e(money($incomeMonth)), 'cash-stack', 'success'],
    ['Overdue Tenants', $overdue, 'exclamation-octagon', 'danger'],
    ['Due in 30 Days', $upcoming, 'calendar-event', 'warning'],
];
?>
<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card stat-card h-100"><div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-soft-<?= $color ?> me-3"><i class="bi bi-<?= $icon ?>"></i></div>
                <div><div class="stat-value"><?= $value ?></div><div class="stat-label"><?= e($label) ?></div></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-header">Quick Actions</div>
    <div class="card-body d-flex flex-wrap gap-2">
        <a href="payments.php" class="btn btn-success"><i class="bi bi-cash-coin me-1"></i> Record Payment</a>
        <a href="tenant_form.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Add Tenant</a>
        <a href="properties.php" class="btn btn-outline-primary"><i class="bi bi-building-add me-1"></i> Add Property</a>
        <a href="units.php" class="btn btn-outline-primary"><i class="bi bi-door-open me-1"></i> Add Unit</a>
        <a href="overdue.php" class="btn btn-outline-danger"><i class="bi bi-exclamation-octagon me-1"></i> Overdue Monitor</a>
        <a href="reports.php" class="btn btn-outline-secondary"><i class="bi bi-graph-up-arrow me-1"></i> Reports</a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        Recent Payments <a href="payment_history.php" class="btn btn-sm btn-outline-primary">View all</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date</th><th>Tenant</th><th>Property / Unit</th><th>Amount</th><th>Method</th><th>Balance After</th><th>Receipt</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $p): ?>
                    <tr>
                        <td><?= e(fdate($p['payment_date'])) ?></td>
                        <td><?= e($p['full_name']) ?></td>
                        <td><?= e($p['property_name']) ?> - <?= e($p['unit_name']) ?></td>
                        <td class="fw-semibold"><?= e(money($p['amount_paid'])) ?></td>
                        <td class="text-capitalize"><?= e($p['payment_method']) ?></td>
                        <td><?= balance_badge((float)$p['balance_after']) ?></td>
                        <td><a class="btn btn-sm btn-outline-secondary" href="receipt_download.php?id=<?= (int)$p['id'] ?>" target="_blank"><i class="bi bi-file-earmark-pdf"></i> <?= e($p['receipt_number']) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
