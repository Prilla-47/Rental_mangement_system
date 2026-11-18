<?php
/**
 * Database bootstrap.
 * Connects to MySQL, creates the database + all tables if they do not exist,
 * and runs the seeder. No manual SQL import is needed.
 */
declare(strict_types=1);

// ---- Configuration (XAMPP defaults) ---------------------------------------
const DB_HOST = 'localhost';
const DB_USER = 'sagltd_rent_tracker';
const DB_PASS = 'sagltd_rent_tracker';
const DB_NAME = 'sagltd_rent_tracker';

const APP_NAME        = 'Rent Tracker';
const CURRENCY_SYMBOL = '₦';   // shown in the web UI
const CURRENCY_CODE   = 'NGN'; // used in PDF receipts (core PDF fonts have no ₦ glyph)

// Base URL of the project (e.g. "/rent-tracker"), detected automatically.
if (!defined('BASE_URL')) {
    $projectRoot = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
    $docRoot     = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base = ($docRoot !== '' && str_starts_with($projectRoot, $docRoot))
        ? substr($projectRoot, strlen($docRoot))
        : '/rent-tracker';
    define('BASE_URL', rtrim($base, '/'));
}

/** Shared PDO connection (created on first use). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 1049) { // unknown database -> create it
            try {
                $tmp = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
                $tmp->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
            } catch (PDOException $e2) {
                db_fail($e2);
            }
        } else {
            db_fail($e);
        }
    }

    // Install when the last table is missing (also repairs partial installs).
    if (!$pdo->query("SHOW TABLES LIKE 'notifications'")->fetch()) {
        install_schema($pdo);
    }
    require_once __DIR__ . '/seeder.php';
    seed_default_landlord($pdo);
    return $pdo;
}

function db_fail(PDOException $e): never
{
    http_response_code(500);
    die('<h3>Database connection failed</h3><p>Make sure MySQL is running in XAMPP and the credentials in '
        . '<code>includes/db.php</code> are correct.</p><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
}

function install_schema(PDO $pdo): void
{
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            phone VARCHAR(30) NOT NULL DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('landlord','tenant') NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS properties (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            landlord_id INT UNSIGNED NOT NULL,
            property_name VARCHAR(150) NOT NULL,
            address VARCHAR(255) NOT NULL,
            description TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_prop_landlord FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS units (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            property_id INT UNSIGNED NOT NULL,
            unit_name VARCHAR(80) NOT NULL,
            unit_type VARCHAR(60) NOT NULL DEFAULT '',
            rent_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('vacant','occupied') NOT NULL DEFAULT 'vacant',
            UNIQUE KEY uq_unit (property_id, unit_name),
            CONSTRAINT fk_unit_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tenants (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL UNIQUE,
            unit_id INT UNSIGNED NOT NULL,
            landlord_id INT UNSIGNED NOT NULL,
            move_in_date DATE NOT NULL,
            rent_due_day INT NOT NULL DEFAULT 1,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            CONSTRAINT fk_tenant_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_tenant_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT,
            CONSTRAINT fk_tenant_landlord FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            unit_id INT UNSIGNED NOT NULL,
            landlord_id INT UNSIGNED NOT NULL,
            amount_paid DECIMAL(12,2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',
            balance_before DECIMAL(12,2) NOT NULL,
            balance_after DECIMAL(12,2) NOT NULL,
            recorded_by INT UNSIGNED NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_pay_date (payment_date),
            CONSTRAINT fk_pay_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_pay_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE RESTRICT,
            CONSTRAINT fk_pay_landlord FOREIGN KEY (landlord_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_pay_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS receipts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            payment_id INT UNSIGNED NOT NULL UNIQUE,
            receipt_number VARCHAR(30) NOT NULL UNIQUE,
            generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_receipt_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            message VARCHAR(500) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_notif_user (user_id, is_read),
            CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
}
