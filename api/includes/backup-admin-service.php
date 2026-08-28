<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog-storage.php';
require_once __DIR__ . '/business-backup.php';

final class BackupAdminException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}

/** @return list<array{id: string, label: string, capabilities: array<string, bool>}> */
function backup_admin_domains(): array
{
    return array_map(static function (string $name): array {
        $definition = backup_domain($name);
        return [
            'id' => $name,
            'label' => $name === 'catalog' ? 'Catalogue' : 'Business',
            'description' => $name === 'business'
                ? 'Customers, Payment Tracking, Refilling Items, and Certificates'
                : 'Catalogue categories, subcategories, suppliers, and products',
            'capabilities' => [
                'createSnapshot' => is_callable($definition['snapshotStrategy'] ?? null),
                'restore' => is_callable($definition['adminSnapshotStrategy']['restore'] ?? null),
                'download' => is_callable($definition['adminSnapshotStrategy']['metadata'] ?? null),
                'delete' => is_callable($definition['adminSnapshotStrategy']['delete'] ?? null),
            ],
        ];
    }, backup_domain_names());
}

/** @return array<string, mixed> */
function backup_admin_definition(string $domain): array
{
    try { return backup_domain($domain); }
    catch (BackupDomainException $exception) {
        throw new BackupAdminException('Invalid or unavailable backup domain.', 400);
    }
}

/** @return list<array<string, mixed>> */
function backup_admin_list(string $domain): array
{
    $definition = backup_admin_definition($domain);
    $list = $definition['adminSnapshotStrategy']['list'] ?? null;
    if (!is_callable($list)) throw new BackupAdminException('Backup listing is unavailable.', 501);
    $lock = $domain === 'catalog'
        ? catalog_acquire_backup_management_lock(false)
        : business_acquire_backup_management_lock(false);
    try {
        $items = $list();

        if ($domain === 'catalog') {
            foreach (catalog_list_backups() as $backup) {
                try {
                    $read = catalog_read_backup($backup['dataset'], $backup['filename']);
                    $items[] = [
                        'domain' => 'catalog', 'id' => $backup['filename'], 'type' => 'dataset-backup',
                        'dataset' => $backup['dataset'],
                        'createdAt' => DateTimeImmutable::createFromFormat(
                            '!Ymd-His', $backup['timestamp'], new DateTimeZone('UTC')
                        )?->format(DATE_ATOM) ?? $backup['timestamp'],
                        'counts' => [$backup['dataset'] => count($read['records'])],
                        'validation' => 'not-checked', 'downloadAvailable' => false,
                    ];
                } catch (Throwable) { continue; }
            }
            usort($items, static fn(array $left, array $right): int =>
                ($right['createdAt'] <=> $left['createdAt']) ?: ($right['id'] <=> $left['id']));
        }
        return $items;
    } finally {
        if (is_resource($lock)) {
            $domain === 'catalog' ? catalog_release_backup_management_lock($lock) : business_release_backup_management_lock($lock);
        }
    }
}

/** @return array<string, mixed> */
function backup_admin_create_snapshot(string $domain): array
{
    backup_admin_definition($domain);
    $created = backup_domain_create_snapshot($domain);
    $metadata = backup_admin_definition($domain)['adminSnapshotStrategy']['metadata'] ?? null;
    if (!is_callable($metadata)) throw new BackupAdminException('Snapshot metadata is unavailable.', 501);
    return $metadata($created['name']);
}

/** @return array<string, mixed> */
function backup_admin_dry_run(string $domain, string $type, string $id, ?string $dataset = null): array
{
    $definition = backup_admin_definition($domain);
    if ($type === 'snapshot') {
        $callback = $definition['adminSnapshotStrategy']['dryRun'] ?? null;
        if (!is_callable($callback)) throw new BackupAdminException('Snapshot restore is unavailable.', 501);
        return $callback($id);
    }
    if ($domain === 'catalog' && $type === 'dataset-backup' && is_string($dataset)) {
        $result = backup_domain_restore_dry_run($domain, $dataset, $id);
        return [
            'domain' => $domain, 'snapshotId' => $id, 'dataset' => $dataset,
            'currentCounts' => [$dataset => $result['currentCount']],
            'snapshotCounts' => [$dataset => $result['backupCount']], 'validation' => 'passed',
        ];
    }
    throw new BackupAdminException('Invalid backup selection.', 400);
}

/** @return array<string, mixed> */
function backup_admin_restore(string $domain, string $type, string $id, string $confirmation, ?string $dataset = null): array
{
    if ($confirmation !== 'RESTORE') throw new BackupAdminException('Type RESTORE exactly to confirm.', 400);
    $definition = backup_admin_definition($domain);
    backup_admin_dry_run($domain, $type, $id, $dataset);
    if ($type === 'snapshot') {
        $callback = $definition['adminSnapshotStrategy']['restore'] ?? null;
        if (!is_callable($callback)) throw new BackupAdminException('Snapshot restore is unavailable.', 501);
        return $callback($id);
    }
    if ($domain === 'catalog' && $type === 'dataset-backup' && is_string($dataset)) {
        $restored = backup_domain_restore($domain, $dataset, $id);
        return [
            'domain' => $domain, 'snapshotId' => $id, 'dataset' => $dataset,
            'counts' => [$dataset => $restored['recordCount']], 'validation' => 'passed',
            'rollbackBackupCreated' => true,
        ];
    }
    throw new BackupAdminException('Invalid backup selection.', 400);
}

/** @return array{path: string, filename: string, size: int} */
function backup_admin_create_download(string $domain, string $snapshotId): array
{
    $definition = backup_admin_definition($domain);
    $metadata = $definition['adminSnapshotStrategy']['metadata'] ?? null;
    if (!is_callable($metadata)) throw new BackupAdminException('Snapshot download is unavailable.', 501);
    $lock = $domain === 'catalog'
        ? catalog_acquire_backup_management_lock(false)
        : business_acquire_backup_management_lock(false);

    try {
        $metadata($snapshotId, true);
        $directory = $domain === 'catalog' ? catalog_snapshot_path($snapshotId) : business_snapshot_path($snapshotId);
        $temporary = tempnam(sys_get_temp_dir(), $domain . '-snapshot-');
        if ($temporary === false) throw new RuntimeException('Unable to prepare snapshot download.');
        $archive = new ZipArchive();
        if ($archive->open($temporary, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to prepare snapshot download.');
        }
        $datasets = $domain === 'catalog' ? catalog_dataset_names() : business_dataset_names();
        foreach ([...$datasets, 'manifest'] as $name) {
            $path = $directory . '/' . $name . '.json';
            if (is_link($path) || !is_file($path) || !$archive->addFile($path, $name . '.json')) {
                $archive->close();
                throw new BackupAdminException('The selected snapshot is incomplete.', 409);
            }
        }
        if (!$archive->close()) throw new RuntimeException('Unable to prepare snapshot download.');
        $size = filesize($temporary);
        if (!is_int($size) || $size < 1) throw new RuntimeException('Unable to prepare snapshot download.');
        return ['path' => $temporary, 'filename' => $snapshotId . '.zip', 'size' => $size];
    } catch (Throwable $exception) {
        if (isset($temporary)) @unlink($temporary);
        throw $exception;
    } finally {
        $domain === 'catalog' ? catalog_release_backup_management_lock($lock) : business_release_backup_management_lock($lock);
    }
}

/** @return array<string, string> */
function backup_admin_delete(string $domain, string $type, string $id, string $confirmation, ?string $dataset = null): array
{
    if ($confirmation !== 'DELETE') throw new BackupAdminException('Type DELETE exactly to confirm.', 400);
    $definition = backup_admin_definition($domain);
    try {
        $lock = $domain === 'catalog'
            ? catalog_acquire_backup_management_lock(true)
            : business_acquire_backup_management_lock(true);
    } catch (CatalogStorageException|BusinessStorageException) {
        throw new BackupAdminException('Unable to complete backup deletion.', 500);
    }
    try {
        if ($type === 'snapshot') {
            $prefix = $domain === 'catalog' ? 'catalog' : 'business';
            if (preg_match('/^' . $prefix . '-\d{8}-\d{6}-[a-f0-9]{8}$/D', $id) !== 1) {
                throw new BackupAdminException('Invalid backup identifier.', 422);
            }
            $path = $domain === 'catalog' ? catalog_snapshot_path($id) : business_snapshot_path($id);
            if (is_link($path)) throw new BackupAdminException('Symbolic-link snapshots cannot be deleted.', 422);
            if (!is_dir($path)) throw new BackupAdminException('Backup item not found.', 404);
            $callback = $definition['adminSnapshotStrategy']['delete'] ?? null;
            if (!is_callable($callback)) throw new BackupAdminException('Backup deletion is unavailable.', 501);
            try { return $callback($id); }
            catch (CatalogStorageException|BusinessStorageException $exception) {
                if ($exception->getMessage() === 'At least one valid catalogue snapshot must be retained.') {
                    throw new BackupAdminException($exception->getMessage(), 409);
                }
                if (str_contains($exception->getMessage(), 'structure')
                    || str_contains($exception->getMessage(), 'manifest')
                    || str_contains($exception->getMessage(), 'validation')
                    || str_contains($exception->getMessage(), 'record counts')) {
                    throw new BackupAdminException('The selected snapshot is not safe to delete.', 422);
                }
                throw new BackupAdminException('The snapshot could not be deleted.', 500);
            }
        }
        if ($type === 'dataset-backup') {
            if (!is_string($dataset)) throw new BackupAdminException('Invalid backup selection.', 422);
            try {
                catalog_dataset_path($dataset);
                $path = catalog_backup_path($dataset, $id);
            } catch (CatalogStorageException) {
                throw new BackupAdminException('Invalid backup identifier.', 422);
            }
            if (is_link($path)) throw new BackupAdminException('Symbolic-link backups cannot be deleted.', 422);
            if (!is_file($path)) throw new BackupAdminException('Backup item not found.', 404);
            try { return catalog_delete_dataset_backup($dataset, $id); }
            catch (CatalogStorageException) { throw new BackupAdminException('The catalogue backup could not be deleted.', 500); }
        }
        throw new BackupAdminException('Invalid backup selection.', 422);
    } finally {
        $domain === 'catalog' ? catalog_release_backup_management_lock($lock) : business_release_backup_management_lock($lock);
    }
}
