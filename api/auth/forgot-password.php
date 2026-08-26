<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate-limit.php';
require_once __DIR__ . '/../includes/mailer.php';

auth_apply_cors(['POST']);
require_request_method('POST');
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) json_response(['success' => false, 'message' => 'JSON request required.'], 415);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 2048) json_response(['success' => false, 'message' => 'Request is too large.'], 413);
try { $data = json_decode((string) file_get_contents('php://input'), false, 8, JSON_THROW_ON_ERROR); } catch (JsonException) { $data = null; }
$generic = ['success' => true, 'message' => 'If password recovery is available, a reset link has been sent to the registered administrator email.'];
if (!is_object($data)) json_response(['success' => false, 'message' => 'Invalid request data.'], 400);
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
try {
    $config = password_recovery_config();
    $recoveryKey = hash('sha256', normalize_admin_email($config['recovery_email']));
    $ipKey = 'forgot:' . $ip;
    $accountKey = 'forgot-account:' . $recoveryKey;
    if (auth_rate_is_limited($ipKey, $accountKey)) {
        json_response(['success' => false, 'message' => 'Too many requests. Please try again later.'], 429);
    }
    auth_rate_record_failure($ipKey, $accountKey);
    $credentials = load_admin_credentials();
    if ($credentials === null) json_response($generic);
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    auth_state_with_lock(function () use ($token, $credentials): void {
        $now = time();
        auth_write_reset_state([
            'token_hash' => hash('sha256', $token),
            'admin_email_hash' => hash('sha256', $credentials['admin_email']),
            'created_at' => $now,
            'expires_at' => $now + 1800,
        ]);
    });
    try { send_password_reset_email($token, $config); } catch (Throwable) { /* Generic public response. */ }
    $token = '';
    json_response($generic);
} catch (Throwable) {
    json_response($generic);
}
