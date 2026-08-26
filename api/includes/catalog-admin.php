<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/catalog-storage.php';

final class CatalogAdminRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }
}

function catalog_admin_specs(): array
{
    return [
        'categories' => [
            'singular' => 'Category',
            'prefix' => 'CAT',
            'fields' => ['name'],
        ],
        'subcategories' => [
            'singular' => 'Subcategory',
            'prefix' => 'SUB',
            'fields' => ['categoryId', 'name'],
        ],
        'suppliers' => [
            'singular' => 'Supplier',
            'prefix' => 'SUP',
            'fields' => ['name', 'logo'],
        ],
        'products' => [
            'singular' => 'Product',
            'prefix' => 'PRD',
            'fields' => ['subcategoryId', 'supplierId', 'title', 'image'],
        ],
    ];
}

function catalog_admin_response(array $body, int $status = 200): never
{
    header('X-Content-Type-Options: nosniff');
    json_response($body, $status);
}

function catalog_admin_error(CatalogAdminRequestException $exception): never
{
    catalog_admin_response([
        'success' => false,
        'message' => $exception->getMessage(),
        ...$exception->details,
    ], $exception->status);
}

function catalog_admin_read_json(): array
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        throw new CatalogAdminRequestException('JSON request required.', 415);
    }

    $limit = 8192;
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) {
        throw new CatalogAdminRequestException('Request is too large.', 413);
    }

    $raw = file_get_contents('php://input', false, null, 0, $limit + 1);
    if ($raw === false || strlen($raw) > $limit) {
        throw new CatalogAdminRequestException('Request is too large.', 413);
    }

    try {
        $decoded = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new CatalogAdminRequestException('Invalid request data.', 400);
    }

    if (!is_object($decoded)) {
        throw new CatalogAdminRequestException('Invalid request data.', 400);
    }
    return get_object_vars($decoded);
}

function catalog_admin_assert_fields(
    array $input,
    array $required,
    array $allowed,
    bool $allowPartial = false
): void {
    $unexpected = array_diff(array_keys($input), $allowed);
    if ($unexpected !== []) {
        throw new CatalogAdminRequestException('Request contains unsupported fields.', 400);
    }

    foreach ($required as $field) {
        if (!array_key_exists($field, $input)) {
            throw new CatalogAdminRequestException('Request is missing required fields.', 400);
        }
    }

    if ($allowPartial && count($input) <= count($required)) {
        throw new CatalogAdminRequestException('No update fields were provided.', 400);
    }
}

function catalog_admin_string(array $input, string $field, int $maxLength): string
{
    $value = $input[$field] ?? null;
    if (!is_string($value)) {
        throw new CatalogAdminRequestException('Request contains invalid field values.', 400);
    }
    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($value === '' || $length > $maxLength) {
        throw new CatalogAdminRequestException('Request contains invalid field values.', 400);
    }
    return $value;
}

function catalog_admin_id(array $input, string $field, string $prefix): string
{
    $id = $input[$field] ?? null;
    if (!is_string($id) || preg_match('/^' . $prefix . '[A-F0-9]{8}$/', $id) !== 1) {
        throw new CatalogAdminRequestException('Request contains an invalid ID.', 400);
    }
    return $id;
}

function catalog_admin_generate_id(string $prefix, array $records): string
{
    $existing = array_fill_keys(array_column($records, 'id'), true);
    for ($attempt = 0; $attempt < 32; $attempt++) {
        $id = $prefix . strtoupper(bin2hex(random_bytes(4)));
        if (!isset($existing[$id])) return $id;
    }
    throw new RuntimeException('Unable to allocate a catalogue ID.');
}

function catalog_admin_find_index(array $records, string $id, string $singular): int
{
    foreach ($records as $index => $record) {
        if ($record['id'] === $id) return $index;
    }
    throw new CatalogAdminRequestException($singular . ' not found.', 404);
}

function catalog_admin_assert_unique_name(
    array $records,
    string $name,
    ?string $scopeField = null,
    ?string $scopeValue = null,
    ?string $excludeId = null
): void {
    $normalizedName = catalog_normalize_comparison_value($name);
    $normalizedScope = $scopeValue === null ? null : catalog_normalize_comparison_value($scopeValue);
    foreach ($records as $record) {
        if ($record['id'] === $excludeId) continue;
        if ($scopeField !== null
            && catalog_normalize_comparison_value($record[$scopeField]) !== $normalizedScope) continue;
        if (catalog_normalize_comparison_value($record['name']) === $normalizedName) {
            throw new CatalogAdminRequestException('A record with that name already exists.', 409);
        }
    }
}

function catalog_admin_assert_reference(array $records, string $id, string $label): void
{
    foreach ($records as $record) {
        if ($record['id'] === $id) return;
    }
    throw new CatalogAdminRequestException('Referenced ' . $label . ' does not exist.', 400);
}

function catalog_admin_create_record(string $dataset, array $input, array &$catalog): array
{
    $spec = catalog_admin_specs()[$dataset];
    catalog_admin_assert_fields($input, $spec['fields'], $spec['fields']);
    $record = ['id' => catalog_admin_generate_id($spec['prefix'], $catalog[$dataset])];

    if ($dataset === 'categories') {
        $record['name'] = catalog_admin_string($input, 'name', 160);
        catalog_admin_assert_unique_name($catalog[$dataset], $record['name']);
    } elseif ($dataset === 'subcategories') {
        $record['categoryId'] = catalog_admin_id($input, 'categoryId', 'CAT');
        $record['name'] = catalog_admin_string($input, 'name', 160);
        catalog_admin_assert_reference($catalog['categories'], $record['categoryId'], 'category');
        catalog_admin_assert_unique_name($catalog[$dataset], $record['name'], 'categoryId', $record['categoryId']);
    } elseif ($dataset === 'suppliers') {
        $record['name'] = catalog_admin_string($input, 'name', 160);
        $record['logo'] = catalog_admin_string($input, 'logo', 1024);
        catalog_admin_assert_unique_name($catalog[$dataset], $record['name']);
    } else {
        $record['subcategoryId'] = catalog_admin_id($input, 'subcategoryId', 'SUB');
        $record['supplierId'] = catalog_admin_id($input, 'supplierId', 'SUP');
        $record['title'] = catalog_admin_string($input, 'title', 240);
        $record['image'] = catalog_admin_string($input, 'image', 1024);
        catalog_admin_assert_reference($catalog['subcategories'], $record['subcategoryId'], 'subcategory');
        catalog_admin_assert_reference($catalog['suppliers'], $record['supplierId'], 'supplier');
    }

    $catalog[$dataset][] = $record;
    return $record;
}

function catalog_admin_update_record(string $dataset, array $input, array &$catalog): array
{
    $spec = catalog_admin_specs()[$dataset];
    catalog_admin_assert_fields($input, ['id'], ['id', ...$spec['fields']], true);
    $id = catalog_admin_id($input, 'id', $spec['prefix']);
    $index = catalog_admin_find_index($catalog[$dataset], $id, $spec['singular']);
    $record = $catalog[$dataset][$index];

    foreach ($spec['fields'] as $field) {
        if (!array_key_exists($field, $input)) continue;
        $record[$field] = match ($field) {
            'categoryId' => catalog_admin_id($input, $field, 'CAT'),
            'subcategoryId' => catalog_admin_id($input, $field, 'SUB'),
            'supplierId' => catalog_admin_id($input, $field, 'SUP'),
            'name' => catalog_admin_string($input, $field, 160),
            'title' => catalog_admin_string($input, $field, 240),
            'logo', 'image' => catalog_admin_string($input, $field, 1024),
        };
    }

    if ($dataset === 'categories') {
        catalog_admin_assert_unique_name($catalog[$dataset], $record['name'], null, null, $id);
    } elseif ($dataset === 'subcategories') {
        catalog_admin_assert_reference($catalog['categories'], $record['categoryId'], 'category');
        catalog_admin_assert_unique_name($catalog[$dataset], $record['name'], 'categoryId', $record['categoryId'], $id);
    } elseif ($dataset === 'suppliers') {
        catalog_admin_assert_unique_name($catalog[$dataset], $record['name'], null, null, $id);
    } else {
        catalog_admin_assert_reference($catalog['subcategories'], $record['subcategoryId'], 'subcategory');
        catalog_admin_assert_reference($catalog['suppliers'], $record['supplierId'], 'supplier');
    }

    $catalog[$dataset][$index] = $record;
    return $record;
}

function catalog_admin_delete_record(string $dataset, array $input, array &$catalog): string
{
    $spec = catalog_admin_specs()[$dataset];
    catalog_admin_assert_fields($input, ['id'], ['id']);
    $id = catalog_admin_id($input, 'id', $spec['prefix']);
    $index = catalog_admin_find_index($catalog[$dataset], $id, $spec['singular']);

    if ($dataset === 'categories') {
        $count = count(array_filter($catalog['subcategories'], fn(array $item): bool => $item['categoryId'] === $id));
        if ($count > 0) throw new CatalogAdminRequestException(
            'Category cannot be deleted because it is in use.', 409, ['dependencies' => ['subcategories' => $count]]
        );
    } elseif ($dataset === 'subcategories') {
        $count = count(array_filter($catalog['products'], fn(array $item): bool => $item['subcategoryId'] === $id));
        if ($count > 0) throw new CatalogAdminRequestException(
            'Subcategory cannot be deleted because it is in use.', 409, ['dependencies' => ['products' => $count]]
        );
    } elseif ($dataset === 'suppliers') {
        $count = count(array_filter($catalog['products'], fn(array $item): bool => $item['supplierId'] === $id));
        if ($count > 0) throw new CatalogAdminRequestException(
            'Supplier cannot be deleted because it is in use.', 409, ['dependencies' => ['products' => $count]]
        );
    }

    array_splice($catalog[$dataset], $index, 1);
    return $id;
}

function serve_catalog_admin(string $dataset): never
{
    $specs = catalog_admin_specs();
    if (!isset($specs[$dataset])) catalog_admin_response(['success' => false, 'message' => 'Unsupported catalogue dataset.'], 500);

    auth_apply_cors(['GET', 'POST', 'PATCH', 'DELETE']);
    header('Allow: GET, POST, PATCH, DELETE, OPTIONS');
    header('X-Content-Type-Options: nosniff');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST', 'PATCH', 'DELETE'], true)) {
        catalog_admin_response(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    require_admin_auth();

    if ($method === 'GET') {
        try {
            catalog_admin_response(['success' => true, 'data' => catalog_read_dataset($dataset)]);
        } catch (Throwable) {
            catalog_admin_response(['success' => false, 'message' => 'Catalogue data is temporarily unavailable.'], 500);
        }
    }

    if (!csrf_verify_request()) {
        catalog_admin_response(['success' => false, 'message' => 'Invalid security token.'], 403);
    }

    try {
        $input = catalog_admin_read_json();
        $result = catalog_mutate_dataset(
            $dataset,
            function (array &$catalog) use ($dataset, $method, $input): mixed {
                return match ($method) {
                    'POST' => catalog_admin_create_record($dataset, $input, $catalog),
                    'PATCH' => catalog_admin_update_record($dataset, $input, $catalog),
                    'DELETE' => catalog_admin_delete_record($dataset, $input, $catalog),
                };
            }
        );

        if ($method === 'POST') catalog_admin_response(['success' => true, 'data' => $result], 201);
        if ($method === 'PATCH') catalog_admin_response(['success' => true, 'data' => $result]);
        catalog_admin_response([
            'success' => true,
            'message' => $specs[$dataset]['singular'] . ' deleted.',
            'deletedId' => $result,
        ]);
    } catch (CatalogAdminRequestException $exception) {
        catalog_admin_error($exception);
    } catch (Throwable) {
        catalog_admin_response(['success' => false, 'message' => 'Unable to update catalogue data.'], 500);
    }
}
