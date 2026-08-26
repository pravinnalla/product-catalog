<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate-limit.php';

auth_apply_cors(['POST']);
require_request_method('POST');

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    json_response(['success' => false, 'message' => 'JSON request required.'], 415);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
    json_response(['success' => false, 'message' => 'Request is too large.'], 413);
}

try {
    $data = json_decode((string) file_get_contents('php://input'), false, 32, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    json_response(['success' => false, 'message' => 'Invalid request data.'], 400);
}

$fields = is_object($data) ? get_object_vars($data) : [];
$password = is_string($fields['password'] ?? null) ? $fields['password'] : '';
$failure = ['success' => false, 'authenticated' => false, 'message' => 'Invalid credentials.'];

if (!is_object($data) || array_keys($fields) !== ['password'] || $password === '' || strlen($password) > 128) {
    json_response($failure, 401);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ipKey = 'login:' . $ip;
$accountKey = 'login-account:' . hash('sha256', 'single-admin-login');
try {
    if (auth_rate_is_limited($ipKey, $accountKey)) {
        json_response($failure, 429);
    }

    $credentials = load_admin_credentials();
    if ($credentials === null) {
        json_response(['success' => false, 'message' => 'Authentication is unavailable.'], 503);
    }

    if (!password_verify($password, $credentials['password_hash'])) {
        auth_rate_record_failure($ipKey, $accountKey);
        json_response($failure, 401);
    }

    admin_session_start();
    admin_session_regenerate();
    $now = time();
    $_SESSION = [
        'admin_authenticated' => true,
        'admin_email' => $credentials['admin_email'],
        'auth_time' => $now,
        'last_activity' => $now,
        'credential_version' => $credentials['credential_version'],
    ];
    $csrfToken = csrf_rotate_token();
    auth_rate_reset($ipKey, $accountKey);

    json_response([
        'success' => true,
        'authenticated' => true,
        'csrfToken' => $csrfToken,
    ]);
} catch (Throwable) {
    json_response(['success' => false, 'message' => 'Authentication is temporarily unavailable.'], 503);
}
