<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post_str('action') === 'deactivate') {
        $id = (int)post_str('id');
        $pdo->beginTransaction();
        $s = $pdo->prepare("UPDATE tenants SET status = 'inactive' WHERE id = ? AND landlord_id = ? AND status = 'active'");
        $s->execute([$id, $lid]);
        if ($s->rowCount()) {
            $pdo->prepare("UPDATE units un JOIN tenants t ON t.unit_id = un.id SET un.status = 'vacant' WHERE t.id = ?")->execute([$id]);
            flash('success', 'Tenant deactivated and the unit is now vacant.');
        } else {
            flash('error', 'Tenant not found or already inactive.');
        }
        $pdo->commit();
    }
    redirect('tenants.php');
}

$tenants = tenant_rows($pdo, ['landlord_id' => $lid]);

$pageTitle = 'Tenants';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">All Tenants</h2>
    <a href="tenant_form.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Register Tenant</a>
</div>
<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover datatable w-100">
            <thead><tr><th>Tenant</th><th>Contact</th><th>Property / Unit</th><th>Rent</th><th>Due Day</th><th>Balance</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($tenants as $t): ?>
                <tr>
                    <td class="fw-semibold"><a href="tenant_view.php?id=<?= (int)$t['id'] ?>" class="text-decoration-none"><?= e($t['full_name']) ?></a></td>
                    <td><div><?= e($t['email']) ?></div><div class="small text-muted"><?= e($t['phone']) ?></div></td>
                    <td><?= e($t['property_name']) ?> - <?= e($t['unit_name']) ?></td>
                    <td data-order="<?= e($t['rent_amount']) ?>"><?= e(money($t['rent_amount'])) ?></td>
                    <td><?= (int)$t['rent_due_day'] ?></td>
                    <td data-order="<?= e($t['balance']) ?>"><?= balance_badge((float)$t['balance']) ?></td>
                    <td><span class="badge bg-<?= $t['status'] === 'active' ? 'success' : 'secondary' ?> text-capitalize"><?= e($t['status']) ?></span></td>
                    <td class="text-end text-nowrap">
                        <a href="tenant_view.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View profile"><i class="bi bi-eye"></i></a>
                        <?php if ($t['status'] === 'active'): ?>
                            <a href="payments.php?tenant_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-success" title="Record payment"><i class="bi bi-cash-coin"></i></a>
                            <a href="tenant_form.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="post" class="d-inline confirm-form" data-title="Deactivate <?= e($t['full_name']) ?>?" data-text="The tenant will no longer be able to log in and the unit becomes vacant." data-confirm="Yes, deactivate">
                                <?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Deactivate"><i class="bi bi-person-x"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
