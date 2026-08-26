<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate-limit.php';

auth_apply_cors(['POST']);
require_request_method('POST');
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) json_response(['success' => false, 'message' => 'JSON request required.'], 415);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) json_response(['success' => false, 'message' => 'Request is too large.'], 413);
try { $data = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR); } catch (JsonException) { $data = null; }
$token = is_array($data) && is_string($data['token'] ?? null) ? $data['token'] : '';
$new = is_array($data) && is_string($data['newPassword'] ?? null) ? $data['newPassword'] : '';
$confirm = is_array($data) && is_string($data['confirmPassword'] ?? null) ? $data['confirmPassword'] : '';
$failure = ['success' => false, 'message' => 'The reset link is invalid or expired.'];
if (preg_match('/^[A-Za-z0-9_-]{43}$/', $token) !== 1 || !admin_password_is_valid($new) || !hash_equals($new, $confirm)) json_response($failure, 400);
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$tokenKey = hash('sha256', $token);
try {
    if (auth_rate_is_limited('reset:' . $ip, 'reset:' . $tokenKey)) json_response($failure, 429);
    $reset = auth_state_with_lock(function () use ($tokenKey, $new): bool {
        $state = auth_read_reset_state();
        $credentials = load_admin_credentials();
        if ($state === null || $credentials === null || $state['expires_at'] < time()
            || !hash_equals($state['token_hash'], $tokenKey)
            || !hash_equals($state['admin_email_hash'], hash('sha256', $credentials['admin_email']))) {
            if ($state !== null && $state['expires_at'] < time()) auth_delete_reset_state();
            return false;
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        if ($hash === false) throw new RuntimeException('Unable to hash password.');
        $credentials['password_hash'] = $hash;
        $credentials['credential_version']++;
        $credentials['password_updated_at'] = gmdate(DATE_ATOM);
        auth_delete_reset_state();
        auth_write_credentials($credentials);
        return true;
    });
    if (!$reset) {
        auth_rate_record_failure('reset:' . $ip, 'reset:' . $tokenKey);
        json_response($failure, 400);
    }
    auth_rate_reset('reset:' . $ip, 'reset:' . $tokenKey);
    json_response(['success' => true, 'message' => 'Password reset. You can now sign in.']);
} catch (Throwable) {
    json_response(['success' => false, 'message' => 'Unable to reset password.'], 503);
}
