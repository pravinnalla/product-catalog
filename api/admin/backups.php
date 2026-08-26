<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/backup-admin-service.php';

auth_apply_cors(['GET', 'POST', 'DELETE']);
header('Allow: GET, POST, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
    json_response(['success' => false, 'message' => 'Method not allowed.'], 405);
}
require_admin_auth();

function backup_api_json(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        throw new BackupAdminException('JSON request required.', 415);
    }
    $limit = 4096;
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) {
        throw new BackupAdminException('Request is too large.', 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $limit + 1);
    if (!is_string($raw) || strlen($raw) > $limit) throw new BackupAdminException('Request is too large.', 413);
    try { $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new BackupAdminException('Invalid request data.', 400); }
    if (!is_array($decoded) || array_is_list($decoded)) throw new BackupAdminException('Invalid request data.', 400);
    return $decoded;
}

function backup_api_fields(array $input, array $required, array $allowed): void
{
    if (array_diff(array_keys($input), $allowed) !== []) throw new BackupAdminException('Request contains unsupported fields.', 400);
    foreach ($required as $field) {
        if (!array_key_exists($field, $input)) throw new BackupAdminException('Request is missing required fields.', 400);
    }
}

function backup_api_string(array $input, string $field, int $limit = 160): string
{
    $value = $input[$field] ?? null;
    if (!is_string($value) || $value === '' || strlen($value) > $limit) {
        throw new BackupAdminException('Request contains invalid field values.', 400);
    }
    return $value;
}

try {
    if ($method === 'GET') {
        $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'list';
        $domain = is_string($_GET['domain'] ?? null) ? $_GET['domain'] : 'catalog';
        if ($action === 'download') {
            if (array_diff(array_keys($_GET), ['action', 'domain', 'id']) !== []) throw new BackupAdminException('Invalid download request.', 400);
            $id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
            $download = backup_admin_create_download($domain, $id);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $download['filename'] . '"');
            header('Content-Length: ' . $download['size']);
            header('Cache-Control: no-store');
            try { readfile($download['path']); } finally { @unlink($download['path']); }
            exit;
        }
        if ($action !== 'list' || array_diff(array_keys($_GET), ['action', 'domain']) !== []) {
            throw new BackupAdminException('Invalid backup request.', 400);
        }
        json_response([
            'success' => true, 'domains' => backup_admin_domains(),
            'selectedDomain' => $domain, 'items' => backup_admin_list($domain),
        ]);
    }

    if (!csrf_verify_request()) json_response(['success' => false, 'message' => 'Invalid security token.'], 403);
    $input = backup_api_json();
    if ($method === 'DELETE') {
        backup_api_fields($input, ['domain', 'type', 'id', 'confirmation'], ['domain', 'type', 'id', 'confirmation', 'dataset']);
        $deleted = backup_admin_delete(
            backup_api_string($input, 'domain', 40), backup_api_string($input, 'type', 40),
            backup_api_string($input, 'id'), backup_api_string($input, 'confirmation', 20),
            isset($input['dataset']) ? backup_api_string($input, 'dataset', 40) : null
        );
        json_response(['success' => true, 'deleted' => $deleted]);
    }
    $action = backup_api_string($input, 'action', 40);
    $domain = backup_api_string($input, 'domain', 40);
    if ($action === 'create-snapshot') {
        backup_api_fields($input, ['action', 'domain'], ['action', 'domain']);
        json_response(['success' => true, 'message' => 'Catalogue snapshot created.', 'item' => backup_admin_create_snapshot($domain)], 201);
    }
    if ($action === 'dry-run') {
        backup_api_fields($input, ['action', 'domain', 'type', 'id'], ['action', 'domain', 'type', 'id', 'dataset']);
        $result = backup_admin_dry_run(
            $domain, backup_api_string($input, 'type', 40), backup_api_string($input, 'id'),
            isset($input['dataset']) ? backup_api_string($input, 'dataset', 40) : null
        );
        json_response(['success' => true, 'message' => 'DRY RUN PASSED', 'result' => $result]);
    }
    if ($action === 'restore') {
        backup_api_fields($input, ['action', 'domain', 'type', 'id', 'confirmation'], ['action', 'domain', 'type', 'id', 'confirmation', 'dataset']);
        $result = backup_admin_restore(
            $domain, backup_api_string($input, 'type', 40), backup_api_string($input, 'id'),
            backup_api_string($input, 'confirmation', 20),
            isset($input['dataset']) ? backup_api_string($input, 'dataset', 40) : null
        );
        json_response(['success' => true, 'message' => 'Catalogue restore completed.', 'result' => $result]);
    }
    throw new BackupAdminException('Unsupported backup action.', 400);
} catch (BackupAdminException $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], $exception->status);
} catch (CatalogStorageException|BackupDomainException $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable) {
    json_response(['success' => false, 'message' => 'Unable to complete the backup request.'], 500);
}
