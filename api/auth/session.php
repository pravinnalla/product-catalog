<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

auth_apply_cors(['GET']);
require_request_method('GET');

if (!admin_session_is_authenticated()) {
    json_response(['success' => true, 'authenticated' => false]);
}

json_response([
    'success' => true,
    'authenticated' => true,
    'csrfToken' => csrf_token(),
]);
