<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('tenant');
$pdo = db();

$t = tenant_rows($pdo, ['user_id' => $user['id']])[0];
$s = $pdo->prepare('SELECT pm.*, r.receipt_number FROM payments pm LEFT JOIN receipts r ON r.payment_id = pm.id
                    WHERE pm.tenant_id = ? ORDER BY pm.payment_date DESC, pm.id DESC');
$s->execute([$t['id']]);
$payments = $s->fetchAll();

$pageTitle = 'Payment History';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header d-flex justify-content-between"><span><?= count($payments) ?> payment(s)</span><span>Total paid: <strong><?= e(money($t['total_paid'])) ?></strong></span></div>
    <div class="card-body"><div class="table-responsive">
        <table class="table table-hover datatable w-100">
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Balance Before</th><th>Balance After</th><th>Notes</th><th>Receipt</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td data-order="<?= e($p['payment_date']) ?>"><?= e(fdate($p['payment_date'])) ?></td>
                    <td class="fw-semibold" data-order="<?= e($p['amount_paid']) ?>"><?= e(money($p['amount_paid'])) ?></td>
                    <td class="text-capitalize"><?= e($p['payment_method']) ?></td>
                    <td><?= e(money($p['balance_before'])) ?></td>
                    <td><?= balance_badge((float)$p['balance_after']) ?></td>
                    <td><?= e($p['notes'] ?: '—') ?></td>
                    <td><a href="receipt_download.php?id=<?= (int)$p['id'] ?>&dl=1" class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-download"></i> <?= e($p['receipt_number']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
