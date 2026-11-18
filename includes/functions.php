<?php
/**
 * Shared helpers: escaping, flash messages, CSRF, rent/balance logic,
 * notifications and payment recording.
 */
declare(strict_types=1);

// ---------------------------------------------------------------- basics ----
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function money(float|int|string|null $n): string
{
    return CURRENCY_SYMBOL . number_format((float)$n, 2);
}

function fdate(?string $d): string
{
    return ($d && $d !== '0000-00-00') ? date('d M Y', strtotime($d)) : '—';
}

function post_str(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function get_str(string $key, string $default = ''): string
{
    return trim((string)($_GET[$key] ?? $default));
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// ----------------------------------------------------------------- flash ----
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $cls = $f['type'] === 'error' ? 'danger' : $f['type'];
        $icon = $cls === 'success' ? 'check-circle-fill' : ($cls === 'danger' ? 'exclamation-triangle-fill' : 'info-circle-fill');
        $out .= '<div class="alert alert-' . e($cls) . ' alert-dismissible fade show" role="alert">'
            . '<i class="bi bi-' . $icon . ' me-2"></i>' . e($f['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

// ------------------------------------------------------------------ CSRF ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_check(): void
{
    $sent = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        die('Security token invalid or expired. Please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------- rent & balance logic ----
/**
 * Rent schedule for a tenant: every due date from move-in up to $today.
 * The first due date is the first occurrence of $dueDay on/after move-in.
 * Returns ['dates' => DateTimeImmutable[] (<= today), 'next' => first due date after today].
 */
function rent_schedule(string $moveIn, int $dueDay, ?DateTimeImmutable $today = null): array
{
    $today ??= new DateTimeImmutable('today');
    $dueDay = max(1, min(28, $dueDay));
    $mi = new DateTimeImmutable($moveIn);
    $d = $mi->setDate((int)$mi->format('Y'), (int)$mi->format('n'), $dueDay);
    if ($d < $mi) {
        $d = $d->add(new DateInterval('P1M'));
    }
    $dates = [];
    $guard = 0;
    while ($d <= $today && $guard++ < 1200) {
        $dates[] = $d;
        $d = $d->add(new DateInterval('P1M'));
    }
    return ['dates' => $dates, 'next' => $d];
}

/** Next $n due dates strictly after today. */
function future_due_dates(string $moveIn, int $dueDay, int $n = 12): array
{
    $next = rent_schedule($moveIn, $dueDay)['next'];
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $out[] = $next;
        $next = $next->add(new DateInterval('P1M'));
    }
    return $out;
}

/**
 * balance = (rent due since move-in) - (payments made). Positive = owing, negative = credit.
 * Adds balance details to a tenant row (needs rent_amount, move_in_date, rent_due_day, total_paid).
 */
function enrich_tenant(array $r): array
{
    $today = new DateTimeImmutable('today');
    $rent = (float)$r['rent_amount'];
    $sched = rent_schedule($r['move_in_date'], (int)$r['rent_due_day'], $today);
    $count = count($sched['dates']);
    // Calendar-month difference (no time()/strtotime()), minimum 1 month.
    $moveIn = new DateTime($r['move_in_date']);
    $now = new DateTime();
    $monthsElapsed = ((int)$now->format('Y') - (int)$moveIn->format('Y')) * 12
        + ((int)$now->format('n') - (int)$moveIn->format('n'));
    $monthsElapsed = max($monthsElapsed, 1);
    $billed = round($monthsElapsed * $rent, 2);
    $balance = round($billed - (float)$r['total_paid'], 2);

    $oldest = null;
    $days = 0;
    if ($balance > 0 && $rent > 0 && $count > 0) {
        $unpaid = min($count, (int)ceil($balance / $rent - 0.000001));
        if ($unpaid > 0) {
            $oldest = $sched['dates'][$count - $unpaid];
            $days = (int)$oldest->diff($today)->days;
        }
    }
    $r['total_billed'] = $billed;
    $r['balance'] = $balance;
    $r['oldest_unpaid_due'] = $oldest?->format('Y-m-d');
    $r['days_overdue'] = $days;
    $r['is_overdue'] = $r['status'] === 'active' && $balance > 0 && $days > 0;
    $r['next_due'] = $sched['next']->format('Y-m-d');
    $r['days_to_next_due'] = (int)$today->diff($sched['next'])->days;
    $r['expected_next'] = max(0, round($balance + $rent, 2));
    return $r;
}

/**
 * Tenants (with unit, property, user details and computed balance).
 * Filters: landlord_id, user_id, tenant_id, property_id, status.
 */
function tenant_rows(PDO $pdo, array $f = []): array
{
    $sql = 'SELECT t.*, u.full_name, u.email, u.phone, un.unit_name, un.rent_amount, un.unit_type,
                   p.id AS property_id, p.property_name,
                   COALESCE((SELECT SUM(amount_paid) FROM payments pm WHERE pm.tenant_id = t.id), 0) AS total_paid
            FROM tenants t
            JOIN users u ON u.id = t.user_id
            JOIN units un ON un.id = t.unit_id
            JOIN properties p ON p.id = un.property_id
            WHERE 1=1';
    $params = [];
    foreach (['landlord_id' => 't.landlord_id', 'user_id' => 't.user_id', 'tenant_id' => 't.id',
              'property_id' => 'p.id', 'status' => 't.status'] as $key => $col) {
        if (!empty($f[$key])) {
            $sql .= " AND $col = ?";
            $params[] = $f[$key];
        }
    }
    $sql .= ' ORDER BY u.full_name';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('enrich_tenant', $stmt->fetchAll());
}

/** Coloured balance label: OWING (red) / CLEAR (green) / IN CREDIT (blue). */
function balance_badge(float $balance, bool $withAmount = true): string
{
    if ($balance > 0) {
        return '<span class="badge bg-danger">OWING' . ($withAmount ? ' ' . e(money($balance)) : '') . '</span>';
    }
    if ($balance < 0) {
        return '<span class="badge bg-primary">IN CREDIT' . ($withAmount ? ' ' . e(money(abs($balance))) : '') . '</span>';
    }
    return '<span class="badge bg-success">CLEAR' . ($withAmount ? ' ' . e(money(0)) : '') . '</span>';
}

function balance_text_class(float $balance): string
{
    return $balance > 0 ? 'text-danger' : ($balance < 0 ? 'text-primary' : 'text-success');
}

// ---------------------------------------------------------- notifications ----
function notify(PDO $pdo, int $userId, string $message): void
{
    $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)')
        ->execute([$userId, (function_exists('mb_substr') ? mb_substr($message, 0, 500) : substr($message, 0, 500))]);
}

function unread_count(PDO $pdo, int $userId): int
{
    $s = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}

function latest_notifications(PDO $pdo, int $userId, int $limit = 5): array
{
    $s = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$limit);
    $s->execute([$userId]);
    return $s->fetchAll();
}

function notification_exists(PDO $pdo, int $userId, string $message, bool $todayOnly): bool
{
    $sql = 'SELECT 1 FROM notifications WHERE user_id = ? AND message = ?' . ($todayOnly ? ' AND DATE(created_at) = CURDATE()' : '');
    $s = $pdo->prepare($sql);
    $s->execute([$userId, $message]);
    return (bool)$s->fetchColumn();
}

/** Creates overdue / upcoming-due notifications for a landlord (de-duplicated). */
function generate_landlord_alerts(PDO $pdo, int $landlordId): void
{
    foreach (tenant_rows($pdo, ['landlord_id' => $landlordId, 'status' => 'active']) as $t) {
        if ($t['is_overdue']) {
            $msg = "Overdue: {$t['full_name']} ({$t['property_name']} - {$t['unit_name']}) owes " . money($t['balance'])
                . " - {$t['days_overdue']} day(s) overdue.";
            if (!notification_exists($pdo, $landlordId, $msg, true)) {
                notify($pdo, $landlordId, $msg);
            }
        } elseif ($t['days_to_next_due'] <= 7) {
            $msg = "Upcoming: {$t['full_name']} ({$t['unit_name']}) rent is due on " . fdate($t['next_due']) . '.';
            if (!notification_exists($pdo, $landlordId, $msg, false)) {
                notify($pdo, $landlordId, $msg);
            }
        }
    }
}

/** Creates reminder / overdue notifications for a tenant user (de-duplicated). */
function generate_tenant_alerts(PDO $pdo, int $userId): void
{
    foreach (tenant_rows($pdo, ['user_id' => $userId, 'status' => 'active']) as $t) {
        if ($t['is_overdue']) {
            $msg = 'Your rent is overdue by ' . $t['days_overdue'] . ' day(s). Amount owing: ' . money($t['balance']) . '.';
            if (!notification_exists($pdo, $userId, $msg, true)) {
                notify($pdo, $userId, $msg);
            }
        } elseif ($t['days_to_next_due'] <= 7) {
            $msg = 'Reminder: your rent of ' . money($t['rent_amount']) . ' is due on ' . fdate($t['next_due']) . '.';
            if (!notification_exists($pdo, $userId, $msg, false)) {
                notify($pdo, $userId, $msg);
            }
        }
    }
}

// --------------------------------------------------------------- payments ----
/**
 * Records a payment atomically: computes balance_before from the live ledger,
 * stores balance_after = balance_before - amount, generates the receipt number
 * (RCT-YYYYMMDD-001) and notifies the tenant.
 * @return array{payment_id:int, receipt_number:string, balance_before:float, balance_after:float}
 */
function record_payment(PDO $pdo, int $tenantId, int $landlordId, float $amount, string $date, string $method, string $notes, int $recordedBy): array
{
    if ($amount <= 0) {
        throw new RuntimeException('Amount paid must be greater than zero.');
    }
    if (!valid_date($date) || $date > date('Y-m-d')) {
        throw new RuntimeException('Payment date is invalid or in the future.');
    }
    if (!in_array($method, ['cash', 'transfer'], true)) {
        throw new RuntimeException('Invalid payment method.');
    }

    $pdo->beginTransaction();
    try {
        // Row lock serialises concurrent payments for the same tenant.
        $lock = $pdo->prepare('SELECT id FROM tenants WHERE id = ? AND landlord_id = ? AND status = \'active\' FOR UPDATE');
        $lock->execute([$tenantId, $landlordId]);
        if (!$lock->fetch()) {
            throw new RuntimeException('Tenant not found or not active.');
        }
        $rows = tenant_rows($pdo, ['tenant_id' => $tenantId, 'landlord_id' => $landlordId]);
        $t = $rows[0];
        $before = (float)$t['balance'];
        $after = round($before - $amount, 2);

        $pdo->prepare('INSERT INTO payments (tenant_id, unit_id, landlord_id, amount_paid, payment_date, payment_method,
                        balance_before, balance_after, recorded_by, notes) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$tenantId, $t['unit_id'], $landlordId, round($amount, 2), $date, $method, $before, $after, $recordedBy,
                       $notes !== '' ? $notes : null]);
        $paymentId = (int)$pdo->lastInsertId();

        // Count today's receipts, build the number, then insert (UNIQUE receipt_number guards races).
        $receiptNo = '';
        for ($try = 0; $try < 5; $try++) {
            $q = $pdo->prepare('SELECT COUNT(*) FROM receipts WHERE DATE(generated_at) = CURDATE()');
            $q->execute();
            $count = (int)$q->fetchColumn() + $try; // bump on retry after a duplicate
            $receiptNo = 'RCT-' . date('Ymd') . '-' . str_pad((string)($count + 1), 3, '0', STR_PAD_LEFT);
            try {
                $pdo->prepare('INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)')->execute([$paymentId, $receiptNo]);
                break;
            } catch (PDOException $ex) {
                if ($try === 4 || $ex->getCode() !== '23000') {
                    throw $ex;
                }
            }
        }

        $state = $after > 0 ? 'Balance owing: ' . money($after) : ($after < 0 ? 'In credit: ' . money(abs($after)) : 'Your rent is fully paid.');
        notify($pdo, (int)$t['user_id'], 'Payment of ' . money($amount) . ' received on ' . fdate($date) . '. Receipt ' . $receiptNo . '. ' . $state);
        $pdo->commit();
        return ['payment_id' => $paymentId, 'receipt_number' => $receiptNo, 'balance_before' => $before, 'balance_after' => $after];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}
