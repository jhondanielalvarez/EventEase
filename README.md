# EventEase

A web-based event venue reservation system for Terraza Del Rio Private Resort & Events Place.

## Features
- Customer: venue and package catalog, gallery, availability, step-by-step reservation, reservation tracking, and contact details.
- Admin: review, request GCash deposit, verify the received transaction, manage reservations, record event-day balances, view payment history and monthly reports, and manage the resort catalog and settings.
- Pending requests become confirmed only after verified deposit (except existing bookings with a saved zero-deposit requirement). Payment references are submitted through Track Reservation. A reviewed slot is held for up to 24 hours or until event start, whichever comes first; submitted references retain a hold until resolved or event start.
- GCash is manual: the application does not connect to GCash, collect credentials, or initiate transfers. The admin must verify the actual received amount and reference.
- On-site final payment is the entire remaining balance, recorded on the event date or later. Completion requires a finished and fully paid event.

## Requirements
PHP 8.2 or later with PDO MySQL, mbstring, fileinfo; MariaDB/MySQL with InnoDB; Apache with mod_rewrite/AllowOverride enabled for the supplied access restrictions; modern browser.

## Local setup (XAMPP)
1. Place this source at C:\xampp\htdocs\eventease. Start Apache and MySQL.
2. Import setup/schema.sql into a new database as an authorized database administrator. This is schema-only; no real accounts, customer bookings, or payments are included.
3. Create a dedicated application database user for eventease_db with SELECT, INSERT, UPDATE and DELETE permissions, not the database root account.
4. Create C:\xampp\eventease-private outside the web root. Copy setup/config.example.php into that directory as config.php and supply the database user's real settings. Set EVENTEASE_PRIVATE_DIR to another absolute private path only if necessary; never point it under htdocs.
5. From an authorized local command line, run the included setup/create-admin.php with PHP. It creates the initial administrator only when no existing account exists, and generates a one-time temporary password. Keep the output private and change the password after login.
6. Visit http://localhost/eventease/admin/ and configure the official catalog, deposit percentage, GCash recipient number, and contact information. Sample values are for demonstration only. Switch Catalog status to Official resort rates only after verification. Verify the business details and permission to use resort photographs before deployment.
7. Visit http://localhost/eventease/ for the customer website. Use the admin account settings to change the initial password.

## Security and repository hygiene
- Never commit config.php, .env, actual database dumps, account credentials, uploaded payment proofs, logs, test data, or backups.
- Keep the Git repository or .git directory outside the public document root. The setup and app directories are denied to HTTP requests. The public server must enforce .htaccess rules.
- The private directory holds the configuration and payment proof uploads. Back up real data privately according to the operator's retention policy.
- All payments are manually recorded; a reference alone is not proof of payment. Server-side amount, authorization, CSRF, and schedule checks remain authoritative.
- Local preview is marked noindex. Production requires HTTPS, secure configuration, official business details, concurrency and load testing, and an actual end-to-end browser acceptance test.
- Refunds, automated payment verification, automated SMS/email, chat, catering and staff scheduling are outside scope.

## Testing
Automated tests are maintained privately in the local development environment. See setup/TESTING.md for the latest documented checks and limitations. No live transfers were used during testing.
