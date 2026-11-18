<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('tenant');
$pdo = db();

$t = tenant_rows($pdo, ['user_id' => $user['id']])[0];
$s = $pdo->prepare('SELECT pm.id, pm.amount_paid, pm.payment_date, pm.payment_method, r.receipt_number
                    FROM payments pm JOIN receipts r ON r.payment_id = pm.id
                    WHERE pm.tenant_id = ? ORDER BY r.id DESC');
$s->execute([$t['id']]);
$receipts = $s->fetchAll();

$pageTitle = 'Receipts';
require __DIR__ . '/../includes/header.php';
?>
<div class="card"><div class="card-body"><div class="table-responsive">
    <table class="table table-hover datatable w-100">
        <thead><tr><th>Receipt No.</th><th>Payment Date</th><th>Amount</th><th>Method</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
            <tr>
                <td class="fw-semibold"><?= e($r['receipt_number']) ?></td>
                <td data-order="<?= e($r['payment_date']) ?>"><?= e(fdate($r['payment_date'])) ?></td>
                <td data-order="<?= e($r['amount_paid']) ?>"><?= e(money($r['amount_paid'])) ?></td>
                <td class="text-capitalize"><?= e($r['payment_method']) ?></td>
                <td class="text-end text-nowrap">
                    <a href="receipt_download.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> View</a>
                    <a href="receipt_download.php?id=<?= (int)$r['id'] ?>&dl=1" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Download PDF</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
