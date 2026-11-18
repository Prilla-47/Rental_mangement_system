<?php
/**
 * Shared notifications page for both roles.
 * Expects $user and $role-specific alert generation to be done by the caller.
 */
$pdo = db();
$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post_str('action');
    if ($action === 'read') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([(int)post_str('id'), $uid]);
    } elseif ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
        flash('success', 'All notifications marked as read.');
    } elseif ($action === 'clear_read') {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1')->execute([$uid]);
        flash('success', 'Read notifications cleared.');
    }
    redirect(basename($_SERVER['SCRIPT_NAME']));
}

$s = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 200');
$s->execute([$uid]);
$items = $s->fetchAll();

$pageTitle = 'Notifications';
require __DIR__ . '/header.php';
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Your Notifications</h2>
    <div class="d-flex gap-2">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all me-1"></i> Mark all as read</button></form>
        <form method="post" class="confirm-form" data-title="Clear read notifications?" data-text="Read notifications will be permanently removed." data-confirm="Yes, clear">
            <?= csrf_field() ?><input type="hidden" name="action" value="clear_read"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-trash me-1"></i> Clear read</button></form>
    </div>
</div>
<div class="card">
    <ul class="list-group list-group-flush">
        <?php foreach ($items as $n): ?>
            <li class="list-group-item d-flex justify-content-between align-items-start gap-3 <?= $n['is_read'] ? '' : 'bg-primary-subtle' ?>">
                <div>
                    <i class="bi bi-<?= $n['is_read'] ? 'bell' : 'bell-fill text-primary' ?> me-2"></i><?= e($n['message']) ?>
                    <div class="small text-muted"><?= e(date('d M Y, H:i', strtotime($n['created_at']))) ?></div>
                </div>
                <?php if (!$n['is_read']): ?>
                    <form method="post" class="flex-shrink-0"><?= csrf_field() ?><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                        <button class="btn btn-sm btn-light text-nowrap">Mark as read</button></form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (!$items): ?><li class="list-group-item text-center text-muted py-4">You have no notifications.</li><?php endif; ?>
    </ul>
</div>
<?php require __DIR__ . '/footer.php'; ?>
