<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/includes/catalog-storage.php';

try {
    $catalog = catalog_read_all();

    fwrite(STDOUT, "Runtime catalogue is valid.\n");

    foreach (catalog_dataset_names() as $dataset) {
        fwrite(STDOUT, sprintf("%s: %d records\n", $dataset, count($catalog[$dataset])));
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Runtime catalogue validation failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
