<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/includes/catalog-storage.php';

/**
 * @return list<mixed>
 */
function read_source_dataset(string $dataset): array
{
    $sourcePath = dirname(__DIR__) . '/src/data/' . $dataset . '.json';
    $contents = @file_get_contents($sourcePath);

    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read the %s source dataset.', $dataset));
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException(
            sprintf('The %s source dataset contains malformed JSON.', $dataset),
            0,
            $exception
        );
    }

    if (!is_array($decoded) || !array_is_list($decoded)) {
        throw new RuntimeException(
            sprintf('The %s source dataset must contain a top-level array.', $dataset)
        );
    }

    return $decoded;
}

try {
    $catalog = [];

    foreach (catalog_dataset_names() as $dataset) {
        $catalog[$dataset] = read_source_dataset($dataset);
    }

    $runtimeSuppliers = [];

    foreach ($catalog['suppliers'] as $index => $supplier) {
        if (!is_array($supplier) || array_is_list($supplier)) {
            throw new RuntimeException(
                sprintf('suppliers source record %d must be an object.', $index)
            );
        }

        $allowedSourceFields = ['id', 'name', 'logo', 'description'];
        $unexpectedFields = array_diff(array_keys($supplier), $allowedSourceFields);

        if ($unexpectedFields !== []) {
            throw new RuntimeException(
                sprintf('suppliers source record %d contains unsupported fields.', $index)
            );
        }

        $runtimeSuppliers[] = [
            'id' => $supplier['id'] ?? null,
            'name' => $supplier['name'] ?? null,
            'logo' => $supplier['logo'] ?? null,
        ];
    }

    $catalog['suppliers'] = $runtimeSuppliers;
    catalog_initialize($catalog);
    $validated = catalog_read_all();

    fwrite(STDOUT, "Runtime catalogue initialized successfully.\n");

    foreach (catalog_dataset_names() as $dataset) {
        fwrite(STDOUT, sprintf("%s: %d records\n", $dataset, count($validated[$dataset])));
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Runtime catalogue initialization failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
