<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog-storage.php';

function catalog_apply_public_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=0, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Origin');

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && app_origin_is_allowed($origin)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
}

function catalog_json_error(string $message, int $status): never
{
    catalog_apply_public_headers();
    http_response_code($status);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        try {
            echo json_encode(
                ['success' => false, 'message' => $message],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            // The fixed ASCII error response cannot normally fail to encode.
        }
    }
    exit;
}

function catalog_request_matches_etag(string $etag): bool
{
    $header = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if (!is_string($header) || $header === '') {
        return false;
    }

    foreach (explode(',', $header) as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '*' || $candidate === $etag || $candidate === 'W/' . $etag) {
            return true;
        }
    }
    return false;
}

function serve_catalog_dataset(string $dataset): never
{
    catalog_apply_public_headers();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Max-Age: 600');
        http_response_code(204);
        exit;
    }

    if ($method !== 'GET' && $method !== 'HEAD') {
        header('Allow: GET, HEAD, OPTIONS');
        catalog_json_error('Method not allowed.', 405);
    }

    try {
        $records = catalog_read_dataset($dataset);
        $body = json_encode(
            ['success' => true, 'data' => $records],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    } catch (Throwable) {
        catalog_json_error('Catalogue data is temporarily unavailable.', 500);
    }

    $etag = '"' . hash('sha256', $body) . '"';
    header('ETag: ' . $etag);

    if (catalog_request_matches_etag($etag)) {
        http_response_code(304);
        exit;
    }

    http_response_code(200);
    if ($method === 'GET') {
        echo $body;
    }
    exit;
}
