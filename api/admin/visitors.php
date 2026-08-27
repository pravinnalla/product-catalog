<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/visitor-storage.php';

auth_apply_cors(['GET']);
require_request_method('GET');
require_admin_auth();

try {
    $page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
    $perPage = 25;
    $records = visitor_recent_records();
    $total = count($records);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    json_response([
        'success' => true,
        'items' => array_slice($records, ($page - 1) * $perPage, $perPage),
        'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages],
    ]);
} catch (Throwable) {
    json_response(['success' => false, 'message' => 'Visitor reports are temporarily unavailable.'], 500);
}
