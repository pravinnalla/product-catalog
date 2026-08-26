<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/rate-limit.php';

auth_apply_cors(['POST']);
require_request_method('POST');
$credentials = require_admin_auth();
if (!csrf_verify_request()) json_response(['success' => false, 'message' => 'Invalid security token.'], 403);
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) json_response(['success' => false, 'message' => 'JSON request required.'], 415);
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) json_response(['success' => false, 'message' => 'Request is too large.'], 413);

try { $data = json_decode((string) file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR); } catch (JsonException) { $data = null; }
$current = is_array($data) && is_string($data['currentPassword'] ?? null) ? $data['currentPassword'] : '';
$new = is_array($data) && is_string($data['newPassword'] ?? null) ? $data['newPassword'] : '';
$confirm = is_array($data) && is_string($data['confirmPassword'] ?? null) ? $data['confirmPassword'] : '';
if ($current === '' || !admin_password_is_valid($new) || !hash_equals($new, $confirm) || hash_equals($current, $new)) {
    json_response(['success' => false, 'message' => 'Unable to change password. Check the password requirements.'], 400);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$email = $credentials['admin_email'];
try {
    if (auth_rate_is_limited('change:' . $ip, 'change:' . $email)) json_response(['success' => false, 'message' => 'Unable to change password.'], 429);
    $changed = auth_state_with_lock(function () use ($current, $new): bool {
        $fresh = load_admin_credentials();
        if ($fresh === null || !password_verify($current, $fresh['password_hash'])) return false;
        $hash = password_hash($new, PASSWORD_DEFAULT);
        if ($hash === false) throw new RuntimeException('Unable to hash password.');
        $fresh['password_hash'] = $hash;
        $fresh['credential_version']++;
        $fresh['password_updated_at'] = gmdate(DATE_ATOM);
        auth_delete_reset_state();
        auth_write_credentials($fresh);
        return true;
    });
    if (!$changed) {
        auth_rate_record_failure('change:' . $ip, 'change:' . $email);
        json_response(['success' => false, 'message' => 'Unable to change password.'], 401);
    }
    auth_rate_reset('change:' . $ip, 'change:' . $email);
    admin_session_destroy();
    json_response(['success' => true, 'authenticated' => false, 'message' => 'Password changed. Sign in again.']);
} catch (Throwable) {
    json_response(['success' => false, 'message' => 'Unable to change password.'], 503);
}
