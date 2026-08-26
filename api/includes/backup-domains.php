<?php

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

final class BackupDomainException extends RuntimeException
{
}

/**
 * Registered backup domains. Paths are derived by server code only; callers
 * can select a domain name but can never supply a filesystem root.
 *
 * A future domain must provide equivalent authoritative validation, locking,
 * snapshot, dry-run restore, and confirmed restore strategies when its module
 * is implemented.
 *
 * @return array<string, array<string, mixed>>
 */
function backup_domain_registry(): array
{
    $privateRoot = app_private_root();

    return [
        'catalog' => [
            'storageRoot' => $privateRoot . '/catalog',
            'backupRoot' => $privateRoot . '/backups/catalog',
            'snapshotRoot' => $privateRoot . '/backups/snapshots',
            'validationStrategy' => 'catalog_validate_all',
            'lockStrategy' => [
                'acquire' => 'catalog_acquire_mutation_lock',
                'release' => 'catalog_release_mutation_lock',
                'scope' => 'catalog',
            ],
            'snapshotStrategy' => 'catalog_create_snapshot',
            'adminSnapshotStrategy' => [
                'list' => 'catalog_list_snapshots',
                'metadata' => 'catalog_read_snapshot_metadata',
                'dryRun' => 'catalog_snapshot_restore_dry_run',
                'restore' => 'catalog_restore_snapshot',
                'delete' => 'catalog_delete_snapshot',
            ],
            'restoreStrategy' => [
                'dryRun' => 'catalog_restore_dry_run',
                'restore' => 'catalog_restore_backup',
            ],
        ],
    ];
}

/** @return list<string> */
function backup_domain_names(): array
{
    return array_keys(backup_domain_registry());
}

/** @return array<string, mixed> */
function backup_domain(string $name): array
{
    if (preg_match('/^[a-z][a-z0-9-]*$/D', $name) !== 1) {
        throw new BackupDomainException('Invalid backup domain name.');
    }

    $registry = backup_domain_registry();
    if (!array_key_exists($name, $registry)) {
        throw new BackupDomainException('Unregistered backup domain.');
    }

    return $registry[$name];
}

function backup_domain_strategy(string $domain, string $strategy): callable
{
    $definition = backup_domain($domain);
    $callback = $definition[$strategy] ?? null;
    if (!is_callable($callback)) {
        throw new BackupDomainException('The backup domain strategy is unavailable.');
    }

    return $callback;
}

/** @return array{name: string, counts: array<string, int>} */
function backup_domain_create_snapshot(string $domain): array
{
    return backup_domain_strategy($domain, 'snapshotStrategy')();
}

/** @return array<string, mixed> */
function backup_domain_restore_dry_run(string $domain, string ...$arguments): array
{
    $definition = backup_domain($domain);
    $callback = $definition['restoreStrategy']['dryRun'] ?? null;
    if (!is_callable($callback)) {
        throw new BackupDomainException('The backup domain dry-run strategy is unavailable.');
    }

    return $callback(...$arguments);
}

/** @return array<string, mixed> */
function backup_domain_restore(string $domain, string ...$arguments): array
{
    $definition = backup_domain($domain);
    $callback = $definition['restoreStrategy']['restore'] ?? null;
    if (!is_callable($callback)) {
        throw new BackupDomainException('The backup domain restore strategy is unavailable.');
    }

    return $callback(...$arguments);
}
