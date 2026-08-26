<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

auth_apply_cors(['POST']);
require_request_method('POST');
require_admin_auth();

if (!csrf_verify_request()) {
    json_response(['success' => false, 'message' => 'Invalid security token.'], 403);
}

csrf_rotate_token();
admin_session_destroy();
json_response(['success' => true, 'authenticated' => false]);
