# Rent Tracker

A rent and payment tracking web app for landlords and tenants.
PHP 8+ (plain, no framework), MySQL (PDO), Bootstrap 5, vanilla JavaScript, FPDF for PDF receipts.

## Setup (XAMPP)

1. Install XAMPP with PHP 8.0 or newer. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Copy the `rent-tracker` folder into `C:\xampp\htdocs\`.
3. Open **http://localhost/rent-tracker/** in your browser.

On the first request the app creates the `rent_tracker_db` database and every table, then seeds the default landlord. No SQL import is needed.
`rent_tracker_db.sql` is a backup / manual-install copy of the same schema (import it through phpMyAdmin only if you prefer).

If your MySQL user is not `root` with an empty password, edit the constants at the top of `includes/db.php`.

The pages load Bootstrap, Bootstrap Icons, jQuery, DataTables and SweetAlert2 from CDNs, so the browser needs internet access.
PHP extensions used: `pdo_mysql` and `mbstring` (both enabled in default XAMPP).

## Default login

| Role     | Email                     | Password   |
|----------|---------------------------|------------|
| Landlord | landlord@renttracker.com  | Admin1234  |

Change this password under **Profile** after the first login. Tenants are created by the landlord (**Tenants -> Register Tenant**), who sets their email and initial password.

## How balances work

* Rent is charged on the tenant's **due day** each month, starting with the first due date on or after the move-in date. Due day is limited to 1-28 so every month has it.
* `balance = total rent due since move-in - total payments made`.
* A payment stores `balance_before` (the live balance at that moment) and `balance_after = balance_before - amount_paid`.
* Overpaying makes the balance negative, which is carried forward as credit.
* Display: **OWING** (red), **CLEAR** (green), **IN CREDIT** (blue).
* A tenant is **overdue** when the balance is positive and the oldest unpaid due date has passed; days overdue count from that date.

Currency is set in `includes/db.php` (`CURRENCY_SYMBOL` for the web UI, `CURRENCY_CODE` for PDFs - core PDF fonts cannot print the naira sign, so PDFs show `NGN`).

## Folder structure

```
rent-tracker/
  index.php            Login page (landlord and tenant)
  logout.php           POST-only logout (CSRF protected)
  rent_tracker_db.sql  Schema backup
  assets/
    css/style.css      Theme (dark sidebar, cards)
    js/app.js          SweetAlert confirms, DataTables, modal helpers
    js/payment.js      Live balance preview on the Record Payment page
  includes/
    db.php             Config, PDO connection, auto-creates DB + tables
    seeder.php         Default landlord account
    auth.php           Session start, role check (require_role)
    functions.php      Escaping, flash, CSRF, rent/balance logic, notifications, record_payment()
    header.php / footer.php   Shared layout: sidebar, navbar, bell, profile menu
    receipt.php        FPDF receipt builder
    notifications_page.php, profile_page.php   Pages shared by both roles
  landlord/            dashboard, properties, units, tenants (+ tenant_form, tenant_view),
                       payments (record), payment_history, overdue, upcoming, receipts
                       (+ receipt_download), reports, notifications, profile
  tenant/              dashboard, payments, receipts (+ receipt_download), due_dates,
                       notifications, profile
  lib/fpdf/            FPDF 1.86
```

## Security notes

* All queries use PDO prepared statements; all output is escaped with `htmlspecialchars()`.
* Every POST form carries a CSRF token that is validated server-side (one token per session).
* The session ID is regenerated on login; every protected page calls `require_role()`.
* Landlords can only see and change their own data; tenants can only open their own receipts.
* `includes/` and `lib/` are blocked from direct web access by `.htaccess`.
* The optional superadmin panel from the brief was not built.
