<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

$year = (int)get_str('year', date('Y'));
if ($year < 2000 || $year > 2100) $year = (int)date('Y');
$month = (int)get_str('month', date('n'));
if ($month < 1 || $month > 12) $month = (int)date('n');

$s = $pdo->prepare('SELECT id, property_name FROM properties WHERE landlord_id = ? ORDER BY property_name');
$s->execute([$lid]);
$props = $s->fetchAll();

// Outstanding balance per property (positive balances of active tenants)
$outstanding = [];
foreach (tenant_rows($pdo, ['landlord_id' => $lid, 'status' => 'active']) as $t) {
    $outstanding[$t['property_id']] = ($outstanding[$t['property_id']] ?? 0) + max(0, $t['balance']);
}

// Collected per property per month for the selected year
$s = $pdo->prepare('SELECT pr.id AS property_id, MONTH(pm.payment_date) AS m, SUM(pm.amount_paid) AS total
    FROM payments pm JOIN units un ON un.id = pm.unit_id JOIN properties pr ON pr.id = un.property_id
    WHERE pm.landlord_id = ? AND YEAR(pm.payment_date) = ? GROUP BY pr.id, MONTH(pm.payment_date)');
$s->execute([$lid, $year]);
$grid = [];
foreach ($s->fetchAll() as $r) {
    $grid[$r['property_id']][(int)$r['m']] = (float)$r['total'];
}

$summary = [];
$sumCollected = 0;
$sumOutstanding = 0;
foreach ($props as $p) {
    $col = $grid[$p['id']][$month] ?? 0;
    $out = $outstanding[$p['id']] ?? 0;
    $summary[] = ['name' => $p['property_name'], 'collected' => $col, 'outstanding' => $out];
    $sumCollected += $col;
    $sumOutstanding += $out;
}
$monthNames = array_map(fn($m) => date('M', mktime(0, 0, 0, $m, 1)), range(1, 12));

$pageTitle = 'Income Reports';
require __DIR__ . '/../includes/header.php';
?>
<form method="get" class="row g-2 mb-3">
    <div class="col-6 col-md-2"><select name="month" class="form-select" aria-label="Month">
        <?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option><?php endfor; ?>
    </select></div>
    <div class="col-6 col-md-2"><select name="year" class="form-select" aria-label="Year">
        <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 6; $y--): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?>
    </select></div>
    <div class="col-auto"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i> Apply</button></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card stat-card"><div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-soft-success me-3"><i class="bi bi-cash-stack"></i></div>
        <div><div class="stat-value"><?= e(money($sumCollected)) ?></div><div class="stat-label">Collected in <?= date('F', mktime(0, 0, 0, $month, 1)) ?> <?= $year ?></div></div></div></div></div>
    <div class="col-md-6"><div class="card stat-card"><div class="card-body d-flex align-items-center">
        <div class="stat-icon bg-soft-danger me-3"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="stat-value"><?= e(money($sumOutstanding)) ?></div><div class="stat-label">Currently outstanding (all active tenants)</div></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header">Collected vs Outstanding by Property - <?= date('F', mktime(0, 0, 0, $month, 1)) ?> <?= $year ?></div>
    <div class="card-body"><div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Property</th><th class="text-end">Collected</th><th class="text-end">Outstanding</th></tr></thead>
            <tbody>
            <?php foreach ($summary as $r): ?>
                <tr><td><?= e($r['name']) ?></td><td class="text-end text-success fw-semibold"><?= e(money($r['collected'])) ?></td><td class="text-end text-danger fw-semibold"><?= e(money($r['outstanding'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$summary): ?><tr><td colspan="3" class="text-center text-muted py-3">No properties yet.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($summary): ?>
                <tfoot><tr class="table-light fw-bold"><td>Total</td><td class="text-end"><?= e(money($sumCollected)) ?></td><td class="text-end"><?= e(money($sumOutstanding)) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div></div>
</div>

<div class="card">
    <div class="card-header">Monthly Income per Property - <?= $year ?></div>
    <div class="card-body"><div class="table-responsive">
        <table class="table table-sm table-hover mb-0 text-nowrap">
            <thead><tr><th>Property</th><?php foreach ($monthNames as $mn): ?><th class="text-end"><?= $mn ?></th><?php endforeach; ?><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php $colTotals = array_fill(1, 12, 0.0); foreach ($props as $p): $rowTotal = 0; ?>
                <tr>
                    <td class="fw-semibold"><?= e($p['property_name']) ?></td>
                    <?php for ($m = 1; $m <= 12; $m++): $v = $grid[$p['id']][$m] ?? 0; $rowTotal += $v; $colTotals[$m] += $v; ?>
                        <td class="text-end <?= $v ? '' : 'text-muted' ?>"><?= $v ? e(number_format($v, 2)) : '-' ?></td>
                    <?php endfor; ?>
                    <td class="text-end fw-bold"><?= e(number_format($rowTotal, 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if ($props): ?>
                <tfoot><tr class="table-light fw-bold"><td>Total</td>
                    <?php foreach ($colTotals as $v): ?><td class="text-end"><?= $v ? e(number_format($v, 2)) : '-' ?></td><?php endforeach; ?>
                    <td class="text-end"><?= e(number_format(array_sum($colTotals), 2)) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
