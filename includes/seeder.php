<?php
/** Seeds the default landlord account (only if it does not exist yet). */
declare(strict_types=1);

function seed_default_landlord(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $email = 'landlord@renttracker.com';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return;
    }
    $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)')
        ->execute(['Default Landlord', $email, '08000000000', password_hash('Admin1234', PASSWORD_DEFAULT), 'landlord']);
}
