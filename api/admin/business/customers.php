<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/business-storage.php';

final class CustomerRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400) { parent::__construct($message); }
}

function customer_response(array $body, int $status = 200): never
{
    header('X-Content-Type-Options: nosniff');
    json_response($body, $status);
}

function customer_read_json(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) throw new CustomerRequestException('JSON request required.', 415);
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) throw new CustomerRequestException('Request is too large.', 413);
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if ($raw === false || strlen($raw) > 16384) throw new CustomerRequestException('Request is too large.', 413);
    try { $decoded = json_decode($raw, false, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException) { throw new CustomerRequestException('Invalid request data.'); }
    if (!is_object($decoded)) throw new CustomerRequestException('Invalid request data.');
    return get_object_vars($decoded);
}

function customer_string(array $input, string $field, int $maximum, bool $required): string
{
    $value = $input[$field] ?? null;
    if (!is_string($value)) throw new CustomerRequestException($required ? "$field is required." : "Invalid $field.");
    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if (($required && $value === '') || $length > $maximum) throw new CustomerRequestException("Invalid $field.");
    return $value;
}

function customer_validate(array $input, bool $create): array
{
    $fields = ['name', 'address', 'gstin', 'contactPerson', 'phone', 'email', 'isActive'];
    if (array_diff(array_keys($input), $fields) !== []) throw new CustomerRequestException('Request contains unsupported fields.');
    if ($create && array_diff(['name', 'address'], array_keys($input)) !== []) throw new CustomerRequestException('Name and address are required.');
    $record = [];
    foreach (['name' => [160, true], 'address' => [2000, true], 'gstin' => [32, false], 'contactPerson' => [160, false], 'phone' => [80, false], 'email' => [254, false]] as $field => [$maximum, $required]) {
        if (array_key_exists($field, $input) || $create) $record[$field] = customer_string($input + [$field => ''], $field, $maximum, $required);
    }
    if (isset($record['email']) && $record['email'] !== '' && filter_var($record['email'], FILTER_VALIDATE_EMAIL) === false) {
        throw new CustomerRequestException('Enter a valid email address.');
    }
    if (array_key_exists('isActive', $input) && !is_bool($input['isActive'])) throw new CustomerRequestException('Active status must be true or false.');
    if (array_key_exists('isActive', $input) || $create) $record['isActive'] = $input['isActive'] ?? true;
    if (!$create && $record === []) throw new CustomerRequestException('No update fields were provided.');
    return $record;
}

function customer_assert_unique(array $records, string $name, ?string $excludeId = null): void
{
    $normalized = business_normalize_text($name);
    foreach ($records as $record) {
        if (($record['id'] ?? null) === $excludeId) continue;
        if (is_string($record['name'] ?? null) && business_normalize_text($record['name']) === $normalized) {
            throw new CustomerRequestException('A customer with that name already exists.', 409);
        }
    }
}

function customer_assert_dataset(array $records): void
{
    $expected = ['id', 'name', 'address', 'gstin', 'contactPerson', 'phone', 'email', 'isActive'];
    $ids = [];
    foreach ($records as $record) {
        if (!is_array($record) || array_keys($record) !== $expected
            || !is_string($record['id']) || preg_match('/^CUS\d{4,}$/D', $record['id']) !== 1
            || isset($ids[$record['id']]) || !is_bool($record['isActive'])) {
            throw new BusinessStorageException('The customers business dataset failed validation.');
        }
        foreach (['name', 'address', 'gstin', 'contactPerson', 'phone', 'email'] as $field) {
            if (!is_string($record[$field])) throw new BusinessStorageException('The customers business dataset failed validation.');
        }
        if (trim($record['name']) === '' || trim($record['address']) === ''
            || ($record['email'] !== '' && filter_var($record['email'], FILTER_VALIDATE_EMAIL) === false)) {
            throw new BusinessStorageException('The customers business dataset failed validation.');
        }
        $ids[$record['id']] = true;
    }
}

auth_apply_cors(['GET', 'POST', 'PATCH']);
header('Allow: GET, POST, PATCH, OPTIONS');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST', 'PATCH'], true)) customer_response(['success' => false, 'message' => 'Method not allowed.'], 405);
require_admin_auth();

try {
    if ($method === 'GET') {
        $records = business_read_dataset('customers');
        customer_assert_dataset($records);
        $id = $_GET['id'] ?? null;
        if ($id === null || $id === '') customer_response(['success' => true, 'data' => $records]);
        if (!is_string($id) || preg_match('/^CUS\d{4,}$/D', $id) !== 1) throw new CustomerRequestException('Invalid customer ID.');
        $index = business_find_record_index($records, $id);
        if ($index === null) throw new CustomerRequestException('Customer not found.', 404);
        customer_response(['success' => true, 'data' => $records[$index]]);
    }
    if (!csrf_verify_request()) customer_response(['success' => false, 'message' => 'Invalid security token.'], 403);
    $input = customer_read_json();
    $result = business_mutate_dataset('customers', function (array &$records) use ($method, $input): array {
        customer_assert_dataset($records);
        if ($method === 'POST') {
            $data = customer_validate($input, true);
            customer_assert_unique($records, $data['name']);
            $record = ['id' => business_next_record_id($records, 'CUS'), ...$data];
            $records[] = $record;
            return $record;
        }
        $id = $input['id'] ?? null;
        if (!is_string($id) || preg_match('/^CUS\d{4,}$/D', $id) !== 1) throw new CustomerRequestException('Invalid customer ID.');
        $data = $input; unset($data['id']);
        $changes = customer_validate($data, false);
        $index = business_find_record_index($records, $id);
        if ($index === null) throw new CustomerRequestException('Customer not found.', 404);
        $updated = [...$records[$index], ...$changes];
        customer_assert_unique($records, $updated['name'], $id);
        $records[$index] = $updated;
        return $updated;
    });
    customer_response(['success' => true, 'data' => $result], $method === 'POST' ? 201 : 200);
} catch (CustomerRequestException $exception) {
    customer_response(['success' => false, 'message' => $exception->getMessage()], $exception->status);
} catch (Throwable) {
    customer_response(['success' => false, 'message' => $method === 'GET' ? 'Customer data is temporarily unavailable.' : 'Unable to update customer data.'], 500);
}
