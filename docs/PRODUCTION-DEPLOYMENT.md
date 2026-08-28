# Laxmikant Traders production deployment

This guide prepares the BigRock Linux shared-hosting deployment. Phase 9C does not authorize connecting to or changing the live server.

## Confirmed layout

- Domain: `https://laxmikanttraders.in`
- PHP: 8.3.x, selected with cPanel MultiPHP Manager
- Public document root: `/home1/laxmi8ce/public_html/`
- Private application root: `/home1/laxmi8ce/laxmikant_private/`

## Visitor report privacy

The visitor report stores a UTC timestamp, the request IP address, an approximate city/region/country when server GeoIP data is available, and a simple device class. IP addresses may be personal data. IP-derived locations are approximate; the feature does not request GPS access, create browser fingerprints, or use advertising cookies. Records older than 45 days are automatically removed and cannot be recovered through the Visitor Reports interface. Review applicable privacy and legal requirements before long-term production use.
- Runtime uploads: `/home1/laxmi8ce/public_html/uploads/`

The private root must contain `admin.php`, `catalog/`, `business/`, `locks/`, `backups/`, `rate-limit/`, and optionally `logs/` and `analytics/`. `password-reset.json` is created only while a reset is active. Use `0700` for private directories and `0600` for credential, reset, Business JSON, and backup files where the hosting account permits it.

## Local production build and package

1. Run `npm ci` and `npm run build`. The normal build uses `/` as its Vite base and defaults to same-origin `/api` and `/uploads`.
2. Run `composer install --no-dev --optimize-autoloader`. Upload `vendor/`; Composer does not need to run on BigRock.
3. Run `php scripts/prepare-production-package.php --force`. This creates ignored `deployment/public_html/` and `deployment/laxmikant_private/` trees without credentials, SMTP configuration, live Business JSON, backups, or generated Certificate PDFs.
4. Inspect the package before any future upload. `public_html` receives the built pages, PHP API, locally installed FPDF runtime, required PDF logo, uploads protection, and root `.htaccess`. The private tree receives the validated seed catalogue and empty runtime directories only; it does not receive Business data, a real admin credential, SMTP configuration, backup artifacts, analytics, or reset state. `deployment/maintenance/` contains CLI-only maintenance code and must remain outside `public_html`.

GitHub Pages remains a separate static test build: `npm run build:github-pages`. It uses `/product-catalog/` and needs explicitly configured externally hosted API and media URLs to provide runtime features.

## Server-only environment

Configure these in the hosting environment (for example cPanel Environment Variables or an Apache/PHP configuration supported by the account):

```text
APP_PRIVATE_ROOT=/home1/laxmi8ce/laxmikant_private
APP_PUBLIC_ROOT=/home1/laxmi8ce/public_html
RUNTIME_MEDIA_ROOT=/home1/laxmi8ce/public_html/uploads
RUNTIME_MEDIA_URL_PREFIX=/uploads
PASSWORD_RESET_BASE_URL=https://laxmikanttraders.in/admin-reset-password.html
APP_ORIGIN=https://laxmikanttraders.in
```

`TRUST_PROXY_HTTPS=1` is only appropriate if BigRock confirms TLS is terminated by a trusted reverse proxy which supplies `X-Forwarded-Proto`. Normal Apache HTTPS and port 443 detection need no override.

Copy `api/config.example.php` to `api/config.php` on the server and replace placeholders. Keep it untracked and server-readable only. Configure the authenticated BigRock SMTP host, port, username, password, encryption (`tls` or `ssl`), sender, enquiry recipient, and separate recovery recipient. Suitable mailbox names might be `info@laxmikanttraders.in` or `enquiry@laxmikanttraders.in`; create and configure the actual addresses manually rather than hard-coding them.

## First private initialization

Before making the site live:

1. Create the private directory tree outside `public_html`.
2. Seed the catalogue with the existing CLI tool while `APP_PRIVATE_ROOT` points at the production private root: `php scripts/seed-runtime-catalog.php`. It refuses an initialized catalogue unless the storage tool's explicit overwrite workflow is used.
3. Validate with `php scripts/validate-runtime-catalog.php`.
4. Provision the sole administrator with `php scripts/create-admin.php`; the script writes only a password hash and refuses overwrite unless `--force` is passed.
5. Create `uploads/products/` and `uploads/suppliers/`, keep `uploads/.htaccess`, and ensure PHP can write those directories.
6. Run `php scripts/audit-runtime-media.php` with both `APP_PRIVATE_ROOT` and `RUNTIME_MEDIA_ROOT` configured.

For the first Business deployment, create `laxmikant_private/business/` with `0700` permissions or allow the authenticated Business storage layer to create it. Do not upload development fixtures. Missing `customers.json`, `receivables.json`, `refilling-items.json`, and `certificates.json` are safely initialized as empty arrays with `0600` permissions on first use. On every later deployment, preserve those four existing files byte-for-byte; application-code deployment must never replace them.

The CLI scripts are source/deployment tools, not web endpoints. Do not place them under `public_html`.

## Catalogue and Business backup and restore

Catalogue mutations retain the newest 20 exact pre-change backups per dataset under the private `backups/catalog/` directory. The private maintenance package also provides explicit backup listing, full-integrity dry runs, confirmed atomic restores with pre-restore rollback backups, and complete four-dataset snapshots. Follow `maintenance/BACKUP-RESTORE.md`; never publish the backup or maintenance directories and never commit runtime JSON or snapshot archives.

The protected Admin **Backup & Restore** page uses the registered Catalogue and Business domains. A Business snapshot contains exactly Customers, Receivables with embedded Payments, Refilling Items, and Certificates with embedded Certificate Items. It does not contain generated PDFs, calculated statuses, Catalogue data, credentials, SMTP configuration, sessions, analytics, or other secrets.

Before replacing production application code, retain a verified copy of the current private root and create a Catalogue snapshot. If the Business module is already deployed, also create and download a validated Business snapshot. On the first Business deployment, preserve the private root even though the four Business files may not exist yet.

After deployment, verify Catalogue and Business listing, create and download one snapshot for each applicable domain, and run dry-run validation. Do not perform an actual production restore merely as a smoke test. Restore is destructive and requires an independently verified backup and explicit operational authorization.

## Apache, SSL, and permissions

The root `.htaccess` disables indexes and adds conservative response headers. Its HTTPS redirect is intentionally commented until DNS and AutoSSL are confirmed. After the certificate works, enable the documented rewrite block; it excludes `/.well-known/acme-challenge/` and does not add SPA routing. Confirm both apex and `www` host behavior before enabling a permanent redirect.

The uploads `.htaccess` disables indexes, removes script handlers, and denies script-like extensions while allowing image delivery. PHP sessions automatically set `Secure` on HTTPS and remain usable on local HTTP.

Review MultiPHP INI upload/post limits so they accommodate the application limits (5 MB products, 2 MB suppliers) without broadening them unnecessarily.

## Post-deployment checks (Phase 9D)

- Confirm HTTPS and certificate validity, then enable/test redirect.
- Confirm all public pages and admin pages load at domain-root URLs.
- Check all four `/api/catalog/*.php` endpoints, ETag/304, and runtime images under `/uploads/`.
- Test password-only login, session/CSRF logout, CRUD, one controlled upload, password change, and credential-version invalidation.
- Send one controlled enquiry and one controlled recovery email; confirm sender, recipient, reset URL, and no secret disclosure.
- Confirm direct access cannot reveal configuration/private files, directory listings, backups, locks, or executable content under uploads.
- Re-run catalogue validation and media audit. Do not alter IDs while initializing production.

## Business deployment and smoke-test checklist

Use this order for the manual BigRock/cPanel deployment:

1. Back up the current `public_html` application and the complete private root outside the web root.
2. Create and validate a Catalogue snapshot. If Business already exists, create, download, and dry-run a Business snapshot.
3. Confirm `APP_PRIVATE_ROOT` still resolves to `laxmikant_private` and is not inside `public_html`.
4. Confirm `business/` is preserved, or create only the empty directory with `0700` for the first deployment. Never upload Business JSON from the package or a development machine.
5. Upload/replace application files from `deployment/public_html/`. Do not delete the server's private root, uploads, credentials, SMTP configuration, backups, analytics, sessions, or reset state.
6. Confirm PHP 8.3.x, required PHP extensions already used by the application, the packaged `vendor/setasign/fpdf/` runtime, and `api/assets/laxmikant-traders-logo.png`.
7. Confirm private directories are `0700` and private JSON/credential/backup files are `0600` where supported.
8. Open `admin-login.html`, sign in with the existing credential, and verify authenticated navigation and logout without logging credentials or tokens.
9. Open Customers, Payment Tracking, Refilling Items, Certificates, Backup & Restore, and Visitor Reports. Prefer read-only checks and opening forms; do not add fake customer, financial, master, or Certificate data solely for smoke testing.
10. Confirm selectors, searches, filters, pagination, and existing records load. If a legitimate saved Certificate exists, generate its PDF and verify the logo, title, data, footer, and page layout. Otherwise record PDF production verification as pending a real Certificate.
11. Create a Business snapshot, verify its six informational counts, download it if required by policy, and run dry-run. Do not restore live Business data as a smoke test.
12. Verify the homepage, Products, Contact, primary public assets, Catalogue APIs, and runtime images.
13. Verify a public visit can be recorded and appears in the read-only Visitor Reports flow, allowing `Unknown` location where GeoLite2 cannot resolve it.
14. Review available PHP/server logs for fatal errors, missing includes/vendor files, permission failures, session errors, and incorrect private-root resolution without copying secrets into reports.

## Rollback

For an application-code rollback, restore the previous reviewed `public_html` package while preserving `laxmikant_private`, uploads, and backups. Do not roll back or delete Business JSON merely because application code is rolled back; the V1 Business schemas are frozen. Restore Business data only when data recovery is genuinely required, after validating the selected Business snapshot and obtaining explicit authorization. Never clear or recreate the private root as part of application rollback.
