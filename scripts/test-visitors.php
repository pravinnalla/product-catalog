<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$temporaryRoot = sys_get_temp_dir() . '/laxmikant-visitors-' . bin2hex(random_bytes(6));
putenv('APP_PRIVATE_ROOT=' . $temporaryRoot);
require_once dirname(__DIR__) . '/api/includes/visitor-storage.php';

$failures = [];
function visitor_test(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) $failures[] = $message;
}
function visitor_test_remove(string $directory): void
{
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($directory);
}

try {
    $now = strtotime('2026-08-27T07:00:00Z');
    $desktop = ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120 Safari/537.36'];
    $available = static fn(): bool => true;
    $lookup = static function (array $values, bool $fail = false): callable {
        return static function (string $command) use ($values, $fail): ?string {
            if ($fail) return null;
            foreach ($values as $field => $value) {
                if (str_contains($command, "'{$field}'")) return $value;
            }
            return null;
        };
    };
    $formatted = static fn(string $value): string => '  "' . $value . '" <utf8_string>' . PHP_EOL;

    visitor_test(visitor_location_from_server([
        'REMOTE_ADDR' => '203.0.113.10',
        'GEOIP_CITY' => 'Solapur',
        'GEOIP_REGION_NAME' => 'Maharashtra',
        'GEOIP_COUNTRY_NAME' => 'India',
    ], static function (): never { throw new RuntimeException('GeoLite2 should not run.'); }, $available) === 'Solapur, Maharashtra, India', 'Hosting GeoIP variables did not retain priority.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup(['city' => $formatted('Solapur'), 'subdivisions' => $formatted('Maharashtra'), 'country' => $formatted('India')]), $available) === 'Solapur, Maharashtra, India', 'Full GeoLite2 location failed.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup(['subdivisions' => $formatted('Maharashtra'), 'country' => $formatted('India')]), $available) === 'Maharashtra, India', 'Region and country fallback failed.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup(['country' => $formatted('India')]), $available) === 'India', 'Country-only fallback failed.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup([]), $available) === 'Unknown', 'Missing GeoLite2 data did not return Unknown.');
    $invalidCalled = false;
    visitor_test(visitor_geolite2_location('127.0.0.1; touch /tmp/injected', static function () use (&$invalidCalled): ?string { $invalidCalled = true; return null; }, $available) === 'Unknown' && !$invalidCalled, 'Invalid IP reached command execution.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup(['country' => $formatted('India')]), static fn(): bool => false) === 'Unknown', 'Missing executable did not fail safely.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup(['country' => $formatted('India')]), static fn(): bool => false) === 'Unknown', 'Missing/unreadable database did not fail safely.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup([], true), $available) === 'Unknown', 'Command failure did not fail safely.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup(['city' => 'not found', 'country' => $formatted('India')]), $available) === 'India', 'Missing city prevented country fallback.');
    visitor_test(visitor_geolite2_location('203.0.113.10', $lookup(['country' => $formatted('India')]), $available) === 'India', 'Raw mmdblookup formatting was retained.');
    $commands = [];
    visitor_geolite2_location('2001:db8::1', static function (string $command) use (&$commands): ?string { $commands[] = $command; return null; }, $available);
    visitor_test(count($commands) === 3
        && array_reduce($commands, static fn(bool $safe, string $command): bool => $safe
            && str_starts_with($command, '/bin/mmdblookup --file ')
            && str_contains($command, "'/usr/share/GeoIP/GeoLite2-City.mmdb'")
            && str_contains($command, "--ip '2001:db8::1'"), true), 'GeoLite2 command paths or validated IP escaping changed.');

    visitor_test(visitor_log('/', $desktop, $now), 'Public home visit was not logged.');
    $records = visitor_read_records();
    visitor_test(count($records) === 1, 'Public visit did not create exactly one record.');
    visitor_test($records[0]['timestamp'] === '2026-08-27T07:00:00+00:00', 'UTC timestamp was incorrect.');
    visitor_test($records[0]['ip'] === '203.0.113.10', 'REMOTE_ADDR was not stored.');
    visitor_test($records[0]['location'] === 'Unknown', 'Missing location did not fail safely.');
    visitor_test($records[0]['device'] === 'Desktop', 'Desktop classification failed.');
    visitor_test(array_keys($records[0]) === ['timestamp', 'ip', 'location', 'device'], 'Visitor JSON schema changed.');
    visitor_test(visitor_device_from_user_agent('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit Mobile') === 'Mobile', 'Mobile classification failed.');
    visitor_test(visitor_device_from_user_agent('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)') === 'Tablet', 'Tablet classification failed.');
    visitor_test(visitor_device_from_user_agent('Googlebot/2.1') === 'Bot', 'Bot classification failed.');
    visitor_test(visitor_device_from_user_agent('') === 'Unknown', 'Unknown classification failed.');
    visitor_test(!visitor_log('/admin/dashboard.html', $desktop, $now), 'Admin page was logged.');
    visitor_test(!visitor_log('/api/catalog/products.php', $desktop, $now), 'API request was logged.');
    visitor_test(!visitor_log('/src/assets/css/main.css', $desktop, $now), 'Static asset was logged.');
    visitor_test(count(visitor_read_records()) === 1, 'Rejected paths changed the visitor file.');

    $cutoff = visitor_cutoff($now);
    $retentionRecords = [
        ['timestamp' => gmdate(DATE_ATOM, $cutoff - 1), 'ip' => '192.0.2.1', 'location' => 'Unknown', 'device' => 'Desktop'],
        ['timestamp' => gmdate(DATE_ATOM, $cutoff), 'ip' => '192.0.2.2', 'location' => 'Unknown', 'device' => 'Mobile'],
        ['timestamp' => gmdate(DATE_ATOM, $now - 1), 'ip' => '192.0.2.3', 'location' => 'Unknown', 'device' => 'Tablet'],
    ];
    $retained = visitor_apply_retention($retentionRecords, $now);
    visitor_test(count($retained) === 2, 'Retention did not remove only records older than 45 days.');
    visitor_test($retained[0]['ip'] === '192.0.2.2', 'Exactly 45-day-old record was not retained.');
    visitor_atomic_write($retentionRecords);
    $recent = visitor_recent_records($now);
    visitor_test(count($recent) === 2, 'Automatic cleanup did not preserve valid newer records.');
    json_decode((string) file_get_contents(visitor_file_path()), true, 512, JSON_THROW_ON_ERROR);

    $beforeFailure = (string) file_get_contents(visitor_file_path());
    try { visitor_atomic_write([INF]); } catch (VisitorStorageException) { /* expected */ }
    visitor_test((string) file_get_contents(visitor_file_path()) === $beforeFailure, 'Failed write changed the existing visitor log.');

    if (function_exists('pcntl_fork')) {
        $children = [];
        for ($index = 0; $index < 8; $index++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $server = $desktop;
                $server['REMOTE_ADDR'] = '198.51.100.' . ($index + 1);
                exit(visitor_log('/contact.html', $server, $now) ? 0 : 1);
            }
            if ($pid > 0) $children[] = $pid;
        }
        foreach ($children as $pid) { pcntl_waitpid($pid, $status); visitor_test(pcntl_wexitstatus($status) === 0, 'Concurrent writer failed.'); }
        visitor_test(count(visitor_read_records()) === 10, 'Concurrent locking lost or duplicated visitor writes.');
        json_decode((string) file_get_contents(visitor_file_path()), true, 512, JSON_THROW_ON_ERROR);
    } else {
        visitor_test(false, 'pcntl is unavailable; concurrent write behavior was not exercised.');
    }

    $source = (string) file_get_contents(dirname(__DIR__) . '/src/admin/pages/visitors.page.js');
    preg_match('/<thead><tr>(.*?)<\/tr><\/thead>/s', $source, $header);
    visitor_test(isset($header[1]) && substr_count($header[1], '<th>') === 4, 'Admin table does not have exactly four columns.');
    visitor_test(!preg_match('/data-action=["\'](?:delete|clear|purge|edit)|>\s*(?:Delete|Clear|Purge|Edit)\b/i', $source), 'Visitor report contains a prohibited mutation control.');
    visitor_test(str_contains($source, 'visitor-previous') && str_contains($source, 'visitor-next'), 'Pagination controls are missing.');
    $apiSource = (string) file_get_contents(dirname(__DIR__) . '/api/admin/visitors.php');
    visitor_test(str_contains($apiSource, "'success' => true") && str_contains($apiSource, "'items' =>") && str_contains($apiSource, "'pagination' =>"), 'Admin Visitor Reports API response shape changed.');

    if ($failures !== []) {
        foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}\n");
        exit(1);
    }
    fwrite(STDOUT, "Visitor GeoLite2 fallbacks and safety, storage schema, classification, allowlist, retention boundary, atomic failure, concurrent writes, JSON validity, API shape, read-only table, and pagination tests passed.\n");
} finally {
    visitor_test_remove($temporaryRoot);
}
