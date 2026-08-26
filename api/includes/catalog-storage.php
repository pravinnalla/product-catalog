<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog-validation.php';
require_once __DIR__ . '/paths.php';

final class CatalogStorageException extends RuntimeException
{
}

function catalog_private_root(): string
{
    return app_private_root();
}

function catalog_directory(): string
{
    return catalog_private_root() . '/catalog';
}

function catalog_lock_directory(): string
{
    return catalog_private_root() . '/locks';
}

function catalog_backup_directory(): string
{
    return catalog_private_root() . '/backups/catalog';
}

function catalog_dataset_path(string $dataset): string
{
    if (!in_array($dataset, catalog_dataset_names(), true)) {
        throw new CatalogStorageException('Unsupported catalogue dataset.');
    }

    return catalog_directory() . '/' . $dataset . '.json';
}

function catalog_ensure_directory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new CatalogStorageException('Unable to prepare private runtime storage.');
    }

    @chmod($directory, 0700);
}

function catalog_ensure_runtime_directories(): void
{
    catalog_ensure_directory(catalog_directory());
    catalog_ensure_directory(catalog_lock_directory());
    catalog_ensure_directory(catalog_backup_directory());
}

/**
 * @return resource
 */
function catalog_acquire_mutation_lock()
{
    catalog_ensure_runtime_directories();
    $handle = @fopen(catalog_lock_directory() . '/catalog.lock', 'c');

    if ($handle === false) {
        throw new CatalogStorageException('Unable to open the catalogue mutation lock.');
    }

    @chmod(catalog_lock_directory() . '/catalog.lock', 0600);

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new CatalogStorageException('Unable to acquire the catalogue mutation lock.');
    }

    return $handle;
}

/**
 * @param resource $handle
 */
function catalog_release_mutation_lock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

/**
 * @return list<mixed>
 */
function catalog_read_json_file(string $dataset, string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new CatalogStorageException(
            sprintf('The %s runtime dataset is unavailable.', $dataset)
        );
    }

    $contents = @file_get_contents($path);

    if ($contents === false) {
        throw new CatalogStorageException(
            sprintf('The %s runtime dataset could not be read.', $dataset)
        );
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new CatalogStorageException(
            sprintf('The %s runtime dataset contains malformed JSON.', $dataset),
            0,
            $exception
        );
    }

    if (!is_array($decoded) || !array_is_list($decoded)) {
        throw new CatalogStorageException(
            sprintf('The %s runtime dataset must contain a top-level array.', $dataset)
        );
    }

    return $decoded;
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function catalog_read_all(): array
{
    $catalog = [];

    foreach (catalog_dataset_names() as $dataset) {
        $catalog[$dataset] = catalog_read_json_file(
            $dataset,
            catalog_dataset_path($dataset)
        );
    }

    try {
        return catalog_validate_all($catalog);
    } catch (CatalogValidationException $exception) {
        throw new CatalogStorageException(
            'The runtime catalogue failed validation: ' . $exception->getMessage(),
            0,
            $exception
        );
    }
}

/**
 * @return list<array<string, mixed>>
 */
function catalog_read_dataset(string $dataset): array
{
    $path = catalog_dataset_path($dataset);
    unset($path);

    return catalog_read_all()[$dataset];
}

function catalog_encode_json(array $records): string
{
    try {
        return json_encode(
            $records,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
    } catch (JsonException $exception) {
        throw new CatalogStorageException(
            'The catalogue data could not be encoded.',
            0,
            $exception
        );
    }
}

function catalog_write_complete_file(string $path, string $contents): void
{
    $handle = @fopen($path, 'xb');

    if ($handle === false) {
        throw new CatalogStorageException('Unable to create a private catalogue file.');
    }

    $completed = false;

    try {
        $length = strlen($contents);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($handle, substr($contents, $written));

            if ($result === false || $result === 0) {
                throw new CatalogStorageException('Unable to write a private catalogue file.');
            }

            $written += $result;
        }

        if (!fflush($handle)) {
            throw new CatalogStorageException('Unable to flush a private catalogue file.');
        }

        if (function_exists('fsync') && !fsync($handle)) {
            throw new CatalogStorageException('Unable to synchronize a private catalogue file.');
        }

        $completed = true;
    } finally {
        fclose($handle);

        if (!$completed && is_file($path)) {
            @unlink($path);
        }
    }

    @chmod($path, 0600);
}

function catalog_atomic_replace(string $targetPath, string $contents): void
{
    $temporaryPath = $targetPath . '.tmp-' . bin2hex(random_bytes(8));

    try {
        catalog_write_complete_file($temporaryPath, $contents);

        if (!@rename($temporaryPath, $targetPath)) {
            throw new CatalogStorageException('Unable to replace the runtime catalogue safely.');
        }

        @chmod($targetPath, 0600);
    } finally {
        if (is_file($temporaryPath)) {
            @unlink($temporaryPath);
        }
    }
}

function catalog_create_backup(string $dataset, string $contents): void
{
    catalog_ensure_directory(catalog_backup_directory());
    $timestamp = gmdate('Ymd-His');
    $suffix = bin2hex(random_bytes(4));
    $backupPath = sprintf(
        '%s/%s-%s-%s.json',
        catalog_backup_directory(),
        $dataset,
        $timestamp,
        $suffix
    );

    catalog_write_complete_file($backupPath, $contents);
    catalog_prune_backups($dataset, 20);
}

function catalog_prune_backups(string $dataset, int $limit): void
{
    $files = glob(catalog_backup_directory() . '/' . $dataset . '-*.json');

    if ($files === false || count($files) <= $limit) {
        return;
    }

    usort(
        $files,
        static fn(string $left, string $right): int =>
            (filemtime($right) ?: 0) <=> (filemtime($left) ?: 0)
    );

    foreach (array_slice($files, $limit) as $file) {
        @unlink($file);
    }
}

/**
 * Replace one dataset while holding the global lock for the complete transaction.
 *
 * @param list<array<string, mixed>> $proposedRecords
 */
function catalog_replace_dataset(string $dataset, array $proposedRecords): void
{
    catalog_dataset_path($dataset);
    $lock = catalog_acquire_mutation_lock();

    try {
        $currentCatalog = catalog_read_all();
        $proposedCatalog = $currentCatalog;
        $proposedCatalog[$dataset] = $proposedRecords;

        try {
            $validatedCatalog = catalog_validate_all($proposedCatalog);
        } catch (CatalogValidationException $exception) {
            throw new CatalogStorageException(
                'The proposed catalogue update failed validation: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $targetPath = catalog_dataset_path($dataset);
        $currentContents = @file_get_contents($targetPath);

        if ($currentContents === false) {
            throw new CatalogStorageException('Unable to read the current dataset for backup.');
        }

        catalog_create_backup($dataset, $currentContents);
        catalog_atomic_replace(
            $targetPath,
            catalog_encode_json($validatedCatalog[$dataset])
        );
    } finally {
        catalog_release_mutation_lock($lock);
    }
}

/**
 * Run a single-dataset mutation while holding the global catalogue lock.
 *
 * The callback receives the complete validated catalogue by reference. It may
 * change only the requested dataset and may return a response value.
 */
function catalog_mutate_dataset(string $dataset, callable $mutation): mixed
{
    catalog_dataset_path($dataset);
    $lock = catalog_acquire_mutation_lock();

    try {
        $currentCatalog = catalog_read_all();
        $proposedCatalog = $currentCatalog;
        $result = $mutation($proposedCatalog);

        foreach (catalog_dataset_names() as $name) {
            if ($name !== $dataset && $proposedCatalog[$name] !== $currentCatalog[$name]) {
                throw new CatalogStorageException('A mutation attempted to change multiple datasets.');
            }
        }

        try {
            $validatedCatalog = catalog_validate_all($proposedCatalog);
        } catch (CatalogValidationException $exception) {
            throw new CatalogStorageException(
                'The proposed catalogue update failed validation.',
                0,
                $exception
            );
        }

        $targetPath = catalog_dataset_path($dataset);
        $currentContents = @file_get_contents($targetPath);
        if ($currentContents === false) {
            throw new CatalogStorageException('Unable to read the current dataset for backup.');
        }

        catalog_create_backup($dataset, $currentContents);
        catalog_atomic_replace(
            $targetPath,
            catalog_encode_json($validatedCatalog[$dataset])
        );

        return $result;
    } finally {
        catalog_release_mutation_lock($lock);
    }
}

/**
 * Initialize all runtime datasets exactly once.
 *
 * @param array<string, list<array<string, mixed>>> $catalog
 */
function catalog_initialize(array $catalog): void
{
    $lock = catalog_acquire_mutation_lock();

    try {
        foreach (catalog_dataset_names() as $dataset) {
            if (is_file(catalog_dataset_path($dataset))) {
                throw new CatalogStorageException(
                    'Runtime catalogue initialization was refused because data already exists.'
                );
            }
        }

        try {
            $validatedCatalog = catalog_validate_all($catalog);
        } catch (CatalogValidationException $exception) {
            throw new CatalogStorageException(
                'Source catalogue validation failed: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $createdFiles = [];

        try {
            foreach (catalog_dataset_names() as $dataset) {
                $path = catalog_dataset_path($dataset);
                catalog_write_complete_file(
                    $path,
                    catalog_encode_json($validatedCatalog[$dataset])
                );
                $createdFiles[] = $path;
            }
        } catch (Throwable $exception) {
            foreach ($createdFiles as $createdFile) {
                @unlink($createdFile);
            }

            throw $exception;
        }
    } finally {
        catalog_release_mutation_lock($lock);
    }
}
