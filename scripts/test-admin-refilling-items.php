<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$project = dirname(__DIR__); $root = sys_get_temp_dir() . '/refilling-item-api-test-' . bin2hex(random_bytes(6));
$private = $root . '/private'; $cookies = $root . '/cookies'; $server = null; $results = []; $password = 'Temporary-Test#2026';

function refilling_test_http(string $url, string $cookies, string $method = 'GET', ?array $body = null, ?string $csrf = null): array
{
    $output = tempnam(sys_get_temp_dir(), 'refilling-response-');
    $command = ['curl', '--silent', '--show-error', '--max-time', '10', '--output', $output, '--write-out', '%{http_code}', '--cookie', $cookies, '--cookie-jar', $cookies, '--request', $method];
    if ($body !== null) $command = [...$command, '--header', 'Content-Type: application/json', '--data', json_encode($body, JSON_THROW_ON_ERROR)];
    if ($csrf !== null) $command = [...$command, '--header', 'X-CSRF-Token: ' . $csrf];
    $command[] = $url; $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to run request.');
    fclose($pipes[0]); $status = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException((string) $error);
    $raw = (string) file_get_contents($output); @unlink($output); $json = json_decode($raw, true);
    return ['status' => (int) $status, 'json' => is_array($json) ? $json : [], 'raw' => $raw];
}

function refilling_test(array &$results, string $label, callable $test): void
{
    try { $results[$label] = $test() === true; } catch (Throwable) { $results[$label] = false; }
    fwrite(STDOUT, ($results[$label] ? 'PASS ' : 'FAIL ') . $label . PHP_EOL);
}

function refilling_test_remove(string $directory): void
{
    if (!is_dir($directory)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($directory);
}

try {
    mkdir($private, 0700, true);
    file_put_contents($private . '/admin.php', "<?php\nreturn " . var_export(['admin_email' => 'refilling@example.com', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'credential_version' => 1], true) . ";\n");
    $socket = stream_socket_server('tcp://127.0.0.1:0', $number, $message); if (!is_resource($socket)) throw new RuntimeException($message, $number);
    $address = stream_socket_get_name($socket, false); fclose($socket); $port = (int) substr(strrchr((string) $address, ':'), 1);
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project], [['pipe', 'r'], ['file', $root . '/server.log', 'a'], ['file', $root . '/server.log', 'a']], $pipes, $project, [...getenv(), 'APP_PRIVATE_ROOT' => $private]);
    if (!is_resource($server)) throw new RuntimeException('Unable to start test server.'); fclose($pipes[0]); $base = 'http://127.0.0.1:' . $port;
    for ($attempt = 0; $attempt < 30; $attempt++) { usleep(100000); try { refilling_test_http($base . '/api/auth/session.php', $cookies); break; } catch (Throwable) {} }
    $api = $base . '/api/admin/business/refilling-items.php';
    refilling_test($results, 'unauthenticated GET rejected', static fn(): bool => refilling_test_http($api, $cookies)['status'] === 401);
    refilling_test($results, 'unauthenticated mutation rejected', static fn(): bool => refilling_test_http($api, $cookies, 'POST', ['name' => 'Item'])['status'] === 401);
    $login = refilling_test_http($base . '/api/auth/login.php', $cookies, 'POST', ['password' => $password]); $csrf = $login['json']['csrfToken'] ?? '';
    if (!is_string($csrf) || $csrf === '') throw new RuntimeException('Authentication failed.');
    refilling_test($results, 'write without CSRF rejected', static fn(): bool => refilling_test_http($api, $cookies, 'POST', ['name' => 'Item'])['status'] === 403);
    refilling_test($results, 'write with invalid CSRF rejected', static fn(): bool => refilling_test_http($api, $cookies, 'POST', ['name' => 'Item'], 'invalid-token')['status'] === 403);
    $created = refilling_test_http($api, $cookies, 'POST', ['name' => 'ABC Fire Extinguisher'], $csrf); $id = $created['json']['data']['id'] ?? '';
    refilling_test($results, 'valid create gets server ID and defaults active', static fn(): bool => $created['status'] === 201 && $id === 'RFI0001' && ($created['json']['data'] ?? null) === ['id' => 'RFI0001', 'name' => 'ABC Fire Extinguisher', 'isActive' => true]);
    $inactive = refilling_test_http($api, $cookies, 'POST', ['name' => 'CO2 Fire Extinguisher', 'isActive' => false], $csrf); $inactiveId = $inactive['json']['data']['id'] ?? '';
    refilling_test($results, 'explicit inactive create persists', static fn(): bool => $inactive['status'] === 201 && $inactiveId === 'RFI0002' && ($inactive['json']['data']['isActive'] ?? true) === false);
    foreach ([
        'missing name rejected' => [], 'blank name rejected' => ['name' => ''], 'whitespace name rejected' => ['name' => '   '],
        'malformed active status rejected' => ['name' => 'Bad Status', 'isActive' => 'true'],
        'unknown field rejected' => ['name' => 'Extra', 'capacity' => '6 KG'],
        'client ID on create rejected' => ['id' => 'RFI9999', 'name' => 'Client ID'],
    ] as $label => $body) refilling_test($results, $label, static fn(): bool => refilling_test_http($api, $cookies, 'POST', $body, $csrf)['status'] === 400);
    foreach (['abc fire extinguisher', ' ABC   Fire Extinguisher '] as $duplicate) refilling_test($results, "normalized duplicate '$duplicate' rejected", static fn(): bool => refilling_test_http($api, $cookies, 'POST', ['name' => $duplicate], $csrf)['status'] === 409);
    refilling_test($results, 'same normalized name edit succeeds', static fn(): bool => refilling_test_http($api, $cookies, 'PATCH', ['id' => $id, 'name' => ' ABC   FIRE EXTINGUISHER '], $csrf)['status'] === 200);
    refilling_test($results, 'rename and Active to Inactive preserve ID', static function () use ($api, $cookies, $csrf, $id): bool {
        $response = refilling_test_http($api, $cookies, 'PATCH', ['id' => $id, 'name' => 'ABC Extinguisher', 'isActive' => false], $csrf);
        return $response['status'] === 200 && ($response['json']['data']['id'] ?? '') === $id && ($response['json']['data']['isActive'] ?? true) === false;
    });
    refilling_test($results, 'Inactive to Active succeeds', static fn(): bool => (refilling_test_http($api, $cookies, 'PATCH', ['id' => $id, 'isActive' => true], $csrf)['json']['data']['isActive'] ?? false) === true);
    refilling_test($results, 'rename to duplicate rejected', static fn(): bool => refilling_test_http($api, $cookies, 'PATCH', ['id' => $id, 'name' => ' co2   fire extinguisher '], $csrf)['status'] === 409);
    refilling_test($results, 'ID cannot be changed through update data', static fn(): bool => refilling_test_http($api, $cookies, 'PATCH', ['id' => $id, 'newId' => 'RFI9999'], $csrf)['status'] === 400);
    refilling_test($results, 'unknown ID rejected', static fn(): bool => refilling_test_http($api, $cookies, 'PATCH', ['id' => 'RFI9999', 'name' => 'Unknown'], $csrf)['status'] === 404);
    refilling_test($results, 'detail retrieval', static fn(): bool => (refilling_test_http($api . '?id=' . $inactiveId, $cookies)['json']['data']['id'] ?? '') === $inactiveId);
    refilling_test($results, 'DELETE unsupported and inactive data remains stored', static function () use ($api, $cookies, $csrf, $inactiveId): bool {
        $deleted = refilling_test_http($api, $cookies, 'DELETE', ['id' => $inactiveId], $csrf); $stored = refilling_test_http($api . '?id=' . $inactiveId, $cookies);
        return $deleted['status'] === 405 && $stored['status'] === 200 && ($stored['json']['data']['isActive'] ?? true) === false;
    });
    file_put_contents($private . '/business/refilling-items.json', json_encode([['id' => 'RFI0009']], JSON_THROW_ON_ERROR));
    refilling_test($results, 'malformed stored schema returns controlled error without reset or path disclosure', static function () use ($api, $cookies, $private): bool {
        $before = file_get_contents($private . '/business/refilling-items.json'); $response = refilling_test_http($api, $cookies);
        return $response['status'] === 500 && file_get_contents($private . '/business/refilling-items.json') === $before && !str_contains($response['raw'], $private);
    });
    file_put_contents($private . '/business/refilling-items.json', '{broken');
    refilling_test($results, 'corrupt JSON returns controlled error without replacement', static function () use ($api, $cookies, $private): bool {
        return refilling_test_http($api, $cookies)['status'] === 500 && file_get_contents($private . '/business/refilling-items.json') === '{broken';
    });
    exit(in_array(false, $results, true) ? 1 : 0);
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); } refilling_test_remove($root);
}
