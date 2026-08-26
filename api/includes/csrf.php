<?php

declare(strict_types=1);

const ADMIN_CSRF_HEADER = 'HTTP_X_CSRF_TOKEN';

function csrf_rotate_token(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function csrf_token(): string
{
    $token = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && strlen($token) === 64 ? $token : csrf_rotate_token();
}

function csrf_verify_request(): bool
{
    $stored = $_SESSION['csrf_token'] ?? '';
    $provided = $_SERVER[ADMIN_CSRF_HEADER] ?? '';
    return is_string($stored) && $stored !== '' && is_string($provided)
        && hash_equals($stored, $provided);
}
