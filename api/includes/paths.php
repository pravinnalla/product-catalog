<?php

declare(strict_types=1);

function app_environment_value(string $name): ?string
{
    $value = getenv($name);
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    return $value === '' ? null : $value;
}

function app_private_root(): string
{
    return rtrim(
        app_environment_value('APP_PRIVATE_ROOT') ?? dirname(__DIR__, 2) . '/private',
        DIRECTORY_SEPARATOR
    );
}

function app_public_root(): string
{
    return rtrim(
        app_environment_value('APP_PUBLIC_ROOT') ?? dirname(__DIR__, 2) . '/public',
        DIRECTORY_SEPARATOR
    );
}

function app_upload_root(): string
{
    return rtrim(
        app_environment_value('RUNTIME_MEDIA_ROOT') ?? app_public_root() . '/uploads',
        DIRECTORY_SEPARATOR
    );
}

function app_upload_url_prefix(): string
{
    return '/' . trim(app_environment_value('RUNTIME_MEDIA_URL_PREFIX') ?? '/uploads', '/');
}

function app_password_reset_base_url(): string
{
    return rtrim(
        app_environment_value('PASSWORD_RESET_BASE_URL')
            ?? 'http://localhost:5173/admin-reset-password.html',
        '/'
    );
}

function app_allowed_browser_origins(): array
{
    $origins = ['http://localhost:5173', 'https://laxmikanttraders.in'];
    $configured = app_environment_value('APP_ORIGIN');
    if ($configured !== null) {
        $origins[] = rtrim($configured, '/');
    }

    return array_values(array_unique($origins));
}

function app_origin_is_allowed(string $origin): bool
{
    return in_array(rtrim($origin, '/'), app_allowed_browser_origins(), true);
}

function app_enquiry_rate_limit_directory(): string
{
    return app_private_root() . '/rate-limit/enquiry';
}
