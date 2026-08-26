<?php

declare(strict_types=1);

final class CatalogValidationException extends RuntimeException
{
}

/**
 * @return list<string>
 */
function catalog_dataset_names(): array
{
    return [
        "categories",
        "subcategories",
        "suppliers",
        "products",
    ];
}

function catalog_normalize_comparison_value(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

/**
 * @param array<string, mixed> $record
 * @param list<string> $requiredFields
 */
function catalog_validate_exact_fields(
    string $dataset,
    int $index,
    array $record,
    array $requiredFields
): void {
    $actualFields = array_keys($record);
    sort($actualFields);
    $expectedFields = $requiredFields;
    sort($expectedFields);

    if ($actualFields !== $expectedFields) {
        throw new CatalogValidationException(
            sprintf('%s record %d does not match the frozen schema.', $dataset, $index)
        );
    }
}

/**
 * @param array<string, mixed> $record
 */
function catalog_require_non_empty_string(
    string $dataset,
    int $index,
    array $record,
    string $field
): string {
    $value = $record[$field] ?? null;

    if (!is_string($value) || trim($value) === '') {
        throw new CatalogValidationException(
            sprintf('%s record %d has an invalid %s.', $dataset, $index, $field)
        );
    }

    return $value;
}

function catalog_validate_id(
    string $dataset,
    int $index,
    string $id,
    string $pattern
): void {
    if (preg_match($pattern, $id) !== 1) {
        throw new CatalogValidationException(
            sprintf('%s record %d has an invalid ID.', $dataset, $index)
        );
    }
}

/**
 * @param list<array<string, mixed>> $records
 * @return array<string, true>
 */
function catalog_validate_unique_ids(string $dataset, array $records): array
{
    $ids = [];

    foreach ($records as $index => $record) {
        $id = $record['id'];

        if (isset($ids[$id])) {
            throw new CatalogValidationException(
                sprintf('%s contains a duplicate ID at record %d.', $dataset, $index)
            );
        }

        $ids[$id] = true;
    }

    return $ids;
}

/**
 * @param list<array<string, mixed>> $records
 */
function catalog_validate_unique_names(
    string $dataset,
    array $records,
    ?string $scopeField = null
): void {
    $names = [];

    foreach ($records as $index => $record) {
        $name = catalog_normalize_comparison_value($record['name']);
        $scope = $scopeField === null
            ? ''
            : catalog_normalize_comparison_value($record[$scopeField]);
        $key = $scope . "\0" . $name;

        if (isset($names[$key])) {
            throw new CatalogValidationException(
                sprintf('%s contains a duplicate business name at record %d.', $dataset, $index)
            );
        }

        $names[$key] = true;
    }
}

/**
 * @param list<mixed> $records
 * @return list<array<string, mixed>>
 */
function catalog_validate_dataset_records(string $dataset, array $records): array
{
    if (!in_array($dataset, catalog_dataset_names(), true)) {
        throw new CatalogValidationException('Unsupported catalogue dataset.');
    }

    $validated = [];

    foreach ($records as $index => $record) {
        if (!is_array($record) || array_is_list($record)) {
            throw new CatalogValidationException(
                sprintf('%s record %d must be an object.', $dataset, $index)
            );
        }

        switch ($dataset) {
            case 'categories':
                catalog_validate_exact_fields($dataset, $index, $record, ['id', 'name']);
                $id = catalog_require_non_empty_string($dataset, $index, $record, 'id');
                catalog_require_non_empty_string($dataset, $index, $record, 'name');
                catalog_validate_id($dataset, $index, $id, '/^CAT[A-F0-9]{8}$/');
                break;

            case 'subcategories':
                catalog_validate_exact_fields(
                    $dataset,
                    $index,
                    $record,
                    ['id', 'categoryId', 'name']
                );
                $id = catalog_require_non_empty_string($dataset, $index, $record, 'id');
                $categoryId = catalog_require_non_empty_string(
                    $dataset,
                    $index,
                    $record,
                    'categoryId'
                );
                catalog_require_non_empty_string($dataset, $index, $record, 'name');
                catalog_validate_id($dataset, $index, $id, '/^SUB[A-F0-9]{8}$/');
                catalog_validate_id($dataset, $index, $categoryId, '/^CAT[A-F0-9]{8}$/');
                break;

            case 'suppliers':
                catalog_validate_exact_fields(
                    $dataset,
                    $index,
                    $record,
                    ['id', 'name', 'logo']
                );
                $id = catalog_require_non_empty_string($dataset, $index, $record, 'id');
                catalog_require_non_empty_string($dataset, $index, $record, 'name');
                catalog_require_non_empty_string($dataset, $index, $record, 'logo');
                catalog_validate_id($dataset, $index, $id, '/^SUP[A-F0-9]{8}$/');
                break;

            case 'products':
                catalog_validate_exact_fields(
                    $dataset,
                    $index,
                    $record,
                    ['id', 'subcategoryId', 'supplierId', 'title', 'image']
                );
                $id = catalog_require_non_empty_string($dataset, $index, $record, 'id');
                $subcategoryId = catalog_require_non_empty_string(
                    $dataset,
                    $index,
                    $record,
                    'subcategoryId'
                );
                $supplierId = catalog_require_non_empty_string(
                    $dataset,
                    $index,
                    $record,
                    'supplierId'
                );
                catalog_require_non_empty_string($dataset, $index, $record, 'title');
                catalog_require_non_empty_string($dataset, $index, $record, 'image');
                catalog_validate_id($dataset, $index, $id, '/^PRD[A-F0-9]{8}$/');
                catalog_validate_id($dataset, $index, $subcategoryId, '/^SUB[A-F0-9]{8}$/');
                catalog_validate_id($dataset, $index, $supplierId, '/^SUP[A-F0-9]{8}$/');
                break;
        }

        $validated[] = $record;
    }

    catalog_validate_unique_ids($dataset, $validated);

    if ($dataset === 'categories' || $dataset === 'suppliers') {
        catalog_validate_unique_names($dataset, $validated);
    } elseif ($dataset === 'subcategories') {
        catalog_validate_unique_names($dataset, $validated, 'categoryId');
    }

    return $validated;
}

/**
 * @param array<string, list<mixed>> $catalog
 * @return array<string, list<array<string, mixed>>>
 */
function catalog_validate_all(array $catalog): array
{
    $expectedDatasets = catalog_dataset_names();
    $actualDatasets = array_keys($catalog);
    sort($expectedDatasets);
    sort($actualDatasets);

    if ($actualDatasets !== $expectedDatasets) {
        throw new CatalogValidationException('The catalogue dataset collection is incomplete.');
    }

    $validated = [];

    foreach (catalog_dataset_names() as $dataset) {
        if (!is_array($catalog[$dataset]) || !array_is_list($catalog[$dataset])) {
            throw new CatalogValidationException(
                sprintf('%s must contain a top-level JSON array.', $dataset)
            );
        }

        $validated[$dataset] = catalog_validate_dataset_records(
            $dataset,
            $catalog[$dataset]
        );
    }

    $categoryIds = catalog_validate_unique_ids('categories', $validated['categories']);
    $subcategoryIds = catalog_validate_unique_ids('subcategories', $validated['subcategories']);
    $supplierIds = catalog_validate_unique_ids('suppliers', $validated['suppliers']);

    foreach ($validated['subcategories'] as $index => $subcategory) {
        if (!isset($categoryIds[$subcategory['categoryId']])) {
            throw new CatalogValidationException(
                sprintf('subcategories record %d references an unknown category.', $index)
            );
        }
    }

    foreach ($validated['products'] as $index => $product) {
        if (!isset($subcategoryIds[$product['subcategoryId']])) {
            throw new CatalogValidationException(
                sprintf('products record %d references an unknown subcategory.', $index)
            );
        }

        if (!isset($supplierIds[$product['supplierId']])) {
            throw new CatalogValidationException(
                sprintf('products record %d references an unknown supplier.', $index)
            );
        }
    }

    return $validated;
}
