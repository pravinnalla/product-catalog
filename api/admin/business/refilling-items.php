<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/business-storage.php';

final class RefillingItemRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400) { parent::__construct($message); }
}

function refilling_item_response(array $body, int $status = 200): never
{
    header('X-Content-Type-Options: nosniff');
    json_response($body, $status);
}

function refilling_item_read_json(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) throw new RefillingItemRequestException('JSON request required.', 415);
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) throw new RefillingItemRequestException('Request is too large.', 413);
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if ($raw === false || strlen($raw) > 8192) throw new RefillingItemRequestException('Request is too large.', 413);
    try { $decoded = json_decode($raw, false, 16, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new RefillingItemRequestException('Invalid request data.'); }
    if (!is_object($decoded)) throw new RefillingItemRequestException('Invalid request data.');
    return get_object_vars($decoded);
}

function refilling_item_validate(array $input, bool $create): array
{
    $allowed = ['name', 'isActive'];
    if (array_diff(array_keys($input), $allowed) !== []) throw new RefillingItemRequestException('Request contains unsupported fields.');
    if ($create && !array_key_exists('name', $input)) throw new RefillingItemRequestException('name is required.');
    $result = [];
    if (array_key_exists('name', $input)) {
        if (!is_string($input['name'])) throw new RefillingItemRequestException('name is required.');
        $name = trim($input['name']); $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($name === '' || $length > 160) throw new RefillingItemRequestException('Invalid name.');
        $result['name'] = $name;
    }
    if (array_key_exists('isActive', $input) && !is_bool($input['isActive'])) throw new RefillingItemRequestException('Active status must be true or false.');
    if (array_key_exists('isActive', $input) || $create) $result['isActive'] = $input['isActive'] ?? true;
    if (!$create && $result === []) throw new RefillingItemRequestException('No update fields were provided.');
    return $result;
}

function refilling_item_assert_unique(array $records, string $name, ?string $excludeId = null): void
{
    $normalized = business_normalize_text($name);
    foreach ($records as $record) {
        if (($record['id'] ?? null) === $excludeId) continue;
        if (is_string($record['name'] ?? null) && business_normalize_text($record['name']) === $normalized) {
            throw new RefillingItemRequestException('A refilling item with that name already exists.', 409);
        }
    }
}

function refilling_item_assert_dataset(array $records): void
{
    $expected = ['id', 'name', 'isActive']; $ids = [];
    foreach ($records as $record) {
        if (!is_array($record) || array_keys($record) !== $expected
            || !is_string($record['id']) || preg_match('/^RFI\d{4,}$/D', $record['id']) !== 1
            || isset($ids[$record['id']]) || !is_string($record['name']) || trim($record['name']) === ''
            || !is_bool($record['isActive'])) {
            throw new BusinessStorageException('The refilling-items business dataset failed validation.');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($record['name'], 'UTF-8') : strlen($record['name']);
        if ($length > 160) throw new BusinessStorageException('The refilling-items business dataset failed validation.');
        $ids[$record['id']] = true;
    }
}

auth_apply_cors(['GET', 'POST', 'PATCH']);
header('Allow: GET, POST, PATCH, OPTIONS');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PATCH'], true)) refilling_item_response(['success' => false, 'message' => 'Method not allowed.'], 405);
require_admin_auth();

try {
    if ($method === 'GET') {
        $records = business_read_dataset('refilling-items'); refilling_item_assert_dataset($records); $id = $_GET['id'] ?? null;
        if ($id === null || $id === '') refilling_item_response(['success' => true, 'data' => $records]);
        if (!is_string($id) || preg_match('/^RFI\d{4,}$/D', $id) !== 1) throw new RefillingItemRequestException('Invalid refilling item ID.');
        $index = business_find_record_index($records, $id);
        if ($index === null) throw new RefillingItemRequestException('Refilling item not found.', 404);
        refilling_item_response(['success' => true, 'data' => $records[$index]]);
    }
    if (!csrf_verify_request()) refilling_item_response(['success' => false, 'message' => 'Invalid security token.'], 403);
    $input = refilling_item_read_json();
    $result = business_mutate_dataset('refilling-items', function (array &$records) use ($method, $input): array {
        refilling_item_assert_dataset($records);
        if ($method === 'POST') {
            if (array_key_exists('id', $input)) throw new RefillingItemRequestException('IDs are server managed.');
            $data = refilling_item_validate($input, true); refilling_item_assert_unique($records, $data['name']);
            $record = ['id' => business_next_record_id($records, 'RFI'), 'name' => $data['name'], 'isActive' => $data['isActive']];
            $records[] = $record; return $record;
        }
        $id = $input['id'] ?? null;
        if (!is_string($id) || preg_match('/^RFI\d{4,}$/D', $id) !== 1) throw new RefillingItemRequestException('Invalid refilling item ID.');
        $data = $input; unset($data['id']); $changes = refilling_item_validate($data, false);
        $index = business_find_record_index($records, $id);
        if ($index === null) throw new RefillingItemRequestException('Refilling item not found.', 404);
        $updated = [...$records[$index], ...$changes]; refilling_item_assert_unique($records, $updated['name'], $id);
        $records[$index] = $updated; return $updated;
    });
    refilling_item_response(['success' => true, 'data' => $result], $method === 'POST' ? 201 : 200);
} catch (RefillingItemRequestException $exception) {
    refilling_item_response(['success' => false, 'message' => $exception->getMessage()], $exception->status);
} catch (Throwable) {
    refilling_item_response(['success' => false, 'message' => $method === 'GET' ? 'Refilling Items data is temporarily unavailable.' : 'Unable to update Refilling Items data.'], 500);
}
