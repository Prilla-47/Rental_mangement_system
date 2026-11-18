<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

$tenants = tenant_rows($pdo, ['landlord_id' => $lid, 'status' => 'active']);
$selected = (int)get_str('tenant_id');
$form = ['tenant_id' => $selected, 'amount' => '', 'payment_date' => date('Y-m-d'), 'method' => 'cash', 'notes' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = [
        'tenant_id' => (int)post_str('tenant_id'), 'amount' => post_str('amount'), 'payment_date' => post_str('payment_date'),
        'method' => post_str('payment_method'), 'notes' => post_str('notes'),
    ];
    if (!is_numeric($form['amount'])) {
        flash('error', 'Enter a valid amount.');
    } else {
        try {
            $res = record_payment($pdo, $form['tenant_id'], $lid, (float)$form['amount'], $form['payment_date'], $form['method'], $form['notes'], $lid);
            flash('success', 'Payment recorded. Receipt ' . $res['receipt_number'] . ' generated. New balance: '
                . ($res['balance_after'] > 0 ? 'owing ' . money($res['balance_after']) : ($res['balance_after'] < 0 ? 'in credit ' . money(abs($res['balance_after'])) : 'clear')) . '.');
            redirect('receipts.php?highlight=' . $res['payment_id']);
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        } catch (PDOException $ex) {
            flash('error', 'Could not record the payment. Please try again.');
        }
    }
}

$pageTitle = 'Record Payment';
$data = [];
foreach ($tenants as $t) {
    $data[$t['id']] = ['balance' => $t['balance'], 'rent' => $t['rent_amount'], 'unit' => $t['property_name'] . ' - ' . $t['unit_name'],
                       'next' => fdate($t['next_due']), 'overdue' => $t['is_overdue'] ? $t['days_overdue'] : 0];
}
$extraScripts = '<script>window.TENANT_DATA = ' . json_encode($data) . ';window.CUR = ' . json_encode(CURRENCY_SYMBOL) . ';</script>'
    . '<script src="' . BASE_URL . '/assets/js/payment.js"></script>';
require __DIR__ . '/../includes/header.php';
?>
<?php if (!$tenants): ?>
    <div class="alert alert-info">There are no active tenants yet. <a href="tenant_form.php">Register a tenant</a> to record payments.</div>
<?php endif; ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">New Payment</div>
            <div class="card-body">
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Tenant *</label>
                        <select name="tenant_id" id="tenantSelect" class="form-select" required>
                            <option value="">Select tenant</option>
                            <?php foreach ($tenants as $t): ?>
                                <option value="<?= (int)$t['id'] ?>" <?= (int)$form['tenant_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                                    <?= e($t['full_name']) ?> - <?= e($t['property_name']) ?>, <?= e($t['unit_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Amount paid *</label>
                            <div class="input-group"><span class="input-group-text"><?= e(CURRENCY_SYMBOL) ?></span>
                                <input type="number" name="amount" id="amountInput" class="form-control" min="0.01" step="0.01" required value="<?= e($form['amount']) ?>"></div></div>
                        <div class="col-md-6"><label class="form-label">Payment date *</label>
                            <input type="date" name="payment_date" class="form-control" max="<?= date('Y-m-d') ?>" required value="<?= e($form['payment_date']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Payment method *</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash" <?= $form['method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="transfer" <?= $form['method'] === 'transfer' ? 'selected' : '' ?>>Bank transfer</option>
                            </select></div>
                        <div class="col-12"><label class="form-label">Notes (optional)</label><textarea name="notes" class="form-control" rows="2" maxlength="1000"><?= e($form['notes']) ?></textarea></div>
                    </div>
                    <button class="btn btn-success mt-4" <?= $tenants ? '' : 'disabled' ?>><i class="bi bi-check-circle me-1"></i> Record payment &amp; generate receipt</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Tenant Balance</div>
            <div class="card-body" id="balancePanel">
                <p class="text-muted mb-0">Select a tenant to see their current balance.</p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
