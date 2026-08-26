<?php

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

const AUTH_RATE_WINDOW = 900;
const AUTH_RATE_MAX_FAILURES = 5;
const AUTH_RATE_INITIAL_BLOCK = 30;
const AUTH_RATE_MAX_BLOCK = 900;

function auth_rate_directory(): string
{
    return app_private_root() . '/locks/auth-rate-limit';
}

function auth_rate_key(string $type, string $value): string
{
    return $type . '-' . hash('sha256', $value) . '.json';
}

function auth_rate_prepare(): void
{
    $directory = auth_rate_directory();
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to prepare authentication rate limiting.');
    }
    @chmod($directory, 0700);
}

function auth_rate_with_lock(callable $callback): mixed
{
    auth_rate_prepare();
    $lockPath = dirname(auth_rate_directory()) . '/auth.lock';
    $handle = @fopen($lockPath, 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Unable to lock authentication rate limiting.');
    }
    @chmod($lockPath, 0600);
    try {
        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function auth_rate_read(string $path): array
{
    $decoded = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
    return is_array($decoded) ? $decoded : ['failures' => [], 'level' => 0, 'blocked_until' => 0];
}

function auth_rate_write(string $path, array $state): void
{
    $temporary = tempnam(auth_rate_directory(), 'rate-');
    if ($temporary === false) throw new RuntimeException('Unable to update rate limiting.');
    file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX);
    @chmod($temporary, 0600);
    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to update rate limiting.');
    }
}

function auth_rate_paths(string $ip, string $email): array
{
    return [
        auth_rate_directory() . '/' . auth_rate_key('ip', $ip),
        auth_rate_directory() . '/' . auth_rate_key('email', $email),
    ];
}

function auth_rate_is_limited(string $ip, string $email): bool
{
    return auth_rate_with_lock(function () use ($ip, $email): bool {
        $now = time();
        foreach (auth_rate_paths($ip, $email) as $path) {
            if ((int) (auth_rate_read($path)['blocked_until'] ?? 0) > $now) return true;
        }
        return false;
    });
}

function auth_rate_record_failure(string $ip, string $email): void
{
    auth_rate_with_lock(function () use ($ip, $email): void {
        $now = time();
        foreach (auth_rate_paths($ip, $email) as $path) {
            $state = auth_rate_read($path);
            $failures = array_values(array_filter($state['failures'] ?? [], fn ($at) => is_int($at) && $at > $now - AUTH_RATE_WINDOW));
            $failures[] = $now;
            $level = (int) ($state['level'] ?? 0);
            $blockedUntil = (int) ($state['blocked_until'] ?? 0);
            if (count($failures) >= AUTH_RATE_MAX_FAILURES) {
                $level++;
                $blockedUntil = $now + min(AUTH_RATE_MAX_BLOCK, AUTH_RATE_INITIAL_BLOCK * (2 ** min($level - 1, 5)));
                $failures = [];
            }
            auth_rate_write($path, ['failures' => $failures, 'level' => $level, 'blocked_until' => $blockedUntil, 'updated_at' => $now]);
        }
        auth_rate_cleanup($now);
    });
}

function auth_rate_reset(string $ip, string $email): void
{
    auth_rate_with_lock(function () use ($ip, $email): void {
        foreach (auth_rate_paths($ip, $email) as $path) {
            if (is_file($path)) @unlink($path);
        }
    });
}

function auth_rate_cleanup(int $now): void
{
    $checked = 0;
    foreach (new DirectoryIterator(auth_rate_directory()) as $file) {
        if ($checked++ >= 20) break;
        if ($file->isFile() && $file->getExtension() === 'json' && $file->getMTime() < $now - 86400) {
            @unlink($file->getPathname());
        }
    }
}
