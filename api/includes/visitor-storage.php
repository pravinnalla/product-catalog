<?php

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

const VISITOR_RETENTION_DAYS = 45;
const VISITOR_PUBLIC_PAGES = ['/', '/about.html', '/products.html', '/contact.html'];
const VISITOR_MMDBLOOKUP_PATH = '/bin/mmdblookup';
const VISITOR_GEOLITE2_CITY_PATH = '/usr/share/GeoIP/GeoLite2-City.mmdb';

final class VisitorStorageException extends RuntimeException
{
}

function visitor_directory(): string
{
    return app_private_root() . '/analytics';
}

function visitor_file_path(): string
{
    return visitor_directory() . '/visitors.json';
}

function visitor_lock_path(): string
{
    return app_private_root() . '/locks/visitors.lock';
}

function visitor_ensure_directory(string $directory): void
{
    if (is_link($directory)) {
        throw new VisitorStorageException('Private visitor storage is unsafe.');
    }
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new VisitorStorageException('Unable to prepare private visitor storage.');
    }
    @chmod($directory, 0700);
}

/** @return resource */
function visitor_acquire_lock()
{
    visitor_ensure_directory(visitor_directory());
    visitor_ensure_directory(dirname(visitor_lock_path()));
    if (is_link(visitor_lock_path())) {
        throw new VisitorStorageException('Private visitor lock is unsafe.');
    }
    $handle = @fopen(visitor_lock_path(), 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new VisitorStorageException('Unable to lock private visitor storage.');
    }
    @chmod(visitor_lock_path(), 0600);
    return $handle;
}

/** @param resource $handle */
function visitor_release_lock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

/** @return list<array{timestamp:string,ip:string,location:string,device:string}> */
function visitor_read_records(): array
{
    $path = visitor_file_path();
    if (!is_file($path)) return [];
    if (is_link($path) || !is_readable($path)) {
        throw new VisitorStorageException('Visitor data is unavailable.');
    }
    $contents = @file_get_contents($path);
    if ($contents === false) throw new VisitorStorageException('Visitor data could not be read.');
    try {
        $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new VisitorStorageException('Visitor data is malformed.', 0, $exception);
    }
    if (!is_array($records) || !array_is_list($records)) {
        throw new VisitorStorageException('Visitor data has an invalid structure.');
    }
    foreach ($records as $record) {
        if (!visitor_record_is_valid($record)) throw new VisitorStorageException('Visitor data has an invalid record.');
    }
    return $records;
}

function visitor_record_is_valid(mixed $record): bool
{
    return is_array($record)
        && array_keys($record) === ['timestamp', 'ip', 'location', 'device']
        && is_string($record['timestamp']) && strtotime($record['timestamp']) !== false
        && is_string($record['ip']) && strlen($record['ip']) <= 45
        && is_string($record['location']) && strlen($record['location']) <= 200
        && in_array($record['device'], ['Mobile', 'Tablet', 'Desktop', 'Bot', 'Unknown'], true);
}

function visitor_cutoff(int $now): int
{
    return $now - (VISITOR_RETENTION_DAYS * 86400);
}

/** @param list<array{timestamp:string,ip:string,location:string,device:string}> $records */
function visitor_apply_retention(array $records, int $now): array
{
    $cutoff = visitor_cutoff($now);
    return array_values(array_filter($records, static function (array $record) use ($cutoff, $now): bool {
        $timestamp = strtotime($record['timestamp']);
        return $timestamp !== false && $timestamp >= $cutoff && $timestamp <= $now;
    }));
}

function visitor_atomic_write(array $records): void
{
    try {
        $contents = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        $suffix = bin2hex(random_bytes(8));
    } catch (Throwable $exception) {
        throw new VisitorStorageException('Visitor data could not be encoded.', 0, $exception);
    }
    $temporary = visitor_file_path() . '.tmp-' . $suffix;
    $handle = @fopen($temporary, 'xb');
    if ($handle === false) throw new VisitorStorageException('Visitor data could not be written.');
    $complete = false;
    try {
        $written = 0;
        while ($written < strlen($contents)) {
            $count = fwrite($handle, substr($contents, $written));
            if ($count === false || $count === 0) throw new VisitorStorageException('Visitor data could not be written.');
            $written += $count;
        }
        if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
            throw new VisitorStorageException('Visitor data could not be synchronized.');
        }
        $complete = true;
    } finally {
        fclose($handle);
        if (!$complete) @unlink($temporary);
    }
    @chmod($temporary, 0600);
    if (!@rename($temporary, visitor_file_path())) {
        @unlink($temporary);
        throw new VisitorStorageException('Visitor data could not be replaced safely.');
    }
    @chmod(visitor_file_path(), 0600);
}

function visitor_device_from_user_agent(string $userAgent): string
{
    if ($userAgent === '') return 'Unknown';
    if (preg_match('/bot|crawler|spider|slurp|bingpreview/i', $userAgent)) return 'Bot';
    if (preg_match('/ipad|tablet|kindle|silk|playbook|android(?!.*mobile)/i', $userAgent)) return 'Tablet';
    if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile/i', $userAgent)) return 'Mobile';
    return 'Desktop';
}

function visitor_mmdb_value(string $output): ?string
{
    if (!preg_match('/^\s*"((?:[^"\\\\]|\\\\.)*)"\s+<utf8_string>\s*$/m', $output, $matches)) return null;
    $value = json_decode('"' . $matches[1] . '"');
    if (!is_string($value)) return null;
    $value = trim($value);
    return $value === '' ? null : $value;
}

/** @param null|callable(string):(?string) $commandRunner */
function visitor_geolite2_location(string $ip, ?callable $commandRunner = null, ?callable $filesAvailable = null): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return 'Unknown';

    $filesAvailable ??= static fn(): bool => is_executable(VISITOR_MMDBLOOKUP_PATH)
        && is_file(VISITOR_GEOLITE2_CITY_PATH)
        && is_readable(VISITOR_GEOLITE2_CITY_PATH);
    if (!$filesAvailable()) return 'Unknown';

    if ($commandRunner === null) {
        if (!function_exists('shell_exec')) return 'Unknown';
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) return 'Unknown';
        $commandRunner = static fn(string $command): ?string => shell_exec($command);
    }

    $fields = [
        ['city', 'names', 'en'],
        ['subdivisions', '0', 'names', 'en'],
        ['country', 'names', 'en'],
    ];
    $parts = [];
    foreach ($fields as $fieldPath) {
        $command = VISITOR_MMDBLOOKUP_PATH
            . ' --file ' . escapeshellarg(VISITOR_GEOLITE2_CITY_PATH)
            . ' --ip ' . escapeshellarg($ip)
            . ' ' . implode(' ', array_map('escapeshellarg', $fieldPath))
            . ' 2>/dev/null';
        try {
            $output = $commandRunner($command);
        } catch (Throwable) {
            $output = null;
        }
        if (is_string($output)) {
            $value = visitor_mmdb_value($output);
            if ($value !== null) $parts[] = $value;
        }
    }
    return $parts === [] ? 'Unknown' : implode(', ', $parts);
}

/** @param null|callable(string):(?string) $commandRunner */
function visitor_location_from_server(array $server, ?callable $commandRunner = null, ?callable $filesAvailable = null): string
{
    $groups = [
        ['GEOIP_CITY', 'GEOIP_REGION_NAME', 'GEOIP_COUNTRY_NAME'],
        ['HTTP_X_APPENGINE_CITY', 'HTTP_X_APPENGINE_REGION', 'HTTP_X_APPENGINE_COUNTRY'],
    ];
    foreach ($groups as $keys) {
        $parts = [];
        foreach ($keys as $key) {
            $value = trim((string) ($server[$key] ?? ''));
            if ($value !== '') $parts[] = str_replace('_', ' ', $value);
        }
        if ($parts !== []) return implode(', ', $parts);
    }
    return visitor_geolite2_location(trim((string) ($server['REMOTE_ADDR'] ?? '')), $commandRunner, $filesAvailable);
}

function visitor_log(string $page, array $server, ?int $now = null): bool
{
    if (!in_array($page, VISITOR_PUBLIC_PAGES, true)) return false;
    $ip = trim((string) ($server['REMOTE_ADDR'] ?? ''));
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) $ip = 'Unknown';
    $now ??= time();
    $record = [
        'timestamp' => gmdate(DATE_ATOM, $now),
        'ip' => $ip,
        'location' => visitor_location_from_server($server),
        'device' => visitor_device_from_user_agent((string) ($server['HTTP_USER_AGENT'] ?? '')),
    ];
    $lock = visitor_acquire_lock();
    try {
        $records = visitor_apply_retention(visitor_read_records(), $now);
        $records[] = $record;
        visitor_atomic_write($records);
    } finally {
        visitor_release_lock($lock);
    }
    return true;
}

/** @return list<array{timestamp:string,ip:string,location:string,device:string}> */
function visitor_recent_records(?int $now = null): array
{
    $now ??= time();
    $lock = visitor_acquire_lock();
    try {
        $records = visitor_read_records();
        $recent = visitor_apply_retention($records, $now);
        if (count($recent) !== count($records)) visitor_atomic_write($recent);
    } finally {
        visitor_release_lock($lock);
    }
    usort($recent, static fn(array $left, array $right): int => strcmp($right['timestamp'], $left['timestamp']));
    return $recent;
}
