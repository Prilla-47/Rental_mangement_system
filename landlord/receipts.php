<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();

$highlight = (int)get_str('highlight');
$s = $pdo->prepare('SELECT pm.id, pm.amount_paid, pm.payment_date, pm.payment_method, r.receipt_number, r.generated_at,
        u.full_name, un.unit_name, pr.property_name
    FROM payments pm
    JOIN receipts r ON r.payment_id = pm.id
    JOIN tenants t ON t.id = pm.tenant_id JOIN users u ON u.id = t.user_id
    JOIN units un ON un.id = pm.unit_id JOIN properties pr ON pr.id = un.property_id
    WHERE pm.landlord_id = ? ORDER BY r.id DESC');
$s->execute([$user['id']]);
$receipts = $s->fetchAll();

$pageTitle = 'Receipts';
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="card-body"><div class="table-responsive">
    <table class="table table-hover datatable w-100">
        <thead><tr><th>Receipt No.</th><th>Payment Date</th><th>Tenant</th><th>Property / Unit</th><th>Amount</th><th>Method</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
            <tr class="<?= $highlight === (int)$r['id'] ? 'table-success' : '' ?>">
                <td class="fw-semibold"><?= e($r['receipt_number']) ?></td>
                <td data-order="<?= e($r['payment_date']) ?>"><?= e(fdate($r['payment_date'])) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['property_name']) ?> - <?= e($r['unit_name']) ?></td>
                <td data-order="<?= e($r['amount_paid']) ?>"><?= e(money($r['amount_paid'])) ?></td>
                <td class="text-capitalize"><?= e($r['payment_method']) ?></td>
                <td class="text-end text-nowrap">
                    <a href="receipt_download.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> View / Print</a>
                    <a href="receipt_download.php?id=<?= (int)$r['id'] ?>&dl=1" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Download</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
