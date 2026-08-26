<?php

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

function auth_apply_cors(array $methods): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && app_origin_is_allowed($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: ' . implode(', ', array_unique([...$methods, 'OPTIONS'])));
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Max-Age: 600');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        if ($origin !== '' && !app_origin_is_allowed($origin)) {
            http_response_code(403);
        } else {
            http_response_code(204);
        }
        exit;
    }
}

function json_response(array $body, int $status = 200): never
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    http_response_code($status);

    try {
        echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    } catch (JsonException) {
        http_response_code(500);
        echo '{"success":false,"message":"Unable to create response."}';
    }
    exit;
}

function require_request_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
    }
}
