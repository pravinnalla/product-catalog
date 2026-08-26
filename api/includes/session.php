<?php

declare(strict_types=1);

const ADMIN_SESSION_NAME = 'laxmikant_admin_session';
const ADMIN_SESSION_IDLE_TIMEOUT = 1800;
const ADMIN_SESSION_ABSOLUTE_TIMEOUT = 28800;

function admin_request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
        || (getenv('TRUST_PROXY_HTTPS') === '1'
            && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}

function admin_session_cookie_options(): array
{
    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => admin_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', admin_request_is_https() ? '1' : '0');
    session_name(ADMIN_SESSION_NAME);
    session_set_cookie_params(admin_session_cookie_options());
    session_start();
}

function admin_session_regenerate(): void
{
    admin_session_start();
    session_regenerate_id(true);
}

function admin_session_destroy(): void
{
    admin_session_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $options = admin_session_cookie_options();
        unset($options['lifetime']);
        setcookie(ADMIN_SESSION_NAME, '', [
            ...$options,
            'expires' => time() - 42000,
        ]);
    }

    session_destroy();
}
