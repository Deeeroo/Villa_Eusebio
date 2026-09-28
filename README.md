# Villa Eusebio Capstone System

PHP and MySQL reservation system for Villa Eusebio. The project runs locally through XAMPP and is organized around a public customer site plus an owner/admin dashboard.

## Main Folders

- `api/` - Form handlers and JSON endpoints. Customer booking, admin status updates, calendar blocking, archive/restore actions, and settings updates live here.
- `includes/` - Shared PHP helpers used by many pages.
  - `admin_auth.php` handles admin session security, login lockouts, CSRF token helpers, and logout cleanup.
  - `booking_availability.php` handles shared booking slot logic so customer booking, admin approval, rescheduling, and admin blocking follow the same rules.
  - `booking_repository.php` loads booking records with guest, payment, and charge details.
  - `capstone2_features.php` keeps small database migrations and shared settings/audit helpers.
  - `db.php` contains the local database connection.
- `pages/` - Customer pages and admin screens.
- `css/` - Stylesheets grouped by customer pages, admin pages, responsive fixes, and shared components.
- `js/` - Shared browser-side helper scripts.
- `assets/` - Site images, icons, customer gallery images, and public media.
- `uploads/` - Runtime customer uploads such as payment proofs. This folder should stay out of Git except for `.gitkeep`.

## Admin Security

Admin login uses hashed passwords through PHP password verification. Admin sessions now use a shared guard in `includes/admin_auth.php` with:

- session regeneration after login
- session timeout after inactivity
- HTTP-only same-site session cookies
- repeated failed-login lockout
- CSRF token on the login form
- cleaner logout that clears the session cookie

Admin-only APIs should call:

```php
require_once '../includes/admin_auth.php';
admin_require_post_csrf();
```

Admin pages that include `includes/header.php` also get the session timeout check from the shared header.

## Email Automation

Customer approval/rejection emails are sent through Gmail SMTP using the settings in `.env`.

1. Copy `.env.example` to `.env` if `.env` does not exist yet.
2. Turn on 2-Step Verification in the Gmail sender account.
3. Create a Google App Password.
4. Put the app password in `SMTP_PASSWORD`.
5. Keep `MAIL_ENABLED=true` when email sending should be active.

For deployment, change `APP_URL` to the real website domain and set the same environment values in the hosting dashboard when possible. Do not commit `.env` or share Gmail app passwords.

## Booking Rules

Booking slot logic should come from `includes/booking_availability.php`.

Use these helpers instead of copying calendar rules into new files:

- `ve_required_slots($timeType)`
- `ve_checkout_date($checkIn, $timeType)`
- `ve_check_date_availability($conn, $date, $timeType, $excludeBookingId)`
- `ve_check_block_availability($conn, $date, $stayType, $excludeBlockId)`
- `ve_replace_booking_slots($conn, $bookingId, $date, $timeType)`

This keeps customer booking, admin approval, rescheduling, and admin blocking consistent.

## Git Safety

Do not commit:

- database exports such as `.sql`
- `.env` files
- Gmail app passwords or account credentials
- uploaded payment proof files
- local cache/temp/log files

The `.gitignore` already excludes those common private/runtime files.

## Local Setup

1. Put the folder in `C:\xampp\htdocs\capstone_system`.
2. Start Apache and MySQL in XAMPP.
3. Use the `villa_eusebio_db` database locally.
4. Open `http://localhost/capstone_system/`.
5. Admin login is at `http://localhost/capstone_system/pages/owner.php`.
