<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$project = dirname(__DIR__); $root = sys_get_temp_dir() . '/customer-api-test-' . bin2hex(random_bytes(6));
$private = $root . '/private'; $cookies = $root . '/cookies'; $server = null; $results = []; $password = 'Temporary-Test#2026';

function customer_test_http(string $url, string $cookies, string $method = 'GET', ?array $body = null, ?string $csrf = null): array
{
    $output = tempnam(sys_get_temp_dir(), 'customer-response-');
    $command = ['curl', '--silent', '--show-error', '--max-time', '10', '--output', $output, '--write-out', '%{http_code}', '--cookie', $cookies, '--cookie-jar', $cookies, '--request', $method];
    if ($body !== null) $command = [...$command, '--header', 'Content-Type: application/json', '--data', json_encode($body, JSON_THROW_ON_ERROR)];
    if ($csrf !== null) $command = [...$command, '--header', 'X-CSRF-Token: ' . $csrf];
    $command[] = $url; $pipes = []; $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to run request.');
    fclose($pipes[0]); $status = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException((string) $error);
    $raw = (string) file_get_contents($output); @unlink($output); $json = json_decode($raw, true);
    return ['status' => (int) $status, 'json' => is_array($json) ? $json : []];
}

function customer_test(array &$results, string $label, callable $test): void
{
    try { $results[$label] = $test() === true; } catch (Throwable) { $results[$label] = false; }
    fwrite(STDOUT, ($results[$label] ? 'PASS ' : 'FAIL ') . $label . PHP_EOL);
}

function customer_test_remove(string $directory): void
{
    if (!is_dir($directory)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($directory);
}

try {
    mkdir($private, 0700, true);
    file_put_contents($private . '/admin.php', "<?php\nreturn " . var_export(['admin_email' => 'customers@example.com', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'credential_version' => 1], true) . ";\n");
    $socket = stream_socket_server('tcp://127.0.0.1:0', $number, $message); if (!is_resource($socket)) throw new RuntimeException($message, $number);
    $address = stream_socket_get_name($socket, false); fclose($socket); $port = (int) substr(strrchr((string) $address, ':'), 1);
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project], [['pipe', 'r'], ['file', $root . '/server.log', 'a'], ['file', $root . '/server.log', 'a']], $pipes, $project, [...getenv(), 'APP_PRIVATE_ROOT' => $private]);
    if (!is_resource($server)) throw new RuntimeException('Unable to start test server.'); fclose($pipes[0]); $base = 'http://127.0.0.1:' . $port;
    for ($attempt = 0; $attempt < 30; $attempt++) { usleep(100000); try { customer_test_http($base . '/api/auth/session.php', $cookies); break; } catch (Throwable) {} }
    $api = $base . '/api/admin/business/customers.php';
    customer_test($results, 'unauthenticated customer API rejected', static fn(): bool => customer_test_http($api, $cookies)['status'] === 401);
    $login = customer_test_http($base . '/api/auth/login.php', $cookies, 'POST', ['password' => $password]); $csrf = $login['json']['csrfToken'] ?? '';
    if (!is_string($csrf) || $csrf === '') throw new RuntimeException('Authentication failed.');
    customer_test($results, 'write without CSRF rejected', static fn(): bool => customer_test_http($api, $cookies, 'POST', ['name' => 'A', 'address' => 'B'])['status'] === 403);
    $valid = ['name' => 'ABC Industries', 'address' => 'Pune', 'gstin' => '', 'contactPerson' => '', 'phone' => '', 'email' => '', 'isActive' => true];
    $created = customer_test_http($api, $cookies, 'POST', $valid, $csrf); $id = $created['json']['data']['id'] ?? '';
    customer_test($results, 'valid customer gets server ID', static fn(): bool => $created['status'] === 201 && $id === 'CUS0001');
    foreach ([
        'missing name rejected' => array_diff_key($valid, ['name' => true]), 'blank name rejected' => [...$valid, 'name' => '  '],
        'missing address rejected' => array_diff_key($valid, ['address' => true]), 'blank address rejected' => [...$valid, 'address' => '  '],
        'invalid email rejected' => [...$valid, 'name' => 'Email Test', 'email' => 'not-an-email'],
    ] as $label => $body) customer_test($results, $label, static fn(): bool => customer_test_http($api, $cookies, 'POST', $body, $csrf)['status'] === 400);
    customer_test($results, 'normalized duplicate rejected', static fn(): bool => customer_test_http($api, $cookies, 'POST', [...$valid, 'name' => ' abc   INDUSTRIES '], $csrf)['status'] === 409);
    $second = customer_test_http($api, $cookies, 'POST', [...$valid, 'name' => 'Second Customer'], $csrf); $secondId = $second['json']['data']['id'] ?? '';
    customer_test($results, 'optional empty GSTIN and phone accepted', static fn(): bool => $second['status'] === 201 && $secondId === 'CUS0002');
    customer_test($results, 'isActive defaults true when omitted', static function () use ($api, $cookies, $csrf): bool {
        $response = customer_test_http($api, $cookies, 'POST', ['name' => 'Third Customer', 'address' => 'Mumbai'], $csrf);
        return $response['status'] === 201 && ($response['json']['data']['isActive'] ?? null) === true;
    });
    customer_test($results, 'same name update and active to inactive', static function () use ($api, $cookies, $csrf, $id): bool {
        $response = customer_test_http($api, $cookies, 'PATCH', ['id' => $id, 'name' => 'ABC Industries', 'isActive' => false], $csrf);
        return $response['status'] === 200 && ($response['json']['data']['isActive'] ?? null) === false;
    });
    customer_test($results, 'inactive to active', static fn(): bool => (customer_test_http($api, $cookies, 'PATCH', ['id' => $id, 'isActive' => true], $csrf)['json']['data']['isActive'] ?? null) === true);
    customer_test($results, 'rename to duplicate rejected', static fn(): bool => customer_test_http($api, $cookies, 'PATCH', ['id' => $id, 'name' => ' second   customer '], $csrf)['status'] === 409);
    customer_test($results, 'ID cannot be changed as data', static fn(): bool => customer_test_http($api, $cookies, 'PATCH', ['id' => $id, 'newId' => 'CUS9999'], $csrf)['status'] === 400);
    customer_test($results, 'detail retrieval', static fn(): bool => (customer_test_http($api . '?id=' . $id, $cookies)['json']['data']['id'] ?? '') === $id);
    customer_test($results, 'DELETE is unsupported', static fn(): bool => customer_test_http($api, $cookies, 'DELETE', ['id' => $id], $csrf)['status'] === 405);
    file_put_contents($private . '/business/customers.json', '{broken');
    customer_test($results, 'corrupt storage returns controlled server error without replacement', static function () use ($api, $cookies, $private): bool {
        return customer_test_http($api, $cookies)['status'] === 500 && file_get_contents($private . '/business/customers.json') === '{broken';
    });
    exit(in_array(false, $results, true) ? 1 : 0);
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    customer_test_remove($root);
}
