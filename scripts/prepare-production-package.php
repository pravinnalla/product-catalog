<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$project = dirname(__DIR__);
$target = $project . '/deployment';
$force = in_array('--force', $argv, true);

function package_remove_tree(string $directory): void
{
    if (!is_dir($directory)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($directory);
}

function package_copy_tree(string $source, string $destination, ?callable $include = null): void
{
    if (!is_dir($source)) throw new RuntimeException("Required directory is missing: {$source}");
    if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
        throw new RuntimeException("Unable to create package directory: {$destination}");
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($items as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        if ($include !== null && !$include($relative, $item)) continue;
        $path = $destination . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($path)) mkdir($path, 0755, true);
        } else {
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
            if (!copy($item->getPathname(), $path)) throw new RuntimeException("Unable to copy {$relative}");
        }
    }
}

try {
    if (is_dir($target)) {
        if (!$force) throw new RuntimeException('deployment/ already exists. Re-run with --force to replace it.');
        package_remove_tree($target);
    }

    package_copy_tree($project . '/dist', $target . '/public_html');
    package_copy_tree(
        $project . '/api',
        $target . '/public_html/api',
        static fn(string $relative): bool => $relative !== 'config.php' && !str_starts_with($relative, '.rate-limit')
    );
    if (is_dir($project . '/vendor')) package_copy_tree($project . '/vendor', $target . '/public_html/vendor');

    foreach (['catalog', 'locks', 'backups', 'rate-limit', 'logs'] as $directory) {
        mkdir($target . '/laxmikant_private/' . $directory, 0700, true);
    }
    package_copy_tree($project . '/private/catalog', $target . '/laxmikant_private/catalog');
    copy($project . '/private/admin.example.php', $target . '/laxmikant_private/admin.example.php');
    copy($project . '/docs/PRODUCTION-DEPLOYMENT.md', $target . '/README.md');

    fwrite(STDOUT, "Production package prepared under deployment/. No live server was contacted.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Package preparation failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
