<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('tenant');
$pdo = db();

$t = tenant_rows($pdo, ['user_id' => $user['id']])[0];
$dates = future_due_dates($t['move_in_date'], (int)$t['rent_due_day'], 12);

$pageTitle = 'Due Dates';
require __DIR__ . '/../includes/header.php';
$bal = (float)$t['balance'];
?>
<div class="alert alert-<?= $bal > 0 ? 'warning' : 'info' ?>">
    <?php if ($bal > 0): ?>
        You currently owe <strong><?= e(money($bal)) ?></strong><?= $t['is_overdue'] ? ' (overdue by ' . (int)$t['days_overdue'] . ' day(s))' : '' ?>. This is in addition to the upcoming rent below.
    <?php elseif ($bal < 0): ?>
        You are <strong>in credit by <?= e(money(abs($bal))) ?></strong>, which will be applied against your upcoming rent.
    <?php else: ?>
        Your account is <strong>clear</strong>. Thank you!
    <?php endif; ?>
</div>
<div class="card"><div class="card-header">Upcoming Rent Due Dates</div>
<div class="card-body"><div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Due Date</th><th>Day</th><th>In</th><th>Rent</th></tr></thead>
        <tbody>
        <?php $today = new DateTimeImmutable('today'); foreach ($dates as $i => $d): $in = (int)$today->diff($d)->days; ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td class="fw-semibold"><?= e($d->format('d M Y')) ?></td>
                <td><?= e($d->format('l')) ?></td>
                <td><span class="badge bg-<?= $in <= 7 ? 'warning text-dark' : 'secondary' ?>"><?= $in ?> day(s)</span></td>
                <td><?= e(money($t['rent_amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
