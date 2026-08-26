<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/paths.php';

function admin_credentials_path(): string
{
    return app_private_root() . '/admin.php';
}

function normalize_admin_email(string $email): string
{
    return strtolower(trim($email));
}

function load_admin_credentials(): ?array
{
    $path = admin_credentials_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $credentials = require $path;
    if (!is_array($credentials)
        || !isset($credentials['admin_email'], $credentials['password_hash'], $credentials['credential_version'])
        || !is_string($credentials['admin_email'])
        || filter_var($credentials['admin_email'], FILTER_VALIDATE_EMAIL) === false
        || !is_string($credentials['password_hash'])
        || password_get_info($credentials['password_hash'])['algo'] === null
        || !is_int($credentials['credential_version'])
        || $credentials['credential_version'] < 1
    ) {
        return null;
    }

    $credentials['admin_email'] = normalize_admin_email($credentials['admin_email']);
    return $credentials;
}

function admin_session_is_authenticated(?array $credentials = null): bool
{
    admin_session_start();
    if (($_SESSION['admin_authenticated'] ?? false) !== true) {
        return false;
    }

    $credentials ??= load_admin_credentials();
    $now = time();
    $authTime = $_SESSION['auth_time'] ?? 0;
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    $version = $_SESSION['credential_version'] ?? 0;

    $valid = $credentials !== null
        && is_int($authTime) && is_int($lastActivity) && is_int($version)
        && ($now - $lastActivity) <= ADMIN_SESSION_IDLE_TIMEOUT
        && ($now - $authTime) <= ADMIN_SESSION_ABSOLUTE_TIMEOUT
        && $version === $credentials['credential_version'];

    if (!$valid) {
        admin_session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = $now;
    return true;
}

function require_admin_auth(): array
{
    $credentials = load_admin_credentials();
    if (!admin_session_is_authenticated($credentials)) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    return $credentials;
}

function admin_password_is_valid(string $password): bool
{
    return strlen($password) >= 12
        && strlen($password) <= 128
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/\d/', $password) === 1
        && preg_match('/[^a-zA-Z\d]/', $password) === 1;
}

function auth_state_lock_path(): string
{
    return dirname(admin_credentials_path()) . '/locks/auth-state.lock';
}

function auth_state_with_lock(callable $callback): mixed
{
    $directory = dirname(auth_state_lock_path());
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to prepare authentication state.');
    }
    $handle = @fopen(auth_state_lock_path(), 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Unable to lock authentication state.');
    }
    @chmod(auth_state_lock_path(), 0600);
    try {
        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function auth_atomic_write(string $path, string $contents): void
{
    $temporary = tempnam(dirname($path), 'auth-');
    if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
        if (is_string($temporary)) @unlink($temporary);
        throw new RuntimeException('Unable to update authentication state.');
    }
    @chmod($temporary, 0600);
    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to update authentication state.');
    }
    @chmod($path, 0600);
}

function auth_write_credentials(array $credentials): void
{
    $allowed = [
        'admin_email' => normalize_admin_email((string) $credentials['admin_email']),
        'password_hash' => (string) $credentials['password_hash'],
        'credential_version' => (int) $credentials['credential_version'],
        'password_updated_at' => (string) ($credentials['password_updated_at'] ?? gmdate(DATE_ATOM)),
    ];
    $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($allowed, true) . ";\n";
    auth_atomic_write(admin_credentials_path(), $contents);
}

function password_reset_path(): string
{
    return dirname(admin_credentials_path()) . '/password-reset.json';
}

function auth_read_reset_state(): ?array
{
    if (!is_file(password_reset_path())) return null;
    $decoded = json_decode((string) @file_get_contents(password_reset_path()), true);
    if (!is_array($decoded)
        || array_keys($decoded) !== ['token_hash', 'admin_email_hash', 'created_at', 'expires_at']
        || !is_string($decoded['token_hash']) || strlen($decoded['token_hash']) !== 64
        || !is_string($decoded['admin_email_hash']) || strlen($decoded['admin_email_hash']) !== 64
        || !is_int($decoded['created_at']) || !is_int($decoded['expires_at'])) {
        return null;
    }
    return $decoded;
}

function auth_write_reset_state(array $state): void
{
    auth_atomic_write(password_reset_path(), json_encode($state, JSON_THROW_ON_ERROR) . PHP_EOL);
}

function auth_delete_reset_state(): void
{
    if (is_file(password_reset_path()) && !@unlink(password_reset_path())) {
        throw new RuntimeException('Unable to invalidate password reset state.');
    }
}
