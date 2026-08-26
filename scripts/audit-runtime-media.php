<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/api/includes/upload.php';

try {
    $catalog = catalog_read_all();
    $hasIssues = false;
    foreach (upload_specs() as $kind => $spec) {
        $directory = upload_ensure_directory($kind);
        $files = [];
        foreach (scandir($directory) ?: [] as $filename) {
            if (upload_is_managed_filename($kind, $filename) && is_file($directory . DIRECTORY_SEPARATOR . $filename)) {
                $files[] = $filename;
            }
        }
        sort($files);
        $references = [];
        foreach ($catalog[$spec['dataset']] as $record) {
            $filename = $record[$spec['field']];
            if (upload_is_managed_filename($kind, $filename)) $references[$filename] = true;
        }
        $referenced = array_keys($references);
        sort($referenced);
        $missing = array_values(array_diff($referenced, $files));
        $orphaned = array_values(array_diff($files, $referenced));
        $hasIssues = $hasIssues || $missing !== [] || $orphaned !== [];

        echo ucfirst($kind) . " media\n";
        echo '  Runtime files: ' . count($files) . "\n";
        echo '  Referenced runtime files: ' . count($referenced) . "\n";
        echo '  Missing referenced files: ' . ($missing === [] ? 'none' : implode(', ', $missing)) . "\n";
        echo '  Unreferenced runtime files: ' . ($orphaned === [] ? 'none' : implode(', ', $orphaned)) . "\n";
    }
    echo "Audit is report-only; no files were deleted.\n";
    exit($hasIssues ? 2 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Unable to audit runtime media.\n");
    exit(1);
}
