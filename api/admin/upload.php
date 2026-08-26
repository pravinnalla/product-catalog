<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload.php';

auth_apply_cors(['POST', 'DELETE']);
header('Allow: POST, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['POST', 'DELETE'], true)) {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
require_admin_auth();
if (!csrf_verify_request()) json_response(['success' => false, 'message' => 'Invalid security token.'], 403);

try {
    if ($method === 'POST') {
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data;') !== 0) {
            throw new UploadValidationException('Multipart form data is required.', 415);
        }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > (5 * 1024 * 1024 + 65536)) {
            throw new UploadValidationException('The selected image is too large.', 413);
        }
        $kind = is_string($_POST['kind'] ?? null) ? $_POST['kind'] : '';
        upload_spec($kind);
        if (count($_FILES) !== 1 || !isset($_FILES['image']) || !is_array($_FILES['image'])) {
            throw new UploadValidationException('Exactly one image file is required.');
        }
        json_response(['success' => true, 'data' => upload_store($_FILES['image'], $kind)], 201);
    }

    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        throw new UploadValidationException('JSON request required.', 415);
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 2048) throw new UploadValidationException('Request is too large.', 413);
    $raw = file_get_contents('php://input', false, null, 0, 2049);
    $input = json_decode(is_string($raw) ? $raw : '', true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($input) || count($input) !== 2 || !isset($input['kind'], $input['filename'])
        || !is_string($input['kind']) || !is_string($input['filename'])) {
        throw new UploadValidationException('Invalid cleanup request.');
    }
    $deleted = upload_delete_if_unreferenced($input['kind'], $input['filename']);
    json_response(['success' => true, 'deleted' => $deleted]);
} catch (UploadValidationException $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], $exception->status);
} catch (JsonException) {
    json_response(['success' => false, 'message' => 'Invalid cleanup request.'], 400);
} catch (Throwable) {
    json_response(['success' => false, 'message' => 'Unable to process the media request.'], 500);
}
