<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/response.php';
require_once __DIR__ . '/includes/visitor-storage.php';

require_request_method('POST');

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin !== '' && !app_origin_is_allowed($origin)) {
    http_response_code(204);
    exit;
}

try {
    $body = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
    $page = is_array($body) ? (string) ($body['page'] ?? '') : '';
    visitor_log($page, $_SERVER);
} catch (Throwable) {
    // Logging is best-effort and must never break a public page or expose internals.
}

http_response_code(204);
