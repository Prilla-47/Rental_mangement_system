<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

$filterProp = (int)get_str('property_id');

$s = $pdo->prepare('SELECT id, property_name FROM properties WHERE landlord_id = ? ORDER BY property_name');
$s->execute([$lid]);
$properties = $s->fetchAll();
$propIds = array_column($properties, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');
    $id = (int)post_str('id');
    $back = 'units.php' . ($filterProp ? '?property_id=' . $filterProp : '');

    if ($action === 'save') {
        $propertyId = (int)post_str('property_id');
        $name = post_str('unit_name');
        $type = post_str('unit_type');
        $rent = post_str('rent_amount');
        $status = post_str('status', 'vacant');

        if (!in_array($propertyId, array_map('intval', $propIds), true)) {
            flash('error', 'Please choose one of your properties.');
        } elseif ($name === '' || !is_numeric($rent) || (float)$rent < 0) {
            flash('error', 'Unit name and a valid rent amount are required.');
        } elseif (!in_array($status, ['vacant', 'occupied'], true)) {
            flash('error', 'Invalid status.');
        } else {
            try {
                if ($id > 0) {
                    // A unit with an active tenant must stay occupied.
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE unit_id = ? AND status = 'active'");
                    $chk->execute([$id]);
                    if ((int)$chk->fetchColumn() > 0) {
                        $status = 'occupied';
                    }
                    $pdo->prepare('UPDATE units un JOIN properties p ON p.id = un.property_id
                                   SET un.property_id = ?, un.unit_name = ?, un.unit_type = ?, un.rent_amount = ?, un.status = ?
                                   WHERE un.id = ? AND p.landlord_id = ?')
                        ->execute([$propertyId, $name, $type, round((float)$rent, 2), $status, $id, $lid]);
                    flash('success', 'Unit updated.');
                } else {
                    $pdo->prepare('INSERT INTO units (property_id, unit_name, unit_type, rent_amount, status) VALUES (?,?,?,?,?)')
                        ->execute([$propertyId, $name, $type, round((float)$rent, 2), $status]);
                    flash('success', 'Unit added.');
                }
            } catch (PDOException $ex) {
                flash('error', $ex->getCode() === '23000' ? 'A unit with that name already exists in this property.' : 'Could not save the unit.');
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        try {
            $s = $pdo->prepare('DELETE un FROM units un JOIN properties p ON p.id = un.property_id WHERE un.id = ? AND p.landlord_id = ?');
            $s->execute([$id, $lid]);
            flash($s->rowCount() ? 'success' : 'error', $s->rowCount() ? 'Unit deleted.' : 'Unit not found.');
        } catch (PDOException $ex) {
            flash('error', 'This unit cannot be deleted because it has tenant or payment history.');
        }
    }
    redirect($back);
}

$sql = 'SELECT un.*, p.property_name,
            (SELECT u.full_name FROM tenants t JOIN users u ON u.id = t.user_id WHERE t.unit_id = un.id AND t.status = \'active\' LIMIT 1) AS tenant_name
        FROM units un JOIN properties p ON p.id = un.property_id WHERE p.landlord_id = ?';
$params = [$lid];
if ($filterProp) {
    $sql .= ' AND p.id = ?';
    $params[] = $filterProp;
}
$s = $pdo->prepare($sql . ' ORDER BY p.property_name, un.unit_name');
$s->execute($params);
$units = $s->fetchAll();

$pageTitle = 'Units';
require __DIR__ . '/../includes/header.php';
$blank = e(json_encode(['id' => '', 'property_id' => $filterProp ?: '', 'unit_name' => '', 'unit_type' => '', 'rent_amount' => '', 'status' => 'vacant']));
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="get" class="d-flex gap-2">
        <select name="property_id" class="form-select" onchange="this.form.submit()" aria-label="Filter by property">
            <option value="">All properties</option>
            <?php foreach ($properties as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $filterProp === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['property_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if ($properties): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#unitModal" data-modal-fill="#unitModal" data-fill="<?= $blank ?>" data-title="Add Unit">
            <i class="bi bi-plus-lg me-1"></i> Add Unit
        </button>
    <?php endif; ?>
</div>

<?php if (!$properties): ?>
    <div class="alert alert-info">You need to <a href="properties.php">add a property</a> before you can add units.</div>
<?php endif; ?>

<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover datatable w-100">
            <thead><tr><th>Property</th><th>Unit</th><th>Type</th><th>Rent</th><th>Status</th><th>Tenant</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($units as $u): ?>
                <tr>
                    <td><?= e($u['property_name']) ?></td>
                    <td class="fw-semibold"><?= e($u['unit_name']) ?></td>
                    <td><?= e($u['unit_type'] ?: '—') ?></td>
                    <td data-order="<?= e($u['rent_amount']) ?>"><?= e(money($u['rent_amount'])) ?></td>
                    <td><span class="badge bg-<?= $u['status'] === 'vacant' ? 'secondary' : 'success' ?> text-capitalize"><?= e($u['status']) ?></span></td>
                    <td><?= e($u['tenant_name'] ?: '—') ?></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#unitModal" data-modal-fill="#unitModal" data-title="Edit Unit"
                                data-fill="<?= e(json_encode(['id' => $u['id'], 'property_id' => $u['property_id'], 'unit_name' => $u['unit_name'], 'unit_type' => $u['unit_type'], 'rent_amount' => $u['rent_amount'], 'status' => $u['status']])) ?>">
                            <i class="bi bi-pencil"></i></button>
                        <form method="post" class="d-inline confirm-form" data-title="Delete this unit?" data-text="This cannot be undone." data-confirm="Yes, delete">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div></div>

<div class="modal fade" id="unitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
        <div class="modal-header"><h5 class="modal-title modal-title-text">Add Unit</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Property *</label>
                <select name="property_id" class="form-select" required>
                    <option value="">Select property</option>
                    <?php foreach ($properties as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['property_name']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="row g-3">
                <div class="col-sm-6"><label class="form-label">Unit name / number *</label><input type="text" name="unit_name" class="form-control" maxlength="80" required></div>
                <div class="col-sm-6"><label class="form-label">Unit type</label><input type="text" name="unit_type" class="form-control" maxlength="60" list="unitTypes" placeholder="e.g. 2 Bedroom">
                    <datalist id="unitTypes"><option>Self-contain</option><option>1 Bedroom</option><option>2 Bedroom</option><option>3 Bedroom</option><option>Shop</option><option>Office</option></datalist></div>
                <div class="col-sm-6"><label class="form-label">Rent amount (monthly) *</label><input type="number" name="rent_amount" class="form-control" min="0" step="0.01" required></div>
                <div class="col-sm-6"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="vacant">Vacant</option><option value="occupied">Occupied</option></select></div>
            </div>
            <div class="form-text mt-2">Units with an active tenant always stay occupied.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
