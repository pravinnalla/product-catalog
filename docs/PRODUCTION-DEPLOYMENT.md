# Laxmikant Traders production deployment

This guide prepares the BigRock Linux shared-hosting deployment. Phase 9C does not authorize connecting to or changing the live server.

## Confirmed layout

- Domain: `https://laxmikanttraders.in`
- PHP: 8.2, selected with cPanel MultiPHP Manager
- Public document root: `/home1/laxmi8ce/public_html/`
- Private application root: `/home1/laxmi8ce/laxmikant_private/`
- Runtime uploads: `/home1/laxmi8ce/public_html/uploads/`

The private root must contain `admin.php`, `catalog/`, `locks/`, `backups/`, `rate-limit/`, and optionally `logs/`. `password-reset.json` is created only while a reset is active. Use 0700 for private directories and 0600 for credential/reset files where the hosting account permits it.

## Local production build and package

1. Run `npm ci` and `npm run build`. The normal build uses `/` as its Vite base and defaults to same-origin `/api` and `/uploads`.
2. Run `composer install --no-dev --optimize-autoloader`. Upload `vendor/`; Composer does not need to run on BigRock.
3. Run `php scripts/prepare-production-package.php --force`. This creates ignored `deployment/public_html/` and `deployment/laxmikant_private/` trees without credentials or SMTP configuration.
4. Inspect the package before any future upload. `public_html` receives the built pages, PHP API, Composer vendor files, uploads protection, and root `.htaccess`. The private tree receives the validated catalogue only; it does not receive a real admin credential or reset state. `deployment/maintenance/` contains CLI-only catalogue backup/restore tools and their required includes; deploy it outside `public_html`.

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

The CLI scripts are source/deployment tools, not web endpoints. Do not place them under `public_html`.

## Catalogue backup and restore

Catalogue mutations retain the newest 20 exact pre-change backups per dataset under the private `backups/catalog/` directory. The private maintenance package also provides explicit backup listing, full-integrity dry runs, confirmed atomic restores with pre-restore rollback backups, and complete four-dataset snapshots. Follow `maintenance/BACKUP-RESTORE.md`; never publish the backup or maintenance directories and never commit runtime JSON or snapshot archives.

The protected Admin **Backup & Restore** page uses the same catalogue backup core. After deployment, verify listing, create and download one snapshot, and run its dry-run. Do not perform an actual production restore without separate approval.

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
