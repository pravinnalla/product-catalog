<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$project = dirname(__DIR__);
$temporaryRoot = sys_get_temp_dir() . '/catalog-backup-test-' . bin2hex(random_bytes(6));
$privateRoot = $temporaryRoot . '/private';
mkdir($privateRoot . '/catalog', 0700, true);
putenv('APP_PRIVATE_ROOT=' . $privateRoot);
require_once $project . '/api/includes/catalog-storage.php';

$results = [];

function backup_test(array &$results, string $label, callable $callback): void
{
    try {
        $results[$label] = $callback() === true;
    } catch (Throwable) {
        $results[$label] = false;
    }
}

function backup_test_throws(callable $callback): bool
{
    try {
        $callback();
        return false;
    } catch (Throwable) {
        return true;
    }
}

function backup_test_remove_tree(string $directory): void
{
    if (!is_dir($directory)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

try {
    foreach (catalog_dataset_names() as $dataset) {
        copy($project . '/private/catalog/' . $dataset . '.json', $privateRoot . '/catalog/' . $dataset . '.json');
    }

    $originalProducts = file_get_contents($privateRoot . '/catalog/products.json');
    if (!is_string($originalProducts)) throw new RuntimeException('Unable to prepare product fixture.');
    $validBackup = catalog_create_backup('products', $originalProducts);

    backup_test($results, 'catalogue backup domain is registered', static function (): bool {
        $domain = backup_domain('catalog');
        return backup_domain_names() === ['catalog']
            && $domain['storageRoot'] === catalog_directory()
            && $domain['backupRoot'] === catalog_backup_directory()
            && $domain['lockStrategy']['scope'] === 'catalog';
    });
    backup_test($results, 'unknown backup domain rejected', static fn(): bool =>
        backup_test_throws(static fn() => backup_domain('invoices'))
    );
    backup_test($results, 'traversal-style backup domain rejected', static fn(): bool =>
        backup_test_throws(static fn() => backup_domain('../catalog'))
    );
    backup_test($results, 'absolute-path backup domain rejected', static fn(): bool =>
        backup_test_throws(static fn() => backup_domain('/tmp/catalog'))
    );
    backup_test($results, 'registry cannot resolve arbitrary path', static fn(): bool =>
        backup_test_throws(static fn() => backup_domain($privateRoot . '/catalog'))
    );
    backup_test($results, 'future production domains are not created', static function () use ($privateRoot): bool {
        foreach (['invoices', 'quotations', 'customers', 'reports'] as $domain) {
            if (file_exists($privateRoot . '/business/' . $domain)
                || file_exists($privateRoot . '/backups/' . $domain)) {
                return false;
            }
        }
        return true;
    });

    backup_test($results, 'backup listing newest-first metadata', static function () use ($validBackup): bool {
        $listed = catalog_list_backups('products');
        return count($listed) === 1
            && $listed[0]['dataset'] === 'products'
            && $listed[0]['filename'] === $validBackup
            && preg_match('/^\d{8}-\d{6}$/', $listed[0]['timestamp']) === 1;
    });

    backup_test($results, 'valid restore dry-run', static function () use ($validBackup): bool {
        $before = hash_file('sha256', catalog_dataset_path('products'));
        $backupCount = count(catalog_list_backups('products'));
        $summary = backup_domain_restore_dry_run('catalog', 'products', $validBackup);
        return $summary['currentCount'] === $summary['backupCount']
            && hash_file('sha256', catalog_dataset_path('products')) === $before
            && count(catalog_list_backups('products')) === $backupCount;
    });

    backup_test($results, 'invalid dataset rejected', static fn(): bool =>
        backup_test_throws(static fn() => catalog_restore_dry_run('credentials', 'credentials-20260101-000000-00000000.json'))
    );
    backup_test($results, 'nonexistent backup rejected', static fn(): bool =>
        backup_test_throws(static fn() => catalog_restore_dry_run('products', 'products-20260101-000000-00000000.json'))
    );
    backup_test($results, 'path traversal rejected', static fn(): bool =>
        backup_test_throws(static fn() => catalog_restore_dry_run('products', '../products-20260101-000000-00000000.json'))
    );
    backup_test($results, 'symlink backup escape rejected', static function () use ($temporaryRoot, $originalProducts): bool {
        $outside = $temporaryRoot . '/outside-products.json';
        $link = catalog_backup_directory() . '/products-20260101-000000-00000003.json';
        file_put_contents($outside, $originalProducts);
        if (!symlink($outside, $link)) return false;
        return backup_test_throws(static fn() => catalog_restore_dry_run('products', basename($link)));
    });

    catalog_ensure_directory(catalog_backup_directory());
    $malformed = 'products-20260101-000001-00000001.json';
    file_put_contents(catalog_backup_directory() . '/' . $malformed, '{broken');
    backup_test($results, 'malformed JSON rejected', static fn(): bool =>
        backup_test_throws(static fn() => catalog_restore_dry_run('products', $malformed))
    );

    $invalidRelations = json_decode($originalProducts, true, 512, JSON_THROW_ON_ERROR);
    $invalidRelations[0]['supplierId'] = 'SUP00000000';
    $invalidBackup = 'products-20260101-000002-00000002.json';
    file_put_contents(
        catalog_backup_directory() . '/' . $invalidBackup,
        json_encode($invalidRelations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL
    );
    backup_test($results, 'referential-integrity failure rejected', static function () use ($invalidBackup, $originalProducts): bool {
        $before = hash('sha256', $originalProducts);
        return backup_test_throws(static fn() => catalog_restore_dry_run('products', $invalidBackup))
            && hash_file('sha256', catalog_dataset_path('products')) === $before;
    });

    backup_test($results, 'confirmation required for non-interactive restore', static function () use ($project, $privateRoot, $validBackup): bool {
        $command = [PHP_BINARY, $project . '/scripts/restore-catalog-backup.php', '--dataset=products', '--backup=' . $validBackup];
        $pipes = [];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $project, [...getenv(), 'APP_PRIVATE_ROOT' => $privateRoot]);
        if (!is_resource($process)) return false;
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        return $status !== 0 && str_contains((string) $stderr, 'Non-interactive restore refused')
            && str_contains((string) $stdout, 'Selected backup:');
    });

    backup_test($results, 'atomic restore and pre-restore rollback backup', static function () use ($validBackup, $originalProducts): bool {
        $beforeBackups = count(catalog_list_backups('products'));
        $result = catalog_restore_backup('products', $validBackup);
        return hash_file('sha256', catalog_dataset_path('products')) === hash('sha256', $originalProducts)
            && count(catalog_list_backups('products')) === $beforeBackups + 1
            && is_file(catalog_backup_path('products', $result['rollbackBackup']))
            && count(catalog_read_all()['products']) === $result['recordCount'];
    });

    backup_test($results, 'failed restore leaves live catalogue unchanged', static function () use ($invalidBackup): bool {
        $before = hash_file('sha256', catalog_dataset_path('products'));
        return backup_test_throws(static fn() => catalog_restore_backup('products', $invalidBackup))
            && hash_file('sha256', catalog_dataset_path('products')) === $before;
    });

    backup_test($results, 'per-dataset retention and unrelated-file safety', static function () use ($originalProducts): bool {
        file_put_contents(catalog_backup_directory() . '/operator-notes.txt', 'keep');
        for ($index = 0; $index < 25; $index++) catalog_create_backup('products', $originalProducts);
        return count(catalog_list_backups('products')) === CATALOG_BACKUP_RETENTION_PER_DATASET
            && is_file(catalog_backup_directory() . '/operator-notes.txt');
    });

    backup_test($results, 'complete snapshot creation and validation', static function (): bool {
        $snapshot = backup_domain_create_snapshot('catalog');
        $directory = catalog_snapshot_directory() . '/' . $snapshot['name'];
        $catalog = [];
        foreach (catalog_dataset_names() as $dataset) {
            $catalog[$dataset] = catalog_read_json_file($dataset, $directory . '/' . $dataset . '.json');
        }
        return is_file($directory . '/manifest.json')
            && catalog_validate_all($catalog) === $catalog
            && count($snapshot['counts']) === 4;
    });

    foreach ($results as $label => $passed) fwrite(STDOUT, ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL);
    exit(in_array(false, $results, true) ? 1 : 0);
} finally {
    backup_test_remove_tree($temporaryRoot);
}
