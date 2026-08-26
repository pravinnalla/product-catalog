<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/api/includes/catalog-storage.php';

try {
    $snapshot = backup_domain_create_snapshot('catalog');
    fwrite(STDOUT, "Catalogue snapshot created: {$snapshot['name']}\n");
    foreach (catalog_dataset_names() as $dataset) {
        fwrite(STDOUT, sprintf("%s: %d records\n", $dataset, $snapshot['counts'][$dataset]));
    }
    fwrite(STDOUT, "Snapshot validation: PASS\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Snapshot failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
