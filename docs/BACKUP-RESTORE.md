# Catalogue backup and restore

This runbook covers the four authoritative runtime datasets: `categories.json`, `subcategories.json`, `suppliers.json`, and `products.json`. It does not back up administrator credentials, reset state, SMTP configuration, sessions, logs, rate-limit state, or other secrets.

The current registered backup domain is `catalog`. Domain names and their storage, backup, validation, lock, snapshot, and restore strategies are defined by server code in `api/includes/backup-domains.php`. Operators cannot provide filesystem paths, and unregistered or malformed domain names are rejected. The protected Admin page and catalogue-specific CLI commands reuse the same authoritative server-side operations.

## Locations and permissions

Production runtime catalogue:

```text
/home1/laxmi8ce/laxmikant_private/catalog/
```

Automatic pre-change backups and complete snapshots:

```text
/home1/laxmi8ce/laxmikant_private/backups/catalog/
/home1/laxmi8ce/laxmikant_private/backups/snapshots/
```

These paths are outside `public_html`. Private directories should be `0700` and JSON/manifest files `0600` where BigRock permits. Never copy catalogue backups below `public_html` or expose the maintenance scripts as HTTP endpoints.

Set the production private root before using a maintenance command if the hosting environment does not already provide it:

```sh
export APP_PRIVATE_ROOT=/home1/laxmi8ce/laxmikant_private
```

Run commands from the private `maintenance/` directory produced by the production packaging script.

## Protected Admin Backup & Restore

Authenticated administrators can open **Backup & Restore** from the Admin navigation. The page lists only registered domains and safe metadata for server-held catalogue snapshots and automatic dataset backups. It can create a complete catalogue snapshot, download a validated complete snapshot as a ZIP, run a non-mutating dry-run, and restore a selected server-held item.

The API requires a current credential-version-bound PHP session before returning metadata or downloads. Every POST—including dry-run—also requires the existing `X-CSRF-Token` header. Requests contain identifiers only and are limited to 4 KB; backup JSON upload is not supported. The API rejects unknown domains, malformed identifiers, traversal, absolute paths, unexpected fields, symlinks, and files outside the registered private roots. Snapshot ZIPs are generated in temporary non-public storage and deleted after streaming.

Before restore, the page displays current and selected record counts and requires typing exactly `RESTORE`. The server does not trust a prior browser result: it reacquires the catalogue lock and repeats complete validation. Complete snapshot restore first creates a coordinated rollback snapshot, atomically replaces the four datasets, and validates the resulting live catalogue. If replacement or verification fails, it attempts to restore all captured current datasets before returning an error. Dataset-backup restore retains the existing Phase 10A rollback and atomic replacement behavior.

Restoring catalogue JSON never restores, deletes, or otherwise changes runtime media. Run the report-only `php scripts/audit-runtime-media.php` command after a restore. The Admin page deliberately provides no backup upload, bulk deletion, retention settings, media cleanup, scheduling, or public backup URL.

### Deleting Backups from Admin

Admin Backup & Restore supports single-item deletion of a complete catalogue snapshot or one automatic per-dataset backup. Review the displayed type, date, domain, dataset, counts, and identifier, then type exactly `DELETE`. The browser keeps the final button disabled until the phrase matches, and the server independently requires the same exact confirmation with an authenticated session and `X-CSRF-Token` header.

A complete snapshot is deleted only after its identifier, manifest, full catalogue validation, expected five-file structure, private root, and symlink status pass inspection. An automatic dataset backup is deleted only when its dataset and filename match the existing strict allowlist and naming pattern. Deletion never modifies active `categories.json`, `subcategories.json`, `suppliers.json`, or `products.json`; it also never modifies runtime media, credentials, configuration, locks, or unrelated files.

At least one valid complete catalogue snapshot must remain. Dataset backups do not satisfy this safeguard. Rollback snapshots follow the same rule and are not treated as automatically disposable. If only one valid complete snapshot remains, deletion returns a conflict and leaves it intact.

Listing, download packaging, creation, restore reads, and deletion coordinate through the catalogue backup-management lock. Deletion receives exclusive access only to backup artifacts; it does not acquire the catalogue mutation lock. Snapshot deletion first moves the validated directory to an internal non-public quarantine name, deletes only the recognized files, and attempts to restore the original name if cleanup fails.

Before deleting an important or rollback snapshot, retain a verified off-server copy under the organization’s secure recovery policy. There is no bulk delete, purge-history, scheduled deletion, or retention-settings control.

## Automatic mutation backups

Every Admin CMS catalogue mutation acquires the global catalogue lock, reads and validates the complete live catalogue, writes an exact copy of the current dataset to `backups/catalog/`, and only then atomically replaces the live JSON file. Names have this form:

```text
products-20260826-153000-a1b2c3d4.json
```

The name identifies the dataset and UTC creation time. Retention is the newest 20 backups per dataset. Pruning only considers filenames matching that dataset's strict backup pattern, removes oldest names first, and ignores cleanup failures. It never targets the live catalogue or unrelated files.

## List and validate backups

List all backups, newest first:

```sh
php scripts/restore-catalog-backup.php --list
```

List one dataset:

```sh
php scripts/restore-catalog-backup.php --dataset=products
```

Always copy the exact filename from the listing. Validate the selected backup without changing catalogue files, backups, or media:

```sh
php scripts/restore-catalog-backup.php \
  --dataset=products \
  --backup=products-YYYYMMDD-HHMMSS-1234abcd.json \
  --dry-run
```

Dry-run checks the dataset name and filename, blocks traversal, reads and parses the backup, validates its frozen schema, combines it with the other current datasets, and validates full referential integrity. It reports current and backup record counts.

## Restore a dataset

First run the dry-run. Then run the same command without `--dry-run`:

```sh
php scripts/restore-catalog-backup.php \
  --dataset=products \
  --backup=products-YYYYMMDD-HHMMSS-1234abcd.json
```

Review the dataset, filename, and record counts. Type exactly `RESTORE` when prompted. Blank input, `y`, and other responses cancel the operation. Non-interactive automation is refused unless the operator deliberately supplies `--force`.

Under the global lock, restore re-reads the live catalogue and selected backup, validates the full proposed catalogue, creates a fresh backup of the current live dataset, and atomically installs the historical JSON. It then re-reads and validates the complete live catalogue. If post-write verification fails, it attempts an atomic rollback to the captured current data and exits non-zero.

After success, run:

```sh
php scripts/validate-runtime-catalog.php
```

Then verify the four public catalogue APIs and Admin CMS. Restoration changes JSON only and never deletes or modifies runtime media.

## Complete snapshots and off-server archives

Create a consistent point-in-time snapshot before a major import, large catalogue edit, or application upgrade:

```sh
php scripts/backup-catalog.php
```

The command holds the global lock and creates one named directory under `backups/snapshots/` containing the four exact JSON files plus a non-secret manifest with creation time and record counts. Snapshot directories are not automatically pruned; remove old snapshots manually only after confirming an off-server copy and recovery policy.

Recommended schedule:

- Automatic pre-change backups: every mutation
- Complete catalogue snapshot: weekly and before major changes
- Off-server catalogue archive: weekly for active catalogues, otherwise monthly
- Runtime-upload archive: on the same weekly/monthly schedule and after large upload batches

Use cPanel File Manager or SSH to download a compressed copy of a selected snapshot directory to an encrypted, access-controlled business computer or approved private storage. Archive only the snapshot directory. Do not add `admin.php`, `api/config.php`, reset state, sessions, logs, or credentials, and never commit production runtime JSON to GitHub.

## Media disaster recovery

Source/static images under `src/assets/` are protected by the private source repository and normal source backups. They do not need duplication in a catalogue snapshot.

Runtime-uploaded media is server-only and must be archived separately:

```text
/home1/laxmi8ce/public_html/uploads/products/
/home1/laxmi8ce/public_html/uploads/suppliers/
```

Preserve relative directories and generated filenames. Restore media without renaming it, then run `scripts/audit-runtime-media.php` from a complete private maintenance checkout. The audit reports missing references and orphan files; review them manually and do not automatically delete media during catalogue recovery.

## Complete hosting-loss recovery

1. Provision compatible Linux hosting and PHP 8.2.
2. Restore the reviewed source/build and Composer dependencies from the private GitHub repository or a trusted local source archive.
3. Recreate server-only `api/config.php`, environment values, SMTP configuration, and other secrets from a separately controlled secure record; never put them in an ordinary application-data snapshot.
4. Recreate the private `catalog`, `locks`, `backups/catalog`, `backups/snapshots`, `rate-limit`, and optional `logs` directories outside `public_html`.
5. Copy all four JSON files from one complete snapshot into the private `catalog/` directory.
6. Restore `uploads/products/` and `uploads/suppliers/` from the matching off-server media archive.
7. Securely reprovision the administrator credential using the approved CLI process, or restore it only under a separately controlled secret-backup policy.
8. Apply private-directory/file and public-media permissions; do not use `0777` unless hosting support explicitly requires it.
9. Run `php scripts/validate-runtime-catalog.php` and the report-only runtime media audit.
10. Verify all four public catalogue APIs and their counts.
11. Verify Admin CMS authentication, CSRF, catalogue reads, and one reversible test mutation.
12. Verify one controlled enquiry and recovery email without exposing credentials.
13. Reissue HTTPS certificates, verify hostname/trust, and confirm HTTP-to-HTTPS policy.

Keep the catalogue snapshot and runtime media archive until the recovered site has passed all validation and acceptance checks.

## Future Admin Modules

This section defines extension points only. Invoicing, Quotations, Customers, and Reports are not implemented, registered, or given production files by this phase. JSON remains the expected authoritative format unless a later approved design changes it; no database is part of this architecture.

### Storage and domain registry

A recommended future private layout is:

```text
private/
├── catalog/
├── business/
│   ├── invoices/
│   ├── quotations/
│   ├── customers/
│   └── reports/
├── backups/
│   ├── catalog/
│   ├── invoices/
│   ├── quotations/
│   ├── customers/
│   └── reports/
└── locks/
```

These future directories are a convention, not directories to create before their modules exist. Git ignores future `private/business/`, backup, snapshot, and lock runtime state without ignoring source code or templates.

The closed registry maps a short allowlisted domain name to server-derived storage and backup roots and module-owned strategies. CLI or browser input must never register a domain or supply an absolute path, `../` segment, backup root, or restore target. Backup filenames must be selected from a strict module-owned listing and pattern; restore readers must reject symlinks and verify the resolved object remains in the registered private root. Future domains may appear in the protected Admin tool only after their complete strategies and tests are registered; private backup directories must never be exposed as static URLs.

When a new module is implemented:

1. Define its authoritative JSON schema.
2. Define its private storage root.
3. Define its lock and lock scope.
4. Define authoritative schema, relationship, duplicate/ID, and applicable cross-module validation.
5. Register the backup domain in server code.
6. Add domain snapshot support and, if applicable, coordinated application snapshot support.
7. Add restore dry-run, rejection, rollback, atomicity, and post-restore verification tests.
8. Add its JSON and binary/runtime artifacts to off-server backup coverage.
9. Perform and document a disaster-recovery test.

Registration alone is not sufficient. Each domain must own a complete restore strategy with protected Admin and/or CLI invocation, explicit domain and backup selection, a non-mutating dry-run, validation, deliberate confirmation, a pre-restore rollback backup, atomic replacement, and post-restore verification.

### Validation and cross-module dependencies

Successful JSON parsing is never restore authorization. Every module must validate its frozen schema, relationships, and duplicate/ID rules. Future records may refer to products, customers, quotations, or invoices; the module must therefore register applicable cross-module checks against the authoritative participating domains. A restore must fail before any replacement when required related data is absent or inconsistent. Concrete schemas and relationships belong to the later module design and are intentionally not invented here.

Catalogue validation remains authoritative for the four catalogue datasets. A catalogue dataset restore continues to combine the candidate with the other live catalogue datasets and run complete catalogue schema and referential-integrity validation.

### Lock and snapshot strategies

The catalogue domain keeps its existing global catalogue mutation lock. A future invoice, quotation, or customer domain may use its own module lock or an intentionally designed shared business lock. Domains must not silently reuse the catalogue lock. If an operation changes multiple business domains, it must acquire a documented shared business lock in one consistent order and validate the complete proposed change before publishing it. No additional lock files are needed until such operations exist.

A **domain snapshot** is one validated, lock-consistent domain event, such as the current catalogue-only snapshot. A future **full application-data snapshot** must be one coordinated event containing every included domain under the locks needed to prevent mixed-time data. Its top-level manifest should carry one event ID and creation time, enumerate included domains and domain snapshot manifests/checksums, and record format versions. It must validate intra-domain and applicable cross-domain relationships before publication. It must fail as a whole if an included domain cannot be locked, read, or validated. Unavailable or unimplemented domains must not be represented by empty or invented data.

Full-application restore should be designed separately when at least two real domains exist. It must validate the entire coordinated candidate, create rollback coverage for every affected domain, and publish or roll back the set as one documented transaction.

### Reports and runtime documents

Reports need a backup domain only if they own persistent authoritative state, for example saved report definitions, schedules, report metadata, or private generated archives. Reports calculated entirely from other authoritative JSON domains have no independent data backup; they should be regenerated after restoring their sources. Do not create report storage merely because Reports may later appear in Admin.

Future invoices and quotations may produce PDFs, uploaded documents, logos/signatures, or attachments. Their JSON metadata belongs in the appropriate domain snapshot, while binary/runtime files require a separate business-document archive, comparable to the separate catalogue runtime-media archive. Preserve stable relative paths and associate the archive with the coordinated snapshot event. Validate missing and orphan references after restore; never place private documents in public storage by default and never mix secrets into document archives.

### Off-server disaster-recovery coverage

Keep recovery classes distinct:

- Source code and non-runtime assets: private GitHub repository plus normal source controls.
- Server-only configuration, SMTP settings, and environment secrets: secure manual recreation or a separately controlled secret-backup process.
- Catalogue JSON: validated private catalogue/domain snapshot.
- Future business JSON: validated business-domain or coordinated application snapshot.
- Runtime catalogue media: separate media archive.
- Future invoice/quotation documents: separate private business-document archive tied to its snapshot event.
- Admin credentials: secure CLI reprovisioning or a separately controlled secret-backup policy.

Ordinary domain and full application-data snapshots must never include SMTP credentials, Admin password hashes/reset state, API secrets, sessions, logs, or server configuration.
