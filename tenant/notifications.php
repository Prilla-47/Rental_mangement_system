<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('tenant');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    generate_tenant_alerts(db(), (int)$user['id']);
}
require __DIR__ . '/../includes/notifications_page.php';
