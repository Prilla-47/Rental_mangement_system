<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

$fTenant = (int)get_str('tenant_id');
$fProp = (int)get_str('property_id');
$from = get_str('from');
$to = get_str('to');
if ($from !== '' && !valid_date($from)) $from = '';
if ($to !== '' && !valid_date($to)) $to = '';

$sql = 'SELECT pm.*, u.full_name, un.unit_name, pr.property_name, r.receipt_number
    FROM payments pm
    JOIN tenants t ON t.id = pm.tenant_id JOIN users u ON u.id = t.user_id
    JOIN units un ON un.id = pm.unit_id JOIN properties pr ON pr.id = un.property_id
    LEFT JOIN receipts r ON r.payment_id = pm.id
    WHERE pm.landlord_id = ?';
$params = [$lid];
if ($fTenant) { $sql .= ' AND pm.tenant_id = ?'; $params[] = $fTenant; }
if ($fProp)   { $sql .= ' AND pr.id = ?'; $params[] = $fProp; }
if ($from)    { $sql .= ' AND pm.payment_date >= ?'; $params[] = $from; }
if ($to)      { $sql .= ' AND pm.payment_date <= ?'; $params[] = $to; }
$s = $pdo->prepare($sql . ' ORDER BY pm.payment_date DESC, pm.id DESC');
$s->execute($params);
$payments = $s->fetchAll();
$total = array_sum(array_column($payments, 'amount_paid'));

$tenantList = tenant_rows($pdo, ['landlord_id' => $lid]);
$s = $pdo->prepare('SELECT id, property_name FROM properties WHERE landlord_id = ? ORDER BY property_name');
$s->execute([$lid]);
$props = $s->fetchAll();

$pageTitle = 'Payment History';
require __DIR__ . '/../includes/header.php';
?>
<div class="card mb-3"><div class="card-body">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1">Tenant</label>
            <select name="tenant_id" class="form-select"><option value="">All tenants</option>
                <?php foreach ($tenantList as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $fTenant === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label small mb-1">Property</label>
            <select name="property_id" class="form-select"><option value="">All properties</option>
                <?php foreach ($props as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $fProp === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['property_name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">From</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">To</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Filter</button>
            <a href="payment_history.php" class="btn btn-light" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>
</div></div>

<div class="card">
    <div class="card-header d-flex justify-content-between"><span><?= count($payments) ?> payment(s)</span><span>Total: <strong><?= e(money($total)) ?></strong></span></div>
    <div class="card-body"><div class="table-responsive">
        <table class="table table-hover datatable w-100">
            <thead><tr><th>Date</th><th>Tenant</th><th>Property / Unit</th><th>Amount</th><th>Method</th><th>Before</th><th>After</th><th>Notes</th><th>Receipt</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td data-order="<?= e($p['payment_date']) ?>"><?= e(fdate($p['payment_date'])) ?></td>
                    <td><?= e($p['full_name']) ?></td>
                    <td><?= e($p['property_name']) ?> - <?= e($p['unit_name']) ?></td>
                    <td class="fw-semibold" data-order="<?= e($p['amount_paid']) ?>"><?= e(money($p['amount_paid'])) ?></td>
                    <td class="text-capitalize"><?= e($p['payment_method']) ?></td>
                    <td><?= e(money($p['balance_before'])) ?></td>
                    <td><?= balance_badge((float)$p['balance_after']) ?></td>
                    <td><?= e($p['notes'] ?: '—') ?></td>
                    <td><a href="receipt_download.php?id=<?= (int)$p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-file-earmark-pdf"></i> <?= e($p['receipt_number']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
