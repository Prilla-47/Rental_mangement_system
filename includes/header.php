<?php
/**
 * Layout header: sidebar + top navbar.
 * Expects: $user (array from require_role), $pageTitle (string), optional $activeNav (script name).
 */
$pdo = db();
$role = $user['role'];
$activeNav = $activeNav ?? basename($_SERVER['SCRIPT_NAME']);
$unread = unread_count($pdo, (int)$user['id']);
$latest = latest_notifications($pdo, (int)$user['id'], 5);
$dir = BASE_URL . '/' . $role;

$menus = [
    'landlord' => [
        ['dashboard.php', 'speedometer2', 'Dashboard'],
        ['properties.php', 'buildings', 'Properties'],
        ['units.php', 'door-open', 'Units'],
        ['tenants.php', 'people', 'Tenants'],
        ['payments.php', 'cash-coin', 'Record Payment'],
        ['payment_history.php', 'clock-history', 'Payment History'],
        ['overdue.php', 'exclamation-octagon', 'Overdue Monitor'],
        ['upcoming.php', 'calendar-event', 'Upcoming Dues'],
        ['receipts.php', 'receipt', 'Receipts'],
        ['reports.php', 'graph-up-arrow', 'Income Reports'],
        ['notifications.php', 'bell', 'Notifications'],
        ['profile.php', 'person-gear', 'Profile'],
    ],
    'tenant' => [
        ['dashboard.php', 'speedometer2', 'Dashboard'],
        ['payments.php', 'clock-history', 'Payment History'],
        ['receipts.php', 'receipt', 'Receipts'],
        ['due_dates.php', 'calendar-event', 'Due Dates'],
        ['notifications.php', 'bell', 'Notifications'],
        ['profile.php', 'person-gear', 'Profile'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Dashboard') ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="d-flex" id="wrapper">
    <aside class="sidebar-col">
        <div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
            <div class="offcanvas-header d-lg-none">
                <h5 class="offcanvas-title text-white" id="sidebarLabel"><i class="bi bi-house-heart-fill me-2"></i><?= e(APP_NAME) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-0">
                <a href="<?= $dir ?>/dashboard.php" class="sidebar-brand d-none d-lg-flex">
                    <i class="bi bi-house-heart-fill me-2"></i><?= e(APP_NAME) ?>
                </a>
                <div class="sidebar-role"><?= $role === 'landlord' ? 'Landlord Panel' : 'Tenant Portal' ?></div>
                <ul class="nav flex-column sidebar-nav">
                    <?php foreach ($menus[$role] as [$file, $icon, $label]): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeNav === $file ? 'active' : '' ?>" href="<?= $dir ?>/<?= $file ?>">
                                <i class="bi bi-<?= $icon ?> me-2"></i><?= e($label) ?>
                                <?php if ($file === 'notifications.php' && $unread > 0): ?>
                                    <span class="badge rounded-pill bg-danger ms-auto"><?= $unread ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </aside>

    <div class="content-wrap flex-grow-1">
        <nav class="navbar topbar px-3">
            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h1 class="page-title h5 mb-0 ms-2 me-auto"><?= e($pageTitle ?? '') ?></h1>

            <div class="dropdown me-2">
                <button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                    <i class="bi bi-bell fs-5"></i>
                    <?php if ($unread > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unread > 99 ? '99+' : $unread ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 notif-menu">
                    <div class="px-3 py-2 border-bottom fw-semibold">Notifications</div>
                    <?php if (!$latest): ?>
                        <div class="px-3 py-3 text-muted small">You have no notifications.</div>
                    <?php endif; ?>
                    <?php foreach ($latest as $n): ?>
                        <a href="<?= $dir ?>/notifications.php" class="dropdown-item notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                            <div class="small text-wrap"><?= e($n['message']) ?></div>
                            <div class="text-muted" style="font-size:.7rem"><?= e(date('d M Y, H:i', strtotime($n['created_at']))) ?></div>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?= $dir ?>/notifications.php" class="dropdown-item text-center text-primary border-top py-2">View all</a>
                </div>
            </div>

            <div class="dropdown">
                <button class="btn btn-light d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-5 me-2"></i>
                    <span class="d-none d-sm-inline"><?= e($user['full_name']) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= $dir ?>/profile.php"><i class="bi bi-person-gear me-2"></i>My Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= BASE_URL ?>/logout.php" class="confirm-form" data-title="Log out?" data-text="You will need to sign in again." data-confirm="Yes, log out" data-icon="question">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>

        <main class="p-3 p-md-4">
            <?= flash_render() ?>
