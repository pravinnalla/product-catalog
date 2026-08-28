<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$project = dirname(__DIR__);
$temporaryRoot = sys_get_temp_dir() . '/business-backup-test-' . bin2hex(random_bytes(6));
$privateRoot = $temporaryRoot . '/private';
$results = [];
mkdir($privateRoot . '/catalog', 0700, true);
putenv('APP_PRIVATE_ROOT=' . $privateRoot);
require_once $project . '/api/includes/catalog-storage.php';
require_once $project . '/api/includes/business-backup.php';

function business_backup_test(array &$results, string $label, callable $callback): void
{
    try { $results[$label] = $callback() === true; }
    catch (Throwable) { $results[$label] = false; }
    fwrite(STDOUT, ($results[$label] ? 'PASS ' : 'FAIL ') . $label . PHP_EOL);
}

function business_backup_throws(callable $callback): bool
{
    try { $callback(); return false; } catch (Throwable) { return true; }
}

function business_backup_remove_tree(string $directory): void
{
    if (!is_dir($directory)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($directory);
}

/** @param array<string, list<array<string, mixed>>> $state */
function business_backup_write_state(array $state): void
{
    business_ensure_directory(business_directory());
    foreach (business_dataset_names() as $dataset) business_atomic_write(business_dataset_path($dataset), business_encode_json($state[$dataset]));
}

function business_backup_hashes(): array
{
    $hashes = [];
    foreach (business_dataset_names() as $dataset) $hashes[$dataset] = hash_file('sha256', business_dataset_path($dataset));
    return $hashes;
}

$stateA = [
    'customers' => [
        ['id' => 'CUS0001', 'name' => 'Active Customer', 'address' => 'Pune', 'gstin' => '', 'contactPerson' => '', 'phone' => '', 'email' => '', 'isActive' => true],
        ['id' => 'CUS0002', 'name' => 'Historical Customer', 'address' => 'Mumbai', 'gstin' => '', 'contactPerson' => '', 'phone' => '', 'email' => '', 'isActive' => false],
    ],
    'receivables' => [[
        'id' => 'REC0001', 'customerId' => 'CUS0002', 'invoiceNumber' => 'INV-1', 'invoiceDate' => '2026-08-01',
        'invoiceAmount' => 1000, 'dueDate' => '2026-08-20', 'businessType' => 'REFILLING',
        'payments' => [['id' => 'PAY0001', 'paymentDate' => '2026-08-10', 'amount' => 400, 'paymentMode' => 'Bank', 'reference' => 'UTR1', 'remarks' => '']],
        'remarks' => '',
    ]],
    'refilling-items' => [
        ['id' => 'RFI0001', 'name' => 'ABC Fire Extinguisher', 'isActive' => true],
        ['id' => 'RFI0002', 'name' => 'Historical Item', 'isActive' => false],
    ],
    'certificates' => [[
        'id' => 'CERT0001', 'certificateNumber' => 'CERT-1', 'customerId' => 'CUS0002', 'invoiceNumber' => 'UNRELATED-INVOICE', 'certificateDate' => '2026-08-01',
        'items' => [
            ['id' => 'CIT0001', 'refillingItemId' => 'RFI0001', 'capacity' => '6 KG', 'quantity' => 2, 'serialNumbers' => ['S1'], 'refillingDate' => '2026-08-01', 'nextRefillingDate' => '2027-08-01', 'remark' => ''],
            ['id' => 'CIT0002', 'refillingItemId' => 'RFI0002', 'capacity' => '9 KG', 'quantity' => 1, 'serialNumbers' => [], 'refillingDate' => '2026-08-01', 'nextRefillingDate' => '2027-08-01', 'remark' => 'Historical'],
        ],
        'remarks' => '',
    ]],
];
$stateB = [
    'customers' => [['id' => 'CUS0009', 'name' => 'State B', 'address' => 'Delhi', 'gstin' => '', 'contactPerson' => '', 'phone' => '', 'email' => '', 'isActive' => true]],
    'receivables' => [], 'refilling-items' => [], 'certificates' => [],
];

try {
    foreach (['categories', 'subcategories', 'suppliers', 'products'] as $dataset) {
        copy($project . '/private/catalog/' . $dataset . '.json', $privateRoot . '/catalog/' . $dataset . '.json');
    }
    business_backup_write_state($stateA);
    $catalogHashes = [];
    foreach (catalog_dataset_names() as $dataset) $catalogHashes[$dataset] = hash_file('sha256', catalog_dataset_path($dataset));

    business_backup_test($results, 'Business domain is registered beside Catalogue', static fn(): bool =>
        backup_domain_names() === ['catalog', 'business'] && backup_domain('business')['snapshotStrategy'] === 'business_create_snapshot'
    );
    $created = backup_domain_create_snapshot('business');
    $snapshotId = $created['name'];
    business_backup_test($results, 'coordinated snapshot contains all four datasets and V1 manifest', static function () use ($snapshotId, $stateA): bool {
        $path = business_snapshot_path($snapshotId);
        $manifest = json_decode((string) file_get_contents($path . '/manifest.json'), true, 32, JSON_THROW_ON_ERROR);
        return ($manifest['domain'] ?? null) === 'business' && ($manifest['format'] ?? null) === 1
            && ($manifest['datasets'] ?? null) === business_dataset_names()
            && business_read_snapshot($snapshotId)['business'] === $stateA;
    });
    business_backup_test($results, 'embedded data, inactive masters, and informational counts are preserved', static function () use ($snapshotId): bool {
        $snapshot = business_read_snapshot($snapshotId)['business'];
        $counts = business_read_snapshot_metadata($snapshotId)['counts'];
        return $snapshot['receivables'][0]['payments'][0]['id'] === 'PAY0001'
            && $snapshot['customers'][1]['isActive'] === false && $snapshot['refilling-items'][1]['isActive'] === false
            && $snapshot['certificates'][0]['items'][1]['id'] === 'CIT0002'
            && $counts === ['customers' => 2, 'receivables' => 1, 'payments' => 1, 'refillingItems' => 2, 'certificates' => 1, 'certificateItems' => 2]
            && !array_key_exists('balance', $snapshot['receivables'][0]) && !array_key_exists('nextRefillStatus', $snapshot['certificates'][0]);
    });

    business_backup_write_state($stateB);
    $beforeDryRun = business_backup_hashes();
    $dryRun = business_snapshot_restore_dry_run($snapshotId);
    business_backup_test($results, 'dry run validates and never mutates live Business data', static fn(): bool =>
        $dryRun['validation'] === 'passed' && $dryRun['snapshotCounts']['certificateItems'] === 2 && business_backup_hashes() === $beforeDryRun
    );
    $restored = business_restore_snapshot($snapshotId);
    business_backup_test($results, 'restore replaces all four datasets together and creates rollback snapshot', static fn(): bool =>
        business_read_all() === $stateA && $restored['rollbackBackupCreated'] === true && count(business_list_snapshots()) === 2
    );
    business_backup_test($results, 'Business restore leaves Catalogue unchanged', static function () use ($catalogHashes): bool {
        foreach ($catalogHashes as $dataset => $hash) if (hash_file('sha256', catalog_dataset_path($dataset)) !== $hash) return false;
        return true;
    });

    $businessHashes = business_backup_hashes();
    $catalogSnapshot = catalog_create_snapshot();
    catalog_restore_snapshot($catalogSnapshot['name']);
    business_backup_test($results, 'Catalogue restore leaves Business unchanged', static fn(): bool => business_backup_hashes() === $businessHashes);

    $invalidSnapshot = business_create_snapshot()['name'];
    $invalidPath = business_snapshot_path($invalidSnapshot) . '/receivables.json';
    $invalid = $stateA['receivables'];
    $invalid[0]['customerId'] = 'CUS9999';
    file_put_contents($invalidPath, business_encode_json($invalid));
    $beforeInvalid = business_backup_hashes();
    business_backup_test($results, 'invalid relationship fails dry run and confirmed restore without live mutation', static fn(): bool =>
        business_backup_throws(static fn() => business_snapshot_restore_dry_run($invalidSnapshot))
            && business_backup_throws(static fn() => business_restore_snapshot($invalidSnapshot))
            && business_backup_hashes() === $beforeInvalid
    );

    $corruptPath = business_dataset_path('certificates');
    $validCertificates = (string) file_get_contents($corruptPath);
    file_put_contents($corruptPath, '{broken');
    $beforeArtifacts = count(business_list_snapshots());
    business_backup_test($results, 'corrupt source fails without partial backup artifact', static fn(): bool =>
        business_backup_throws(static fn() => business_create_snapshot()) && count(business_list_snapshots()) === $beforeArtifacts
    );
    file_put_contents($corruptPath, $validCertificates);

    business_backup_write_state(['customers' => [], 'receivables' => [], 'refilling-items' => [], 'certificates' => []]);
    $emptySnapshot = business_create_snapshot()['name'];
    business_backup_test($results, 'empty Business snapshot dry run and restore succeed', static fn(): bool =>
        business_snapshot_restore_dry_run($emptySnapshot)['snapshotCounts']['customers'] === 0
            && business_restore_snapshot($emptySnapshot)['counts']['certificateItems'] === 0
    );

    $deleteTarget = business_create_snapshot()['name'];
    $liveBeforeDelete = business_backup_hashes();
    business_delete_snapshot($deleteTarget);
    business_backup_test($results, 'snapshot deletion removes artifact only', static fn(): bool =>
        !file_exists(business_snapshot_path($deleteTarget)) && business_backup_hashes() === $liveBeforeDelete
    );

    foreach ($results as $passed) if (!$passed) exit(1);
} finally {
    business_backup_remove_tree($temporaryRoot);
}
