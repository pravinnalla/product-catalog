<?php

declare(strict_types=1);

require_once __DIR__ . '/business-storage.php';

const BUSINESS_BACKUP_FORMAT = 1;

function business_backup_directory(): string
{
    return app_private_root() . '/backups/business';
}

/** @return resource */
function business_acquire_backup_management_lock(bool $exclusive)
{
    business_ensure_directory(business_lock_directory());
    $path = business_lock_directory() . '/business-backups.lock';
    if (is_link($path)) throw new BusinessStorageException('The Business backup-management lock is unsafe.');
    $handle = @fopen($path, 'c');
    if ($handle === false || !flock($handle, $exclusive ? LOCK_EX : LOCK_SH)) {
        if (is_resource($handle)) fclose($handle);
        throw new BusinessStorageException('Unable to lock Business backup storage.');
    }
    @chmod($path, 0600);
    return $handle;
}

/** @param resource $handle */
function business_release_backup_management_lock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

function business_backup_id(): string
{
    return 'business-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
}

function business_snapshot_path(string $snapshotId): string
{
    if (is_link(business_backup_directory())
        || basename($snapshotId) !== $snapshotId
        || preg_match('/^business-\d{8}-\d{6}-[a-f0-9]{8}$/D', $snapshotId) !== 1) {
        throw new BusinessStorageException('Invalid Business snapshot identifier.');
    }
    return business_backup_directory() . '/' . $snapshotId;
}

function business_backup_write_file(string $path, string $contents): void
{
    $handle = @fopen($path, 'xb');
    if ($handle === false) throw new BusinessStorageException('Unable to create a Business snapshot file.');
    $complete = false;
    try {
        $length = strlen($contents);
        for ($written = 0; $written < $length;) {
            $count = fwrite($handle, substr($contents, $written));
            if ($count === false || $count === 0) throw new BusinessStorageException('Unable to write a Business snapshot file.');
            $written += $count;
        }
        if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
            throw new BusinessStorageException('Unable to synchronize a Business snapshot file.');
        }
        $complete = true;
    } finally {
        fclose($handle);
        if (!$complete) @unlink($path);
    }
    @chmod($path, 0600);
}

/** @param array<string, list<array<string, mixed>>> $business */
function business_backup_counts(array $business): array
{
    $paymentCount = 0;
    foreach ($business['receivables'] as $record) $paymentCount += count($record['payments']);
    $certificateItemCount = 0;
    foreach ($business['certificates'] as $record) $certificateItemCount += count($record['items']);
    return [
        'customers' => count($business['customers']),
        'receivables' => count($business['receivables']),
        'payments' => $paymentCount,
        'refillingItems' => count($business['refilling-items']),
        'certificates' => count($business['certificates']),
        'certificateItems' => $certificateItemCount,
    ];
}

/** @param array<string, list<array<string, mixed>>> $business */
function business_create_snapshot_locked(array $business): array
{
    business_validate_all($business);
    business_ensure_directory(business_backup_directory());
    $id = business_backup_id();
    $target = business_snapshot_path($id);
    $temporary = business_backup_directory() . '/.creating-' . bin2hex(random_bytes(8));
    if (!@mkdir($temporary, 0700)) throw new BusinessStorageException('Unable to prepare a Business snapshot.');
    $createdAt = gmdate(DATE_ATOM);
    $counts = business_backup_counts($business);
    try {
        foreach (business_dataset_names() as $dataset) {
            business_backup_write_file($temporary . '/' . $dataset . '.json', business_encode_json($business[$dataset]));
        }
        business_backup_write_file($temporary . '/manifest.json', json_encode([
            'domain' => 'business',
            'format' => BUSINESS_BACKUP_FORMAT,
            'createdAt' => $createdAt,
            'datasets' => business_dataset_names(),
            'counts' => $counts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
        if (!@rename($temporary, $target)) throw new BusinessStorageException('Unable to publish the Business snapshot.');
        @chmod($target, 0700);
    } finally {
        if (is_dir($temporary) && !is_link($temporary)) {
            foreach (scandir($temporary) ?: [] as $file) if ($file !== '.' && $file !== '..') @unlink($temporary . '/' . $file);
            @rmdir($temporary);
        }
    }
    return ['name' => $id, 'counts' => $counts, 'createdAt' => $createdAt];
}

/** @return array{name: string, counts: array<string, int>, createdAt: string} */
function business_create_snapshot(): array
{
    $lock = business_acquire_lock();
    $backupLock = null;
    try {
        $backupLock = business_acquire_backup_management_lock(true);
        return business_create_snapshot_locked(business_read_all_locked());
    } finally {
        if (is_resource($backupLock)) business_release_backup_management_lock($backupLock);
        business_release_lock($lock);
    }
}

/** @return array{business: array<string, list<array<string, mixed>>>, contents: array<string, string>} */
function business_read_snapshot(string $snapshotId, bool $readMetadata = true): array
{
    if ($readMetadata) business_read_snapshot_metadata($snapshotId, false);
    $directory = business_snapshot_path($snapshotId);
    $business = [];
    $contents = [];
    foreach (business_dataset_names() as $dataset) {
        $path = $directory . '/' . $dataset . '.json';
        $business[$dataset] = business_read_dataset_file($dataset, $path);
        $raw = @file_get_contents($path);
        if ($raw === false) throw new BusinessStorageException('Unable to read a Business snapshot dataset.');
        $contents[$dataset] = $raw;
    }
    return ['business' => business_validate_all($business), 'contents' => $contents];
}

/** @return array<string, mixed> */
function business_read_snapshot_metadata(string $snapshotId, bool $validateContents = true): array
{
    $directory = business_snapshot_path($snapshotId);
    $path = $directory . '/manifest.json';
    if (is_link($directory) || !is_dir($directory) || is_link($path) || !is_file($path) || !is_readable($path)) {
        throw new BusinessStorageException('The requested Business snapshot is unavailable.');
    }
    try { $manifest = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $exception) { throw new BusinessStorageException('The Business snapshot manifest is invalid.', 0, $exception); }
    $expected = ['domain', 'format', 'createdAt', 'datasets', 'counts'];
    if (!is_array($manifest) || array_keys($manifest) !== $expected || $manifest['domain'] !== 'business'
        || $manifest['format'] !== BUSINESS_BACKUP_FORMAT || !is_string($manifest['createdAt']) || strtotime($manifest['createdAt']) === false
        || $manifest['datasets'] !== business_dataset_names() || !is_array($manifest['counts'])) {
        throw new BusinessStorageException('The Business snapshot manifest is invalid.');
    }
    $countKeys = ['customers', 'receivables', 'payments', 'refillingItems', 'certificates', 'certificateItems'];
    if (array_keys($manifest['counts']) !== $countKeys) throw new BusinessStorageException('The Business snapshot manifest is invalid.');
    foreach ($manifest['counts'] as $count) if (!is_int($count) || $count < 0) throw new BusinessStorageException('The Business snapshot manifest is invalid.');
    if ($validateContents && business_backup_counts(business_read_snapshot($snapshotId, false)['business']) !== $manifest['counts']) {
        throw new BusinessStorageException('The Business snapshot counts do not match its manifest.');
    }
    return [
        'domain' => 'business', 'id' => $snapshotId, 'type' => 'snapshot',
        'createdAt' => $manifest['createdAt'], 'counts' => $manifest['counts'],
        'validation' => $validateContents ? 'passed' : 'not-checked', 'downloadAvailable' => true,
    ];
}

/** @return list<array<string, mixed>> */
function business_list_snapshots(): array
{
    if (!is_dir(business_backup_directory()) || is_link(business_backup_directory())) return [];
    $snapshots = [];
    foreach (scandir(business_backup_directory()) ?: [] as $item) {
        if (preg_match('/^business-\d{8}-\d{6}-[a-f0-9]{8}$/D', $item) !== 1) continue;
        try { $snapshots[] = business_read_snapshot_metadata($item); } catch (Throwable) { continue; }
    }
    usort($snapshots, static fn(array $left, array $right): int =>
        ($right['createdAt'] <=> $left['createdAt']) ?: ($right['id'] <=> $left['id']));
    return $snapshots;
}

/** @return array<string, mixed> */
function business_snapshot_restore_dry_run(string $snapshotId): array
{
    $lock = business_acquire_lock();
    $backupLock = null;
    try {
        $backupLock = business_acquire_backup_management_lock(false);
        $current = business_read_all_locked();
        $snapshot = business_read_snapshot($snapshotId);
        return [
            'domain' => 'business', 'snapshotId' => $snapshotId,
            'currentCounts' => business_backup_counts($current),
            'snapshotCounts' => business_backup_counts($snapshot['business']), 'validation' => 'passed',
        ];
    } finally {
        if (is_resource($backupLock)) business_release_backup_management_lock($backupLock);
        business_release_lock($lock);
    }
}

/** @return array<string, mixed> */
function business_restore_snapshot(string $snapshotId): array
{
    $lock = business_acquire_lock();
    $backupLock = null;
    try {
        $backupLock = business_acquire_backup_management_lock(false);
        $current = business_read_all_locked();
        $currentContents = [];
        foreach (business_dataset_names() as $dataset) {
            $raw = @file_get_contents(business_dataset_path($dataset));
            if ($raw === false) throw new BusinessStorageException('Unable to prepare Business rollback data.');
            $currentContents[$dataset] = $raw;
        }
        $snapshot = business_read_snapshot($snapshotId);
        business_create_snapshot_locked($current);
        try {
            foreach (business_dataset_names() as $dataset) {
                business_atomic_write(business_dataset_path($dataset), $snapshot['contents'][$dataset]);
            }
            $restored = business_read_all_locked();
            if ($restored !== $snapshot['business']) throw new BusinessStorageException('Restored Business verification failed.');
        } catch (Throwable $exception) {
            try {
                foreach (business_dataset_names() as $dataset) business_atomic_write(business_dataset_path($dataset), $currentContents[$dataset]);
                business_read_all_locked();
            } catch (Throwable) {
                throw new BusinessStorageException('Business restore failed and automatic rollback was unsuccessful.', 0, $exception);
            }
            throw $exception;
        }
        return [
            'domain' => 'business', 'snapshotId' => $snapshotId,
            'counts' => business_backup_counts($restored), 'validation' => 'passed', 'rollbackBackupCreated' => true,
        ];
    } finally {
        if (is_resource($backupLock)) business_release_backup_management_lock($backupLock);
        business_release_lock($lock);
    }
}

/** @return array{domain: string, id: string, type: string} */
function business_delete_snapshot(string $snapshotId): array
{
    $directory = business_snapshot_path($snapshotId);
    if (is_link($directory) || !is_dir($directory)) throw new BusinessStorageException('The requested Business snapshot was not found.');
    business_read_snapshot_metadata($snapshotId, true);
    $expected = ['certificates.json', 'customers.json', 'manifest.json', 'receivables.json', 'refilling-items.json'];
    $actual = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    sort($actual);
    if ($actual !== $expected) throw new BusinessStorageException('The Business snapshot structure is not recognized.');
    $quarantine = business_backup_directory() . '/.deleting-' . bin2hex(random_bytes(8));
    if (!@rename($directory, $quarantine)) throw new BusinessStorageException('The Business snapshot could not be deleted.');
    try {
        foreach ($expected as $filename) if (!@unlink($quarantine . '/' . $filename)) throw new BusinessStorageException('The Business snapshot could not be deleted.');
        if (!@rmdir($quarantine)) throw new BusinessStorageException('The Business snapshot could not be deleted.');
    } catch (Throwable $exception) {
        if (is_dir($quarantine) && !file_exists($directory)) @rename($quarantine, $directory);
        throw $exception;
    }
    return ['domain' => 'business', 'id' => $snapshotId, 'type' => 'snapshot'];
}
