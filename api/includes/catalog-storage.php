<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog-validation.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/backup-domains.php';

final class CatalogStorageException extends RuntimeException
{
}

const CATALOG_BACKUP_RETENTION_PER_DATASET = 20;

function catalog_private_root(): string
{
    return app_private_root();
}

function catalog_directory(): string
{
    return catalog_private_root() . '/catalog';
}

function catalog_lock_directory(): string
{
    return catalog_private_root() . '/locks';
}

function catalog_backup_directory(): string
{
    return catalog_private_root() . '/backups/catalog';
}

function catalog_snapshot_directory(): string
{
    return catalog_private_root() . '/backups/snapshots';
}

function catalog_dataset_path(string $dataset): string
{
    if (!in_array($dataset, catalog_dataset_names(), true)) {
        throw new CatalogStorageException('Unsupported catalogue dataset.');
    }

    return catalog_directory() . '/' . $dataset . '.json';
}

function catalog_ensure_directory(string $directory): void
{
    if (is_link($directory)) {
        throw new CatalogStorageException('Private runtime storage must not be a symbolic link.');
    }

    if (is_dir($directory)) {
        return;
    }

    if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new CatalogStorageException('Unable to prepare private runtime storage.');
    }

    @chmod($directory, 0700);
}

function catalog_ensure_runtime_directories(): void
{
    catalog_ensure_directory(catalog_directory());
    catalog_ensure_directory(catalog_lock_directory());
    catalog_ensure_directory(catalog_backup_directory());
}

/**
 * @return resource
 */
function catalog_acquire_mutation_lock()
{
    catalog_ensure_runtime_directories();
    $handle = @fopen(catalog_lock_directory() . '/catalog.lock', 'c');

    if ($handle === false) {
        throw new CatalogStorageException('Unable to open the catalogue mutation lock.');
    }

    @chmod(catalog_lock_directory() . '/catalog.lock', 0600);

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new CatalogStorageException('Unable to acquire the catalogue mutation lock.');
    }

    return $handle;
}

/**
 * @param resource $handle
 */
function catalog_release_mutation_lock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

/** @return resource */
function catalog_acquire_backup_management_lock(bool $exclusive)
{
    catalog_ensure_directory(catalog_lock_directory());
    $path = catalog_lock_directory() . '/catalog-backups.lock';
    if (is_link($path)) throw new CatalogStorageException('The backup-management lock is unsafe.');
    $handle = @fopen($path, 'c');
    if ($handle === false) throw new CatalogStorageException('Unable to open the backup-management lock.');
    @chmod($path, 0600);
    if (!flock($handle, $exclusive ? LOCK_EX : LOCK_SH)) {
        fclose($handle);
        throw new CatalogStorageException('Unable to acquire the backup-management lock.');
    }
    return $handle;
}

/** @param resource $handle */
function catalog_release_backup_management_lock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

/**
 * @return list<mixed>
 */
function catalog_read_json_file(string $dataset, string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new CatalogStorageException(
            sprintf('The %s runtime dataset is unavailable.', $dataset)
        );
    }

    $contents = @file_get_contents($path);

    if ($contents === false) {
        throw new CatalogStorageException(
            sprintf('The %s runtime dataset could not be read.', $dataset)
        );
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new CatalogStorageException(
            sprintf('The %s runtime dataset contains malformed JSON.', $dataset),
            0,
            $exception
        );
    }

    if (!is_array($decoded) || !array_is_list($decoded)) {
        throw new CatalogStorageException(
            sprintf('The %s runtime dataset must contain a top-level array.', $dataset)
        );
    }

    return $decoded;
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function catalog_read_all(): array
{
    $catalog = [];

    foreach (catalog_dataset_names() as $dataset) {
        $catalog[$dataset] = catalog_read_json_file(
            $dataset,
            catalog_dataset_path($dataset)
        );
    }

    try {
        return catalog_validate_all($catalog);
    } catch (CatalogValidationException $exception) {
        throw new CatalogStorageException(
            'The runtime catalogue failed validation: ' . $exception->getMessage(),
            0,
            $exception
        );
    }
}

/**
 * @return list<array<string, mixed>>
 */
function catalog_read_dataset(string $dataset): array
{
    $path = catalog_dataset_path($dataset);
    unset($path);

    return catalog_read_all()[$dataset];
}

function catalog_encode_json(array $records): string
{
    try {
        return json_encode(
            $records,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
    } catch (JsonException $exception) {
        throw new CatalogStorageException(
            'The catalogue data could not be encoded.',
            0,
            $exception
        );
    }
}

function catalog_write_complete_file(string $path, string $contents): void
{
    $handle = @fopen($path, 'xb');

    if ($handle === false) {
        throw new CatalogStorageException('Unable to create a private catalogue file.');
    }

    $completed = false;

    try {
        $length = strlen($contents);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($handle, substr($contents, $written));

            if ($result === false || $result === 0) {
                throw new CatalogStorageException('Unable to write a private catalogue file.');
            }

            $written += $result;
        }

        if (!fflush($handle)) {
            throw new CatalogStorageException('Unable to flush a private catalogue file.');
        }

        if (function_exists('fsync') && !fsync($handle)) {
            throw new CatalogStorageException('Unable to synchronize a private catalogue file.');
        }

        $completed = true;
    } finally {
        fclose($handle);

        if (!$completed && is_file($path)) {
            @unlink($path);
        }
    }

    @chmod($path, 0600);
}

function catalog_atomic_replace(string $targetPath, string $contents): void
{
    $temporaryPath = $targetPath . '.tmp-' . bin2hex(random_bytes(8));

    try {
        catalog_write_complete_file($temporaryPath, $contents);

        if (!@rename($temporaryPath, $targetPath)) {
            throw new CatalogStorageException('Unable to replace the runtime catalogue safely.');
        }

        @chmod($targetPath, 0600);
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
    }
}

function catalog_backup_filename_pattern(string $dataset): string
{
    catalog_dataset_path($dataset);

    return '/^' . preg_quote($dataset, '/') . '-\d{8}-\d{6}-[a-f0-9]{8}\.json$/D';
}

function catalog_backup_path(string $dataset, string $filename): string
{
    if (is_link(catalog_backup_directory())) {
        throw new CatalogStorageException('Catalogue backup storage must not be a symbolic link.');
    }
    if (basename($filename) !== $filename
        || preg_match(catalog_backup_filename_pattern($dataset), $filename) !== 1) {
        throw new CatalogStorageException('Invalid catalogue backup filename.');
    }

    return catalog_backup_directory() . '/' . $filename;
}

/**
 * @return list<array{dataset: string, timestamp: string, filename: string}>
 */
function catalog_list_backups(?string $requestedDataset = null): array
{
    if ($requestedDataset !== null) {
        catalog_dataset_path($requestedDataset);
    }

    if (!is_dir(catalog_backup_directory())) {
        return [];
    }

    $backups = [];
    foreach (scandir(catalog_backup_directory()) ?: [] as $filename) {
        foreach (catalog_dataset_names() as $dataset) {
            if (($requestedDataset === null || $requestedDataset === $dataset)
                && preg_match(catalog_backup_filename_pattern($dataset), $filename) === 1
                && is_file(catalog_backup_directory() . '/' . $filename)) {
                $backups[] = [
                    'dataset' => $dataset,
                    'timestamp' => substr($filename, strlen($dataset) + 1, 15),
                    'filename' => $filename,
                ];
                break;
            }
        }
    }

    usort(
        $backups,
        static fn(array $left, array $right): int =>
            ($right['timestamp'] <=> $left['timestamp'])
                ?: ($right['filename'] <=> $left['filename'])
    );

    return $backups;
}

/**
 * @return array{records: list<array<string, mixed>>, contents: string}
 */
function catalog_read_backup(string $dataset, string $filename): array
{
    $path = catalog_backup_path($dataset, $filename);
    if (is_link($path) || !is_file($path) || !is_readable($path)) {
        throw new CatalogStorageException('The requested catalogue backup is unavailable.');
    }

    $contents = @file_get_contents($path);
    if ($contents === false) {
        throw new CatalogStorageException('The requested catalogue backup could not be read.');
    }

    return [
        'records' => catalog_read_json_file($dataset, $path),
        'contents' => $contents,
    ];
}

function catalog_create_backup(string $dataset, string $contents): string
{
    catalog_ensure_directory(catalog_backup_directory());
    $timestamp = gmdate('Ymd-His');
    $suffix = bin2hex(random_bytes(4));
    $backupPath = sprintf(
        '%s/%s-%s-%s.json',
        catalog_backup_directory(),
        $dataset,
        $timestamp,
        $suffix
    );

    catalog_write_complete_file($backupPath, $contents);
    catalog_prune_backups($dataset, CATALOG_BACKUP_RETENTION_PER_DATASET);

    return basename($backupPath);
}

function catalog_prune_backups(string $dataset, int $limit): void
{
    catalog_dataset_path($dataset);
    $files = array_map(
        static fn(array $backup): string => catalog_backup_directory() . '/' . $backup['filename'],
        catalog_list_backups($dataset)
    );

    if ($files === false || count($files) <= $limit) {
        return;
    }

    foreach (array_slice($files, $limit) as $file) {
        @unlink($file);
    }
}

/**
 * Validate a historical dataset against the complete current catalogue.
 *
 * @return array{dataset: string, backup: string, currentCount: int, backupCount: int}
 */
function catalog_restore_dry_run(string $dataset, string $filename): array
{
    $lock = catalog_acquire_mutation_lock();
    $backupLock = null;
    try {
        $backupLock = catalog_acquire_backup_management_lock(false);
        $currentCatalog = catalog_read_all();
        $backup = catalog_read_backup($dataset, $filename);
        $proposedCatalog = $currentCatalog;
        $proposedCatalog[$dataset] = $backup['records'];
        catalog_validate_all($proposedCatalog);

        return [
            'dataset' => $dataset,
            'backup' => $filename,
            'currentCount' => count($currentCatalog[$dataset]),
            'backupCount' => count($backup['records']),
        ];
    } finally {
        if (is_resource($backupLock)) catalog_release_backup_management_lock($backupLock);
        catalog_release_mutation_lock($lock);
    }
}

/**
 * Restore one historical dataset and preserve the current file as a rollback backup.
 *
 * @return array{dataset: string, backup: string, rollbackBackup: string, recordCount: int}
 */
function catalog_restore_backup(string $dataset, string $filename): array
{
    $lock = catalog_acquire_mutation_lock();
    $backupLock = null;
    try {
        $backupLock = catalog_acquire_backup_management_lock(false);
        $currentCatalog = catalog_read_all();
        $backup = catalog_read_backup($dataset, $filename);
        $proposedCatalog = $currentCatalog;
        $proposedCatalog[$dataset] = $backup['records'];
        $validated = catalog_validate_all($proposedCatalog);

        $targetPath = catalog_dataset_path($dataset);
        $currentContents = @file_get_contents($targetPath);
        if ($currentContents === false) {
            throw new CatalogStorageException('Unable to read the current dataset for rollback backup.');
        }

        $rollbackBackup = catalog_create_backup($dataset, $currentContents);
        try {
            catalog_atomic_replace($targetPath, $backup['contents']);
            $restored = catalog_read_all();
            if ($restored[$dataset] !== $validated[$dataset]) {
                throw new CatalogStorageException('Restored catalogue verification failed.');
            }
        } catch (Throwable $exception) {
            try {
                catalog_atomic_replace($targetPath, $currentContents);
                catalog_read_all();
            } catch (Throwable) {
                throw new CatalogStorageException(
                    'Restore verification failed and automatic rollback was unsuccessful.',
                    0,
                    $exception
                );
            }
            throw $exception;
        }

        return [
            'dataset' => $dataset,
            'backup' => $filename,
            'rollbackBackup' => $rollbackBackup,
            'recordCount' => count($restored[$dataset]),
        ];
    } finally {
        if (is_resource($backupLock)) catalog_release_backup_management_lock($backupLock);
        catalog_release_mutation_lock($lock);
    }
}

/**
 * Create a point-in-time snapshot of all four validated runtime datasets.
 *
 * @return array{name: string, counts: array<string, int>}
 */
function catalog_create_snapshot(): array
{
    $lock = catalog_acquire_mutation_lock();
    $backupLock = null;
    try {
        $backupLock = catalog_acquire_backup_management_lock(false);
        $catalog = catalog_read_all();
        return catalog_create_snapshot_locked($catalog);
    } finally {
        if (is_resource($backupLock)) catalog_release_backup_management_lock($backupLock);
        catalog_release_mutation_lock($lock);
    }
}

/**
 * Publish a complete snapshot while the caller holds the catalogue lock.
 *
 * @param array<string, list<array<string, mixed>>> $catalog
 * @return array{name: string, counts: array<string, int>, createdAt: string}
 */
function catalog_create_snapshot_locked(array $catalog): array
{
    catalog_validate_all($catalog);
    catalog_ensure_directory(catalog_snapshot_directory());
    $name = 'catalog-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $createdAt = gmdate(DATE_ATOM);
    $temporary = catalog_snapshot_directory() . '/.tmp-' . bin2hex(random_bytes(8));
    $target = catalog_snapshot_directory() . '/' . $name;
    catalog_ensure_directory($temporary);

    try {
        $counts = [];
        foreach (catalog_dataset_names() as $dataset) {
            $contents = @file_get_contents(catalog_dataset_path($dataset));
            if ($contents === false) {
                throw new CatalogStorageException('Unable to read a dataset for snapshot creation.');
            }
            catalog_write_complete_file($temporary . '/' . $dataset . '.json', $contents);
            $counts[$dataset] = count($catalog[$dataset]);
        }
        catalog_write_complete_file(
            $temporary . '/manifest.json',
            json_encode([
                'format' => 1,
                'createdAt' => $createdAt,
                'datasets' => $counts,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
        );
        if (!@rename($temporary, $target)) {
            throw new CatalogStorageException('Unable to publish the catalogue snapshot.');
        }
        @chmod($target, 0700);
    } finally {
        if (is_dir($temporary) && !is_link($temporary)) {
            foreach (scandir($temporary) ?: [] as $item) {
                if ($item !== '.' && $item !== '..') @unlink($temporary . '/' . $item);
            }
            @rmdir($temporary);
        }
    }

    return ['name' => $name, 'counts' => $counts, 'createdAt' => $createdAt];
}

function catalog_snapshot_path(string $snapshotId): string
{
    if (is_link(catalog_snapshot_directory())) {
        throw new CatalogStorageException('Catalogue snapshot storage must not be a symbolic link.');
    }
    if (basename($snapshotId) !== $snapshotId
        || preg_match('/^catalog-\d{8}-\d{6}-[a-f0-9]{8}$/D', $snapshotId) !== 1) {
        throw new CatalogStorageException('Invalid catalogue snapshot identifier.');
    }

    return catalog_snapshot_directory() . '/' . $snapshotId;
}

/** @return array{domain: string, id: string, type: string, createdAt: string, counts: array<string, int>, validation: string, downloadAvailable: bool} */
function catalog_read_snapshot_metadata(string $snapshotId, bool $validateContents = true): array
{
    $directory = catalog_snapshot_path($snapshotId);
    if (is_link($directory) || !is_dir($directory) || !is_readable($directory)) {
        throw new CatalogStorageException('The requested catalogue snapshot is unavailable.');
    }
    $manifestPath = $directory . '/manifest.json';
    if (is_link($manifestPath) || !is_file($manifestPath) || !is_readable($manifestPath)) {
        throw new CatalogStorageException('The catalogue snapshot manifest is unavailable.');
    }
    try {
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new CatalogStorageException('The catalogue snapshot manifest is invalid.', 0, $exception);
    }
    if (!is_array($manifest) || ($manifest['format'] ?? null) !== 1
        || !is_string($manifest['createdAt'] ?? null) || strtotime($manifest['createdAt']) === false
        || !is_array($manifest['datasets'] ?? null)
        || array_keys($manifest['datasets']) !== catalog_dataset_names()) {
        throw new CatalogStorageException('The catalogue snapshot manifest is invalid.');
    }
    $counts = [];
    foreach (catalog_dataset_names() as $dataset) {
        $count = $manifest['datasets'][$dataset] ?? null;
        if (!is_int($count) || $count < 0) {
            throw new CatalogStorageException('The catalogue snapshot manifest is invalid.');
        }
        $counts[$dataset] = $count;
    }
    if ($validateContents) {
        $snapshot = catalog_read_snapshot($snapshotId, false);
        foreach ($counts as $dataset => $count) {
            if (count($snapshot['catalog'][$dataset]) !== $count) {
                throw new CatalogStorageException('The catalogue snapshot record counts do not match its manifest.');
            }
        }
    }
    return [
        'domain' => 'catalog', 'id' => $snapshotId, 'type' => 'snapshot',
        'createdAt' => $manifest['createdAt'], 'counts' => $counts,
        'validation' => $validateContents ? 'passed' : 'not-checked',
        'downloadAvailable' => true,
    ];
}

/** @return array{catalog: array<string, list<array<string, mixed>>>, contents: array<string, string>} */
function catalog_read_snapshot(string $snapshotId, bool $readMetadata = true): array
{
    if ($readMetadata) catalog_read_snapshot_metadata($snapshotId, false);
    $directory = catalog_snapshot_path($snapshotId);
    $catalog = [];
    $contents = [];
    foreach (catalog_dataset_names() as $dataset) {
        $path = $directory . '/' . $dataset . '.json';
        if (is_link($path)) throw new CatalogStorageException('Symbolic links are not permitted in snapshots.');
        $catalog[$dataset] = catalog_read_json_file($dataset, $path);
        $raw = @file_get_contents($path);
        if ($raw === false) throw new CatalogStorageException('Unable to read a catalogue snapshot dataset.');
        $contents[$dataset] = $raw;
    }
    try {
        $catalog = catalog_validate_all($catalog);
    } catch (CatalogValidationException $exception) {
        throw new CatalogStorageException('The catalogue snapshot failed validation.', 0, $exception);
    }
    return ['catalog' => $catalog, 'contents' => $contents];
}

/** @return list<array{domain: string, id: string, type: string, createdAt: string, counts: array<string, int>, validation: string, downloadAvailable: bool}> */
function catalog_list_snapshots(): array
{
    if (!is_dir(catalog_snapshot_directory()) || is_link(catalog_snapshot_directory())) return [];
    $snapshots = [];
    foreach (scandir(catalog_snapshot_directory()) ?: [] as $item) {
        if (preg_match('/^catalog-\d{8}-\d{6}-[a-f0-9]{8}$/D', $item) !== 1) continue;
        try { $snapshots[] = catalog_read_snapshot_metadata($item); } catch (Throwable) { continue; }
    }
    usort($snapshots, static fn(array $left, array $right): int =>
        ($right['createdAt'] <=> $left['createdAt']) ?: ($right['id'] <=> $left['id']));
    return $snapshots;
}

/** @return array{domain: string, snapshotId: string, currentCounts: array<string, int>, snapshotCounts: array<string, int>, validation: string} */
function catalog_snapshot_restore_dry_run(string $snapshotId): array
{
    $lock = catalog_acquire_mutation_lock();
    $backupLock = null;
    try {
        $backupLock = catalog_acquire_backup_management_lock(false);
        $current = catalog_read_all();
        $snapshot = catalog_read_snapshot($snapshotId);
        return [
            'domain' => 'catalog', 'snapshotId' => $snapshotId,
            'currentCounts' => array_map('count', $current),
            'snapshotCounts' => array_map('count', $snapshot['catalog']),
            'validation' => 'passed',
        ];
    } finally {
        if (is_resource($backupLock)) catalog_release_backup_management_lock($backupLock);
        catalog_release_mutation_lock($lock);
    }
}

/** @return array{domain: string, snapshotId: string, counts: array<string, int>, validation: string, rollbackBackupCreated: bool} */
function catalog_restore_snapshot(string $snapshotId): array
{
    $lock = catalog_acquire_mutation_lock();
    $backupLock = null;
    try {
        $backupLock = catalog_acquire_backup_management_lock(false);
        $current = catalog_read_all();
        $currentContents = [];
        foreach (catalog_dataset_names() as $dataset) {
            $raw = @file_get_contents(catalog_dataset_path($dataset));
            if ($raw === false) throw new CatalogStorageException('Unable to prepare catalogue rollback data.');
            $currentContents[$dataset] = $raw;
        }
        $snapshot = catalog_read_snapshot($snapshotId);
        catalog_create_snapshot_locked($current);
        try {
            foreach (catalog_dataset_names() as $dataset) {
                catalog_atomic_replace(catalog_dataset_path($dataset), $snapshot['contents'][$dataset]);
            }
            $restored = catalog_read_all();
            if ($restored !== $snapshot['catalog']) throw new CatalogStorageException('Restored catalogue verification failed.');
        } catch (Throwable $exception) {
            try {
                foreach (catalog_dataset_names() as $dataset) {
                    catalog_atomic_replace(catalog_dataset_path($dataset), $currentContents[$dataset]);
                }
                catalog_read_all();
            } catch (Throwable) {
                throw new CatalogStorageException('Snapshot restore failed and automatic rollback was unsuccessful.', 0, $exception);
            }
            throw $exception;
        }
        return [
            'domain' => 'catalog', 'snapshotId' => $snapshotId,
            'counts' => array_map('count', $restored), 'validation' => 'passed',
            'rollbackBackupCreated' => true,
        ];
    } finally {
        if (is_resource($backupLock)) catalog_release_backup_management_lock($backupLock);
        catalog_release_mutation_lock($lock);
    }
}

/** @return array{domain: string, id: string, type: string} */
function catalog_delete_snapshot(string $snapshotId): array
{
    $directory = catalog_snapshot_path($snapshotId);
    if (is_link($directory)) throw new CatalogStorageException('Symbolic-link snapshots cannot be deleted.');
    if (!is_dir($directory)) throw new CatalogStorageException('The requested catalogue snapshot was not found.');
    catalog_read_snapshot_metadata($snapshotId, true);
    $expected = ['categories.json', 'manifest.json', 'products.json', 'subcategories.json', 'suppliers.json'];
    $actual = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    sort($actual);
    if ($actual !== $expected) throw new CatalogStorageException('The catalogue snapshot structure is not recognized.');
    foreach ($expected as $filename) {
        $path = $directory . '/' . $filename;
        if (is_link($path) || !is_file($path)) throw new CatalogStorageException('The catalogue snapshot structure is not recognized.');
    }
    if (count(catalog_list_snapshots()) <= 1) {
        throw new CatalogStorageException('At least one valid catalogue snapshot must be retained.');
    }
    $quarantine = catalog_snapshot_directory() . '/.deleting-' . bin2hex(random_bytes(8));
    if (!@rename($directory, $quarantine)) throw new CatalogStorageException('The catalogue snapshot could not be deleted.');
    try {
        foreach ($expected as $filename) {
            if (!@unlink($quarantine . '/' . $filename)) throw new CatalogStorageException('The catalogue snapshot could not be deleted.');
        }
        if (!@rmdir($quarantine)) throw new CatalogStorageException('The catalogue snapshot could not be deleted.');
    } catch (Throwable $exception) {
        if (is_dir($quarantine) && !file_exists($directory)) @rename($quarantine, $directory);
        throw $exception;
    }
    return ['domain' => 'catalog', 'id' => $snapshotId, 'type' => 'snapshot'];
}

/** @return array{domain: string, id: string, type: string, dataset: string} */
function catalog_delete_dataset_backup(string $dataset, string $filename): array
{
    $path = catalog_backup_path($dataset, $filename);
    if (is_link($path)) throw new CatalogStorageException('Symbolic-link backups cannot be deleted.');
    if (!is_file($path)) throw new CatalogStorageException('The requested catalogue backup was not found.');
    catalog_read_backup($dataset, $filename);
    if (!@unlink($path)) throw new CatalogStorageException('The catalogue backup could not be deleted.');
    return ['domain' => 'catalog', 'id' => $filename, 'type' => 'dataset-backup', 'dataset' => $dataset];
}

/**
 * Run a single-dataset mutation while holding the global catalogue lock.
 *
 * The callback receives the complete validated catalogue by reference. It may
 * change only the requested dataset and may return a response value.
 */
function catalog_mutate_dataset(string $dataset, callable $mutation): mixed
{
    catalog_dataset_path($dataset);
    $lock = catalog_acquire_mutation_lock();

    try {
        $currentCatalog = catalog_read_all();
        $proposedCatalog = $currentCatalog;
        $result = $mutation($proposedCatalog);

        foreach (catalog_dataset_names() as $name) {
            if ($name !== $dataset && $proposedCatalog[$name] !== $currentCatalog[$name]) {
                throw new CatalogStorageException('A mutation attempted to change multiple datasets.');
            }
        }

        try {
            $validatedCatalog = catalog_validate_all($proposedCatalog);
        } catch (CatalogValidationException $exception) {
            throw new CatalogStorageException(
                'The proposed catalogue update failed validation.',
                0,
                $exception
            );
        }

        $targetPath = catalog_dataset_path($dataset);
        $currentContents = @file_get_contents($targetPath);
        if ($currentContents === false) {
            throw new CatalogStorageException('Unable to read the current dataset for backup.');
        }

        catalog_create_backup($dataset, $currentContents);
        catalog_atomic_replace(
            $targetPath,
            catalog_encode_json($validatedCatalog[$dataset])
        );

        return $result;
    } finally {
        catalog_release_mutation_lock($lock);
    }
}

/**
 * Initialize all runtime datasets exactly once.
 *
 * @param array<string, list<array<string, mixed>>> $catalog
 */
function catalog_initialize(array $catalog): void
{
    $lock = catalog_acquire_mutation_lock();

    try {
        foreach (catalog_dataset_names() as $dataset) {
            if (is_file(catalog_dataset_path($dataset))) {
                throw new CatalogStorageException(
                    'Runtime catalogue initialization was refused because data already exists.'
                );
            }
        }

        try {
            $validatedCatalog = catalog_validate_all($catalog);
        } catch (CatalogValidationException $exception) {
            throw new CatalogStorageException(
                'Source catalogue validation failed: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $createdFiles = [];

        try {
            foreach (catalog_dataset_names() as $dataset) {
                $path = catalog_dataset_path($dataset);
                catalog_write_complete_file(
                    $path,
                    catalog_encode_json($validatedCatalog[$dataset])
                );
                $createdFiles[] = $path;
            }
        } catch (Throwable $exception) {
            foreach ($createdFiles as $createdFile) {
                @unlink($createdFile);
            }

            throw $exception;
        }
    } finally {
        catalog_release_mutation_lock($lock);
    }
}
