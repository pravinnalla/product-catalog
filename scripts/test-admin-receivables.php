<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
$project = dirname(__DIR__); $root = sys_get_temp_dir() . '/receivable-api-test-' . bin2hex(random_bytes(6));
$private = $root . '/private'; $cookies = $root . '/cookies'; $server = null; $results = []; $password = 'Temporary-Test#2026';

function receivable_test_http(string $url, string $cookies, string $method = 'GET', ?array $body = null, ?string $csrf = null): array
{
    $output = tempnam(sys_get_temp_dir(), 'receivable-response-');
    $command = ['curl', '--silent', '--show-error', '--max-time', '10', '--output', $output, '--write-out', '%{http_code}', '--cookie', $cookies, '--cookie-jar', $cookies, '--request', $method];
    if ($body !== null) $command = [...$command, '--header', 'Content-Type: application/json', '--data', json_encode($body, JSON_THROW_ON_ERROR)];
    if ($csrf !== null) $command = [...$command, '--header', 'X-CSRF-Token: ' . $csrf];
    $command[] = $url; $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to run request.');
    fclose($pipes[0]); $status = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException((string) $error);
    $raw = (string) file_get_contents($output); @unlink($output); $json = json_decode($raw, true);
    return ['status' => (int) $status, 'json' => is_array($json) ? $json : []];
}

function receivable_test(array &$results, string $label, callable $test): void
{
    try { $results[$label] = $test() === true; } catch (Throwable) { $results[$label] = false; }
    fwrite(STDOUT, ($results[$label] ? 'PASS ' : 'FAIL ') . $label . PHP_EOL);
}

function receivable_test_remove(string $directory): void
{
    if (!is_dir($directory)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()); rmdir($directory);
}

try {
    mkdir($private . '/business', 0700, true);
    file_put_contents($private . '/admin.php', "<?php\nreturn " . var_export(['admin_email' => 'receivables@example.com', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'credential_version' => 1], true) . ";\n");
    $customers = [
        ['id' => 'CUS0001', 'name' => 'Active Customer', 'address' => 'Pune', 'gstin' => '', 'contactPerson' => '', 'phone' => '', 'email' => '', 'isActive' => true],
        ['id' => 'CUS0002', 'name' => 'Inactive Customer', 'address' => 'Mumbai', 'gstin' => '', 'contactPerson' => '', 'phone' => '', 'email' => '', 'isActive' => false],
        ['id' => 'CUS0003', 'name' => 'Other Customer', 'address' => 'Nashik', 'gstin' => '', 'contactPerson' => '', 'phone' => '', 'email' => '', 'isActive' => true],
    ];
    file_put_contents($private . '/business/customers.json', json_encode($customers, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $socket = stream_socket_server('tcp://127.0.0.1:0', $number, $message); if (!is_resource($socket)) throw new RuntimeException($message, $number);
    $address = stream_socket_get_name($socket, false); fclose($socket); $port = (int) substr(strrchr((string) $address, ':'), 1);
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $project], [['pipe', 'r'], ['file', $root . '/server.log', 'a'], ['file', $root . '/server.log', 'a']], $pipes, $project, [...getenv(), 'APP_PRIVATE_ROOT' => $private]);
    if (!is_resource($server)) throw new RuntimeException('Unable to start test server.'); fclose($pipes[0]); $base = 'http://127.0.0.1:' . $port;
    for ($attempt = 0; $attempt < 30; $attempt++) { usleep(100000); try { receivable_test_http($base . '/api/auth/session.php', $cookies); break; } catch (Throwable) {} }
    $api = $base . '/api/admin/business/receivables.php';
    receivable_test($results, 'unauthenticated API rejected', static fn(): bool => receivable_test_http($api, $cookies)['status'] === 401);
    $login = receivable_test_http($base . '/api/auth/login.php', $cookies, 'POST', ['password' => $password]); $csrf = $login['json']['csrfToken'] ?? '';
    if (!is_string($csrf) || $csrf === '') throw new RuntimeException('Authentication failed.');
    $valid = ['customerId' => 'CUS0001', 'invoiceNumber' => 'GST/26-27/001', 'invoiceDate' => '2026-08-26', 'invoiceAmount' => 10000, 'dueDate' => null, 'businessType' => 'PRODUCT', 'remarks' => ''];
    receivable_test($results, 'mutation without CSRF rejected', static fn(): bool => receivable_test_http($api, $cookies, 'POST', $valid)['status'] === 403);
    $created = receivable_test_http($api, $cookies, 'POST', $valid, $csrf); $id = $created['json']['data']['id'] ?? '';
    receivable_test($results, 'valid create has ID and calculated unpaid totals', static fn(): bool => $created['status'] === 201 && $id === 'REC0001' && ($created['json']['data']['payments'] ?? null) === [] && ($created['json']['data']['balance'] ?? null) === 10000 && ($created['json']['data']['paymentStatus'] ?? '') === 'UNPAID');
    foreach ([
        'unknown customer rejected' => [...$valid, 'customerId' => 'CUS9999', 'invoiceNumber' => 'X1'],
        'inactive customer rejected on create' => [...$valid, 'customerId' => 'CUS0002', 'invoiceNumber' => 'X2'],
        'invalid business type rejected' => [...$valid, 'invoiceNumber' => 'X3', 'businessType' => 'SERVICE'],
        'zero amount rejected' => [...$valid, 'invoiceNumber' => 'X4', 'invoiceAmount' => 0],
        'invalid date rejected' => [...$valid, 'invoiceNumber' => 'X5', 'invoiceDate' => '2026-02-30'],
        'client receivable ID rejected' => [...$valid, 'invoiceNumber' => 'X6', 'id' => 'REC9999'],
    ] as $label => $body) receivable_test($results, $label, static fn(): bool => receivable_test_http($api, $cookies, 'POST', $body, $csrf)['status'] === 400);
    receivable_test($results, 'normalized duplicate rejected', static fn(): bool => receivable_test_http($api, $cookies, 'POST', [...$valid, 'invoiceNumber' => ' gst/26-27/001 '], $csrf)['status'] === 409);
    receivable_test($results, 'same invoice allowed for another customer', static fn(): bool => receivable_test_http($api, $cookies, 'POST', [...$valid, 'customerId' => 'CUS0003'], $csrf)['status'] === 201);
    $india = new DateTimeZone('Asia/Kolkata'); $today = new DateTimeImmutable('today', $india); $past = $today->modify('-1 day')->format('Y-m-d'); $todayText = $today->format('Y-m-d');
    $overdue = receivable_test_http($api, $cookies, 'POST', [...$valid, 'invoiceNumber' => 'OVERDUE-1', 'dueDate' => $past], $csrf);
    $dueToday = receivable_test_http($api, $cookies, 'POST', [...$valid, 'invoiceNumber' => 'DUE-TODAY-1', 'dueDate' => $todayText], $csrf);
    receivable_test($results, 'past due balance is overdue', static fn(): bool => ($overdue['json']['data']['overdue'] ?? false) === true);
    receivable_test($results, 'due today is not overdue', static fn(): bool => ($dueToday['json']['data']['overdue'] ?? true) === false);
    $overdueId = $overdue['json']['data']['id'] ?? '';
    $paidOverdue = receivable_test_http($api . '?action=payment', $cookies, 'POST', ['id' => $overdueId, 'paymentDate' => $todayText, 'amount' => 10000], $csrf);
    receivable_test($results, 'paid past-due invoice is not overdue', static fn(): bool => ($paidOverdue['json']['data']['overdue'] ?? true) === false && ($paidOverdue['json']['data']['paymentStatus'] ?? '') === 'PAID');
    receivable_test($results, 'unsupported action rejected', static fn(): bool => receivable_test_http($api . '?action=unknown', $cookies, 'POST', $valid, $csrf)['status'] === 400);
    $paymentApi = $api . '?action=payment';
    $first = receivable_test_http($paymentApi, $cookies, 'POST', ['id' => $id, 'paymentDate' => '2026-08-27', 'amount' => 4000, 'paymentMode' => 'BANK', 'reference' => 'NEFT1', 'remarks' => ''], $csrf);
    $paymentId = $first['json']['data']['payments'][0]['id'] ?? '';
    receivable_test($results, 'add payment recalculates partly paid', static fn(): bool => $first['status'] === 201 && $paymentId === 'PAY0001' && ($first['json']['data']['amountPaid'] ?? null) === 4000 && ($first['json']['data']['balance'] ?? null) === 6000 && ($first['json']['data']['paymentStatus'] ?? '') === 'PARTLY_PAID');
    receivable_test($results, 'payment exceeding balance rejected', static fn(): bool => receivable_test_http($paymentApi, $cookies, 'POST', ['id' => $id, 'paymentDate' => '2026-08-28', 'amount' => 7000], $csrf)['status'] === 400);
    receivable_test($results, 'client payment ID rejected', static fn(): bool => receivable_test_http($paymentApi, $cookies, 'POST', ['id' => $id, 'paymentId' => 'PAY9999', 'paymentDate' => '2026-08-28', 'amount' => 100], $csrf)['status'] === 400);
    receivable_test($results, 'edit payment excludes old amount', static fn(): bool => receivable_test_http($paymentApi, $cookies, 'PATCH', ['id' => $id, 'paymentId' => $paymentId, 'paymentDate' => '2026-08-27', 'amount' => 6000], $csrf)['status'] === 200);
    receivable_test($results, 'invoice amount below payments rejected', static fn(): bool => receivable_test_http($api, $cookies, 'PATCH', ['id' => $id, ...$valid, 'invoiceAmount' => 5999], $csrf)['status'] === 400);
    receivable_test($results, 'same-record invoice edit is not duplicate', static fn(): bool => receivable_test_http($api, $cookies, 'PATCH', ['id' => $id, ...$valid], $csrf)['status'] === 200);
    receivable_test($results, 'delete payment leaves receivable and recalculates', static function () use ($paymentApi, $api, $cookies, $csrf, $id, $paymentId): bool {
        $deleted = receivable_test_http($paymentApi, $cookies, 'DELETE', ['id' => $id, 'paymentId' => $paymentId], $csrf);
        $stored = receivable_test_http($api . '?id=' . $id, $cookies); return $deleted['status'] === 200 && ($stored['json']['data']['paymentStatus'] ?? '') === 'UNPAID';
    });
    $customers[0]['isActive'] = false; file_put_contents($private . '/business/customers.json', json_encode($customers, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    receivable_test($results, 'historical receivable remains readable and editable after customer becomes inactive', static function () use ($api, $cookies, $csrf, $id, $valid): bool {
        $read = receivable_test_http($api . '?id=' . $id, $cookies); $edited = receivable_test_http($api, $cookies, 'PATCH', ['id' => $id, ...$valid, 'remarks' => 'Historical'], $csrf);
        return $read['status'] === 200 && ($read['json']['data']['customerActive'] ?? true) === false && $edited['status'] === 200;
    });
    receivable_test($results, 'delete receivable does not delete customer', static function () use ($api, $cookies, $csrf, $id, $private): bool {
        $response = receivable_test_http($api, $cookies, 'DELETE', ['id' => $id], $csrf);
        $storedCustomers = json_decode((string) file_get_contents($private . '/business/customers.json'), true); return $response['status'] === 200 && count($storedCustomers) === 3;
    });
    receivable_test($results, 'unknown receivable delete rejected', static fn(): bool => receivable_test_http($api, $cookies, 'DELETE', ['id' => 'REC9999'], $csrf)['status'] === 404);
    file_put_contents($private . '/business/receivables.json', json_encode([['id' => 'REC0009']], JSON_THROW_ON_ERROR));
    receivable_test($results, 'invalid stored schema returns controlled error without reset', static function () use ($api, $cookies, $private): bool {
        $before = file_get_contents($private . '/business/receivables.json');
        return receivable_test_http($api, $cookies)['status'] === 500 && file_get_contents($private . '/business/receivables.json') === $before;
    });
    exit(in_array(false, $results, true) ? 1 : 0);
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); } receivable_test_remove($root);
}
