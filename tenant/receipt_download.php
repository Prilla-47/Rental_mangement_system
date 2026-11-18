<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/receipt.php';
$user = require_role('tenant');

$d = fetch_receipt_data(db(), (int)get_str('id'));
if (!$d || (int)$d['tenant_user_id'] !== (int)$user['id']) {
    flash('error', 'Receipt not found.');
    redirect('receipts.php');
}
render_receipt_pdf($d, get_str('dl') === '1');
