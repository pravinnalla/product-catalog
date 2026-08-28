<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$project = dirname(__DIR__);
$temporaryRoot = sys_get_temp_dir() . '/admin-backup-api-test-' . bin2hex(random_bytes(6));
$privateRoot = $temporaryRoot . '/private';
$cookieJar = $temporaryRoot . '/cookies.txt';
$serverLog = $temporaryRoot . '/server.log';
$password = 'Temporary-Test#2026';
$results = [];
$server = null;

function admin_backup_test(array &$results, string $label, callable $callback): void
{
    try { $results[$label] = $callback() === true; }
    catch (Throwable) { $results[$label] = false; }
    fwrite(STDOUT, ($results[$label] ? 'PASS ' : 'FAIL ') . $label . PHP_EOL);
}

function admin_backup_remove_tree(string $directory): void
{
    if (!is_dir($directory)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($directory);
}

/** @return array{status: int, body: string, json: array<string, mixed>|null} */
function admin_backup_http(string $url, string $cookieJar, string $method = 'GET', ?array $body = null, ?string $csrf = null): array
{
    $output = tempnam(sys_get_temp_dir(), 'admin-backup-response-');
    if ($output === false) throw new RuntimeException('Unable to create response file.');
    $command = ['curl', '--silent', '--show-error', '--max-time', '10', '--output', $output, '--write-out', '%{http_code}', '--cookie', $cookieJar, '--cookie-jar', $cookieJar, '--request', $method];
    if ($body !== null) {
        $command = [...$command, '--header', 'Content-Type: application/json', '--data', json_encode($body, JSON_THROW_ON_ERROR)];
    }
    if ($csrf !== null) $command = [...$command, '--header', 'X-CSRF-Token: ' . $csrf];
    $command[] = $url;
    $pipes = [];
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to run HTTP request.');
    fclose($pipes[0]);
    $statusText = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) { @unlink($output); throw new RuntimeException((string) $error); }
    $responseBody = (string) file_get_contents($output); @unlink($output);
    $decoded = json_decode($responseBody, true);
    return ['status' => (int) $statusText, 'body' => $responseBody, 'json' => is_array($decoded) ? $decoded : null];
}

try {
    mkdir($privateRoot . '/catalog', 0700, true);
    foreach (['categories', 'subcategories', 'suppliers', 'products'] as $dataset) {
        copy($project . '/private/catalog/' . $dataset . '.json', $privateRoot . '/catalog/' . $dataset . '.json');
    }
    mkdir($privateRoot . '/business', 0700, true);
    $businessFixtures = [
        'customers' => [['id' => 'CUS0001', 'name' => 'PDF Restore Customer', 'address' => 'Pune', 'gstin' => '', 'contactPerson' => '', 'phone' => '', 'email' => '', 'isActive' => true]],
        'receivables' => [],
        'refilling-items' => [['id' => 'RFI0001', 'name' => 'ABC Fire Extinguisher', 'isActive' => true]],
        'certificates' => [[
            'id' => 'CERT0001', 'certificateNumber' => 'CERT-RESTORE-1', 'customerId' => 'CUS0001', 'invoiceNumber' => 'INV-RESTORE-1', 'certificateDate' => '2026-08-01',
            'items' => [['id' => 'CIT0001', 'refillingItemId' => 'RFI0001', 'capacity' => '6 KG', 'quantity' => 1, 'serialNumbers' => ['RESTORE-SERIAL'], 'refillingDate' => '2026-08-01', 'nextRefillingDate' => '2027-08-01', 'remark' => '']],
            'remarks' => '',
        ]],
    ];
    foreach ($businessFixtures as $dataset => $records) {
        file_put_contents($privateRoot . '/business/' . $dataset . '.json', json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }
    $credentials = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export([
        'admin_email' => 'backup-test@example.com',
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'credential_version' => 1,
        'password_updated_at' => gmdate(DATE_ATOM),
    ], true) . ";\n";
    file_put_contents($privateRoot . '/admin.php', $credentials);

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    if (!is_resource($socket)) throw new RuntimeException($errorMessage, $errorNumber);
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $port = (int) substr(strrchr((string) $address, ':'), 1);
    $pipes = [];
    $server = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project],
        [['pipe', 'r'], ['file', $serverLog, 'a'], ['file', $serverLog, 'a']], $pipes, $project,
        [...getenv(), 'APP_PRIVATE_ROOT' => $privateRoot]
    );
    if (!is_resource($server)) throw new RuntimeException('Unable to start test server.');
    fclose($pipes[0]);
    $base = 'http://127.0.0.1:' . $port;
    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        usleep(100000);
        try { admin_backup_http($base . '/api/auth/session.php', $cookieJar); $ready = true; break; } catch (Throwable) {}
    }
    if (!$ready) throw new RuntimeException('Test server did not start.');

    admin_backup_test($results, 'unauthenticated list returns 401', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php?domain=catalog', $cookieJar)['status'] === 401
    );
    admin_backup_test($results, 'unauthenticated download returns 401', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php?action=download&domain=catalog&id=catalog-20260101-000000-00000000', $cookieJar)['status'] === 401
    );
    admin_backup_test($results, 'unauthenticated delete returns 401', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'DELETE', ['domain' => 'catalog', 'type' => 'snapshot', 'id' => 'catalog-20260101-000000-00000000', 'confirmation' => 'DELETE'])['status'] === 401
    );
    admin_backup_test($results, 'unauthenticated Business creation returns 401', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'create-snapshot', 'domain' => 'business'])['status'] === 401
    );

    $login = admin_backup_http($base . '/api/auth/login.php', $cookieJar, 'POST', ['password' => $password]);
    $csrf = is_string($login['json']['csrfToken'] ?? null) ? $login['json']['csrfToken'] : '';
    if ($login['status'] !== 200 || $csrf === '') throw new RuntimeException('Test authentication failed.');

    admin_backup_test($results, 'authenticated list succeeds without path disclosure', static function () use ($base, $cookieJar, $privateRoot): bool {
        $response = admin_backup_http($base . '/api/admin/backups.php?domain=catalog', $cookieJar);
        return $response['status'] === 200 && ($response['json']['success'] ?? false) === true
            && !str_contains($response['body'], $privateRoot) && !str_contains($response['body'], 'storageRoot');
    });
    admin_backup_test($results, 'registered domains include Catalogue and Business', static function () use ($base, $cookieJar): bool {
        $response = admin_backup_http($base . '/api/admin/backups.php?domain=business', $cookieJar);
        return $response['status'] === 200
            && array_column($response['json']['domains'] ?? [], 'id') === ['catalog', 'business']
            && !str_contains($response['body'], 'storageRoot');
    });
    admin_backup_test($results, 'unknown domain rejected', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php?domain=invoices', $cookieJar)['status'] === 400
    );
    admin_backup_test($results, 'traversal domain rejected', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php?domain=..%2Fcatalog', $cookieJar)['status'] === 400
    );
    foreach (['create-snapshot', 'dry-run', 'restore'] as $action) {
        admin_backup_test($results, $action . ' without CSRF returns 403', static fn(): bool =>
            admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => $action, 'domain' => 'catalog'])['status'] === 403
        );
    }
    admin_backup_test($results, 'delete without CSRF returns 403', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'DELETE', ['domain' => 'catalog', 'type' => 'snapshot', 'id' => 'catalog-20260101-000000-00000000', 'confirmation' => 'DELETE'])['status'] === 403
    );
    admin_backup_test($results, 'delete with invalid CSRF returns 403', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'DELETE', ['domain' => 'catalog', 'type' => 'snapshot', 'id' => 'catalog-20260101-000000-00000000', 'confirmation' => 'DELETE'], str_repeat('0', 64))['status'] === 403
    );
    admin_backup_test($results, 'Business creation without CSRF returns 403', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'create-snapshot', 'domain' => 'business'])['status'] === 403
    );

    $businessCreated = admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'create-snapshot', 'domain' => 'business'], $csrf);
    $businessSnapshot = is_string($businessCreated['json']['item']['id'] ?? null) ? $businessCreated['json']['item']['id'] : '';
    admin_backup_test($results, 'authenticated Business snapshot creation and counts', static fn(): bool =>
        $businessCreated['status'] === 201 && preg_match('/^business-\d{8}-\d{6}-[a-f0-9]{8}$/', $businessSnapshot) === 1
            && ($businessCreated['json']['item']['counts']['certificateItems'] ?? null) === 1
    );
    admin_backup_test($results, 'Business dry run succeeds', static function () use ($base, $cookieJar, $csrf, $businessSnapshot): bool {
        $response = admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'dry-run', 'domain' => 'business', 'type' => 'snapshot', 'id' => $businessSnapshot], $csrf);
        return $response['status'] === 200 && ($response['json']['result']['validation'] ?? null) === 'passed';
    });
    admin_backup_test($results, 'Business download is authenticated and non-empty', static function () use ($base, $cookieJar, $businessSnapshot): bool {
        $response = admin_backup_http($base . '/api/admin/backups.php?action=download&domain=business&id=' . rawurlencode($businessSnapshot), $cookieJar);
        return $response['status'] === 200 && str_starts_with($response['body'], 'PK');
    });
    admin_backup_test($results, 'Business restore revalidates server-side', static function () use ($base, $cookieJar, $csrf, $businessSnapshot): bool {
        $response = admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'restore', 'domain' => 'business', 'type' => 'snapshot', 'id' => $businessSnapshot, 'confirmation' => 'RESTORE'], $csrf);
        return $response['status'] === 200 && ($response['json']['result']['rollbackBackupCreated'] ?? false) === true;
    });
    admin_backup_test($results, 'Certificate PDF resolves restored Business data', static function () use ($base, $cookieJar): bool {
        $response = admin_backup_http($base . '/api/business/certificate-pdf.php?id=CERT0001', $cookieJar);
        return $response['status'] === 200 && str_starts_with($response['body'], '%PDF-');
    });
    $businessHashes = [];
    foreach (['customers', 'receivables', 'refilling-items', 'certificates'] as $dataset) {
        $businessHashes[$dataset] = hash_file('sha256', $privateRoot . '/business/' . $dataset . '.json');
    }
    $businessDeleted = admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'DELETE', [
        'domain' => 'business', 'type' => 'snapshot', 'id' => $businessSnapshot, 'confirmation' => 'DELETE',
    ], $csrf);
    admin_backup_test($results, 'Business snapshot deletion leaves live data unchanged', static function () use ($businessDeleted, $privateRoot, $businessHashes): bool {
        if ($businessDeleted['status'] !== 200) return false;
        foreach ($businessHashes as $dataset => $hash) if (hash_file('sha256', $privateRoot . '/business/' . $dataset . '.json') !== $hash) return false;
        return true;
    });

    $created = admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'create-snapshot', 'domain' => 'catalog'], $csrf);
    $snapshot = is_string($created['json']['item']['id'] ?? null) ? $created['json']['item']['id'] : '';
    admin_backup_test($results, 'valid snapshot creation', static fn(): bool =>
        $created['status'] === 201 && preg_match('/^catalog-\d{8}-\d{6}-[a-f0-9]{8}$/', $snapshot) === 1
    );
    admin_backup_test($results, 'valid snapshot listing', static function () use ($base, $cookieJar, $snapshot): bool {
        $listed = admin_backup_http($base . '/api/admin/backups.php?domain=catalog', $cookieJar);
        return $listed['status'] === 200 && in_array($snapshot, array_column($listed['json']['items'] ?? [], 'id'), true);
    });
    admin_backup_test($results, 'invalid backup rejected', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'dry-run', 'domain' => 'catalog', 'type' => 'snapshot', 'id' => 'catalog-20260101-000000-00000000'], $csrf)['status'] === 400
    );
    admin_backup_test($results, 'traversal backup ID rejected', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'dry-run', 'domain' => 'catalog', 'type' => 'snapshot', 'id' => '../catalog'], $csrf)['status'] === 400
    );
    admin_backup_test($results, 'valid dry-run', static function () use ($base, $cookieJar, $csrf, $snapshot): bool {
        $response = admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'dry-run', 'domain' => 'catalog', 'type' => 'snapshot', 'id' => $snapshot], $csrf);
        return $response['status'] === 200 && ($response['json']['result']['validation'] ?? '') === 'passed';
    });
    admin_backup_test($results, 'restore confirmation missing rejected', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'restore', 'domain' => 'catalog', 'type' => 'snapshot', 'id' => $snapshot], $csrf)['status'] === 400
    );
    admin_backup_test($results, 'restore confirmation incorrect rejected', static fn(): bool =>
        admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'restore', 'domain' => 'catalog', 'type' => 'snapshot', 'id' => $snapshot, 'confirmation' => 'yes'], $csrf)['status'] === 400
    );
    admin_backup_test($results, 'download cannot escape backup root', static function () use ($base, $cookieJar, $privateRoot): bool {
        $response = admin_backup_http($base . '/api/admin/backups.php?action=download&domain=catalog&id=..%2Fcatalog', $cookieJar);
        return $response['status'] === 400 && !str_contains($response['body'], $privateRoot);
    });
    admin_backup_test($results, 'validated snapshot download succeeds', static function () use ($base, $cookieJar, $snapshot): bool {
        $response = admin_backup_http($base . '/api/admin/backups.php?action=download&domain=catalog&id=' . rawurlencode($snapshot), $cookieJar);
        return $response['status'] === 200 && str_starts_with($response['body'], "PK");
    });

    $productsPath = $privateRoot . '/catalog/products.json';
    $snapshotHash = hash_file('sha256', $productsPath);
    $products = json_decode((string) file_get_contents($productsPath), true, 512, JSON_THROW_ON_ERROR);
    $products[0]['title'] .= ' isolated restore test';
    file_put_contents($productsPath, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    $beforeSnapshots = count(glob($privateRoot . '/backups/snapshots/catalog-*', GLOB_ONLYDIR) ?: []);
    $restored = admin_backup_http($base . '/api/admin/backups.php', $cookieJar, 'POST', ['action' => 'restore', 'domain' => 'catalog', 'type' => 'snapshot', 'id' => $snapshot, 'confirmation' => 'RESTORE'], $csrf);
    admin_backup_test($results, 'valid isolated restore', static fn(): bool =>
        $restored['status'] === 200 && hash_file('sha256', $productsPath) === $snapshotHash
            && ($restored['json']['result']['validation'] ?? '') === 'passed'
    );
    admin_backup_test($results, 'pre-restore rollback snapshot created', static fn(): bool =>
        ($restored['json']['result']['rollbackBackupCreated'] ?? false) === true
            && count(glob($privateRoot . '/backups/snapshots/catalog-*', GLOB_ONLYDIR) ?: []) === $beforeSnapshots + 1
    );
    require_once $project . '/api/includes/catalog-storage.php';
    putenv('APP_PRIVATE_ROOT=' . $privateRoot);
    admin_backup_test($results, 'catalogue remains valid', static fn(): bool => count(catalog_read_all()) === 4);

    $liveHashes = [];
    foreach (catalog_dataset_names() as $dataset) $liveHashes[$dataset] = hash_file('sha256', catalog_dataset_path($dataset));
    $validProducts = (string) file_get_contents(catalog_dataset_path('products'));
    $datasetBackupToDelete = catalog_create_backup('products', $validProducts);
    $datasetBackupToKeep = catalog_create_backup('products', $validProducts);
    $snapshotDirectories = glob($privateRoot . '/backups/snapshots/catalog-*', GLOB_ONLYDIR) ?: [];
    sort($snapshotDirectories);
    $snapshotToDelete = basename($snapshotDirectories[0]);
    $snapshotToKeep = basename($snapshotDirectories[1]);

    $deleteRequest = static fn(array $body, ?string $token = null): array => admin_backup_http(
        $base . '/api/admin/backups.php', $cookieJar, 'DELETE', $body, $token ?? $csrf
    );
    $snapshotSelection = ['domain' => 'catalog', 'type' => 'snapshot', 'id' => $snapshotToDelete];
    admin_backup_test($results, 'delete unknown domain rejected', static fn(): bool =>
        $deleteRequest([...$snapshotSelection, 'domain' => 'invoices', 'confirmation' => 'DELETE'])['status'] === 400
    );
    admin_backup_test($results, 'delete traversal domain rejected', static fn(): bool =>
        $deleteRequest([...$snapshotSelection, 'domain' => '../catalog', 'confirmation' => 'DELETE'])['status'] === 400
    );
    admin_backup_test($results, 'delete invalid backup ID rejected', static fn(): bool =>
        $deleteRequest([...$snapshotSelection, 'id' => 'invalid', 'confirmation' => 'DELETE'])['status'] === 422
    );
    admin_backup_test($results, 'delete traversal backup ID rejected', static fn(): bool =>
        $deleteRequest([...$snapshotSelection, 'id' => '../catalog', 'confirmation' => 'DELETE'])['status'] === 422
    );
    admin_backup_test($results, 'delete absolute backup ID rejected', static fn(): bool =>
        $deleteRequest([...$snapshotSelection, 'id' => '/tmp/catalog', 'confirmation' => 'DELETE'])['status'] === 422
    );
    admin_backup_test($results, 'delete missing confirmation rejected', static fn(): bool =>
        $deleteRequest($snapshotSelection)['status'] === 400
    );
    admin_backup_test($results, 'delete wrong confirmation rejected', static fn(): bool =>
        $deleteRequest([...$snapshotSelection, 'confirmation' => 'delete'])['status'] === 400
    );
    admin_backup_test($results, 'delete nonexistent item returns 404', static fn(): bool =>
        $deleteRequest([...$snapshotSelection, 'id' => 'catalog-20260101-000000-00000000', 'confirmation' => 'DELETE'])['status'] === 404
    );

    $outsideSnapshot = $temporaryRoot . '/outside-snapshot';
    mkdir($outsideSnapshot);
    $symlinkSnapshotId = 'catalog-20260101-000001-00000001';
    symlink($outsideSnapshot, catalog_snapshot_directory() . '/' . $symlinkSnapshotId);
    admin_backup_test($results, 'symlink snapshot deletion rejected', static fn(): bool =>
        $deleteRequest(['domain' => 'catalog', 'type' => 'snapshot', 'id' => $symlinkSnapshotId, 'confirmation' => 'DELETE'])['status'] === 422
    );
    unlink(catalog_snapshot_directory() . '/' . $symlinkSnapshotId);

    $outsideBackup = $temporaryRoot . '/outside-backup.json';
    file_put_contents($outsideBackup, $validProducts);
    $symlinkBackupId = 'products-20260101-000002-00000002.json';
    symlink($outsideBackup, catalog_backup_directory() . '/' . $symlinkBackupId);
    admin_backup_test($results, 'symlink dataset backup deletion rejected', static fn(): bool =>
        $deleteRequest(['domain' => 'catalog', 'type' => 'dataset-backup', 'dataset' => 'products', 'id' => $symlinkBackupId, 'confirmation' => 'DELETE'])['status'] === 422
    );
    unlink(catalog_backup_directory() . '/' . $symlinkBackupId);

    $deletedDataset = $deleteRequest(['domain' => 'catalog', 'type' => 'dataset-backup', 'dataset' => 'products', 'id' => $datasetBackupToDelete, 'confirmation' => 'DELETE']);
    admin_backup_test($results, 'delete valid dataset backup succeeds', static fn(): bool =>
        $deletedDataset['status'] === 200 && ($deletedDataset['json']['deleted']['id'] ?? '') === $datasetBackupToDelete
            && !is_file(catalog_backup_path('products', $datasetBackupToDelete))
    );
    admin_backup_test($results, 'other dataset backup remains unchanged', static fn(): bool =>
        is_file(catalog_backup_path('products', $datasetBackupToKeep))
    );
    $deletedSnapshot = $deleteRequest([...$snapshotSelection, 'confirmation' => 'DELETE']);
    admin_backup_test($results, 'delete valid snapshot succeeds', static fn(): bool =>
        $deletedSnapshot['status'] === 200 && ($deletedSnapshot['json']['deleted']['id'] ?? '') === $snapshotToDelete
            && !is_dir(catalog_snapshot_path($snapshotToDelete)) && is_dir(catalog_snapshot_path($snapshotToKeep))
    );
    $lastSnapshot = $deleteRequest(['domain' => 'catalog', 'type' => 'snapshot', 'id' => $snapshotToKeep, 'confirmation' => 'DELETE']);
    admin_backup_test($results, 'last valid complete snapshot deletion returns 409', static fn(): bool =>
        $lastSnapshot['status'] === 409 && is_dir(catalog_snapshot_path($snapshotToKeep))
    );
    admin_backup_test($results, 'active catalogue unchanged after deletion', static function () use ($liveHashes): bool {
        foreach ($liveHashes as $dataset => $hash) if (hash_file('sha256', catalog_dataset_path($dataset)) !== $hash) return false;
        return true;
    });
    admin_backup_test($results, 'delete errors disclose no private paths', static fn(): bool =>
        !str_contains($lastSnapshot['body'], $privateRoot) && !str_contains($lastSnapshot['body'], 'snapshotRoot')
    );

    exit(in_array(false, $results, true) ? 1 : 0);
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    admin_backup_remove_tree($temporaryRoot);
}
