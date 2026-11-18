<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('landlord');
$pdo = db();
$lid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');
    $id = (int)post_str('id');

    if ($action === 'save') {
        $name = post_str('property_name');
        $address = post_str('address');
        $desc = post_str('description');
        if ($name === '' || $address === '') {
            flash('error', 'Property name and address are required.');
        } elseif ($id > 0) {
            $s = $pdo->prepare('UPDATE properties SET property_name = ?, address = ?, description = ? WHERE id = ? AND landlord_id = ?');
            $s->execute([$name, $address, $desc !== '' ? $desc : null, $id, $lid]);
            flash('success', 'Property updated.');
        } else {
            $pdo->prepare('INSERT INTO properties (landlord_id, property_name, address, description) VALUES (?,?,?,?)')
                ->execute([$lid, $name, $address, $desc !== '' ? $desc : null]);
            flash('success', 'Property added. You can now add units to it.');
        }
    } elseif ($action === 'delete' && $id > 0) {
        try {
            $s = $pdo->prepare('DELETE FROM properties WHERE id = ? AND landlord_id = ?');
            $s->execute([$id, $lid]);
            flash($s->rowCount() ? 'success' : 'error', $s->rowCount() ? 'Property deleted.' : 'Property not found.');
        } catch (PDOException $ex) {
            flash('error', 'This property cannot be deleted because its units have tenant or payment records.');
        }
    }
    redirect('properties.php');
}

$s = $pdo->prepare('SELECT p.*,
        (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id) AS unit_count,
        (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id AND u.status = \'occupied\') AS occupied_count
    FROM properties p WHERE p.landlord_id = ? ORDER BY p.property_name');
$s->execute([$lid]);
$properties = $s->fetchAll();

$pageTitle = 'Properties';
require __DIR__ . '/../includes/header.php';
$blank = e(json_encode(['id' => '', 'property_name' => '', 'address' => '', 'description' => '']));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">My Properties</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#propModal" data-modal-fill="#propModal" data-fill="<?= $blank ?>" data-title="Add Property">
        <i class="bi bi-plus-lg me-1"></i> Add Property
    </button>
</div>

<div class="card"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-hover datatable w-100">
            <thead><tr><th>Property</th><th>Address</th><th>Units</th><th>Occupied</th><th>Added</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($properties as $p): ?>
                <tr>
                    <td class="fw-semibold"><?= e($p['property_name']) ?><?php if ($p['description']): ?><div class="small text-muted fw-normal"><?= e($p['description']) ?></div><?php endif; ?></td>
                    <td><?= e($p['address']) ?></td>
                    <td><a href="units.php?property_id=<?= (int)$p['id'] ?>"><?= (int)$p['unit_count'] ?> unit(s)</a></td>
                    <td><?= (int)$p['occupied_count'] ?> / <?= (int)$p['unit_count'] ?></td>
                    <td data-order="<?= e($p['created_at']) ?>"><?= e(fdate($p['created_at'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="units.php?property_id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Units"><i class="bi bi-door-open"></i></a>
                        <button class="btn btn-sm btn-outline-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#propModal" data-modal-fill="#propModal"
                                data-title="Edit Property"
                                data-fill="<?= e(json_encode(['id' => $p['id'], 'property_name' => $p['property_name'], 'address' => $p['address'], 'description' => $p['description']])) ?>">
                            <i class="bi bi-pencil"></i></button>
                        <form method="post" class="d-inline confirm-form" data-title="Delete this property?" data-text="All its units will be deleted too. This cannot be undone." data-confirm="Yes, delete">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div></div>

<div class="modal fade" id="propModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><form method="post" class="modal-content">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
        <div class="modal-header"><h5 class="modal-title modal-title-text">Add Property</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Property name *</label><input type="text" name="property_name" class="form-control" maxlength="150" required></div>
            <div class="mb-3"><label class="form-label">Address *</label><input type="text" name="address" class="form-control" maxlength="255" required></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
    </form></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
