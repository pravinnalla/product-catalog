<?php

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

final class BusinessStorageException extends RuntimeException
{
}

/** @return list<string> */
function business_dataset_names(): array
{
    return ['customers', 'receivables', 'refilling-items', 'certificates'];
}

function business_directory(): string
{
    return app_private_root() . '/business';
}

function business_lock_directory(): string
{
    return app_private_root() . '/locks';
}

function business_dataset_path(string $dataset): string
{
    if (!in_array($dataset, business_dataset_names(), true)) {
        throw new BusinessStorageException('Unsupported business dataset.');
    }
    return business_directory() . '/' . $dataset . '.json';
}

function business_ensure_directory(string $directory): void
{
    if (is_link($directory)) {
        throw new BusinessStorageException('Private business storage must not be a symbolic link.');
    }
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new BusinessStorageException('Unable to prepare private business storage.');
    }
    @chmod($directory, 0700);
}

/** @return resource */
function business_acquire_lock()
{
    business_ensure_directory(business_directory());
    business_ensure_directory(business_lock_directory());
    $path = business_lock_directory() . '/business.lock';
    if (is_link($path)) throw new BusinessStorageException('The business storage lock is unsafe.');
    $handle = @fopen($path, 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new BusinessStorageException('Unable to lock business storage.');
    }
    @chmod($path, 0600);
    return $handle;
}

/** @param resource $handle */
function business_release_lock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

function business_encode_json(array $records): string
{
    try {
        return json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    } catch (JsonException $exception) {
        throw new BusinessStorageException('Business data could not be encoded.', 0, $exception);
    }
}

function business_atomic_write(string $path, string $contents): void
{
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    $handle = @fopen($temporary, 'xb');
    if ($handle === false) throw new BusinessStorageException('Unable to create a private business file.');
    $complete = false;
    try {
        $length = strlen($contents);
        for ($written = 0; $written < $length;) {
            $count = fwrite($handle, substr($contents, $written));
            if ($count === false || $count === 0) throw new BusinessStorageException('Unable to write business data.');
            $written += $count;
        }
        if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
            throw new BusinessStorageException('Unable to synchronize business data.');
        }
        $complete = true;
    } finally {
        fclose($handle);
        if (!$complete) @unlink($temporary);
    }
    @chmod($temporary, 0600);
    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        throw new BusinessStorageException('Unable to replace business data safely.');
    }
    @chmod($path, 0600);
}

/** @return list<array<string, mixed>> */
function business_read_dataset_file(string $dataset, string $path): array
{
    if (is_link($path) || !is_file($path) || !is_readable($path)) {
        throw new BusinessStorageException(sprintf('The %s business dataset is unavailable.', $dataset));
    }
    $contents = @file_get_contents($path);
    if ($contents === false) throw new BusinessStorageException(sprintf('The %s business dataset could not be read.', $dataset));
    try {
        $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new BusinessStorageException(sprintf('The %s business dataset contains malformed JSON.', $dataset), 0, $exception);
    }
    if (!is_array($records) || !array_is_list($records)) {
        throw new BusinessStorageException(sprintf('The %s business dataset must contain a top-level array.', $dataset));
    }
    return $records;
}

function business_initialize_dataset_locked(string $dataset): void
{
    $path = business_dataset_path($dataset);
    if (is_link($path)) throw new BusinessStorageException('Business datasets must not be symbolic links.');
    if (!file_exists($path)) business_atomic_write($path, business_encode_json([]));
}

/** @return list<array<string, mixed>> */
function business_read_dataset(string $dataset): array
{
    $path = business_dataset_path($dataset);
    if (!file_exists($path)) {
        $lock = business_acquire_lock();
        try { business_initialize_dataset_locked($dataset); }
        finally { business_release_lock($lock); }
    }
    return business_read_dataset_file($dataset, $path);
}

function business_mutate_dataset(string $dataset, callable $mutation): mixed
{
    $lock = business_acquire_lock();
    try {
        business_initialize_dataset_locked($dataset);
        $path = business_dataset_path($dataset);
        $records = business_read_dataset_file($dataset, $path);
        $result = $mutation($records);
        business_atomic_write($path, business_encode_json($records));
        return $result;
    } finally {
        business_release_lock($lock);
    }
}

function business_find_record_index(array $records, string $id): ?int
{
    foreach ($records as $index => $record) {
        if (is_array($record) && ($record['id'] ?? null) === $id) return $index;
    }
    return null;
}

function business_next_record_id(array $records, string $prefix, int $width = 4): string
{
    $highest = 0;
    foreach ($records as $record) {
        $id = is_array($record) ? ($record['id'] ?? null) : null;
        if (is_string($id) && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/D', $id, $matches) === 1) {
            $highest = max($highest, (int) $matches[1]);
        }
    }
    $next = $highest + 1;
    return $prefix . str_pad((string) $next, $width, '0', STR_PAD_LEFT);
}

function business_normalize_text(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function business_stored_date_is_valid(mixed $value, bool $optional = false): bool
{
    if ($optional && $value === null) return true;
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/**
 * Validate the complete frozen Business domain, including cross-dataset links.
 *
 * @param array<string, mixed> $business
 * @return array<string, list<array<string, mixed>>>
 */
function business_validate_all(array $business): array
{
    if (array_keys($business) !== business_dataset_names()) {
        throw new BusinessStorageException('The Business domain does not contain the required datasets.');
    }
    foreach ($business as $dataset => $records) {
        if (!is_array($records) || !array_is_list($records)) {
            throw new BusinessStorageException(sprintf('The %s business dataset must contain a top-level array.', $dataset));
        }
    }

    $customerIds = [];
    $customerFields = ['id', 'name', 'address', 'gstin', 'contactPerson', 'phone', 'email', 'isActive'];
    foreach ($business['customers'] as $record) {
        if (!is_array($record) || array_keys($record) !== $customerFields
            || !is_string($record['id']) || preg_match('/^CUS\d{4,}$/D', $record['id']) !== 1 || isset($customerIds[$record['id']])
            || !is_bool($record['isActive'])) {
            throw new BusinessStorageException('The customers business dataset failed validation.');
        }
        foreach (['name', 'address', 'gstin', 'contactPerson', 'phone', 'email'] as $field) {
            if (!is_string($record[$field])) throw new BusinessStorageException('The customers business dataset failed validation.');
        }
        if (trim($record['name']) === '' || trim($record['address']) === ''
            || ($record['email'] !== '' && filter_var($record['email'], FILTER_VALIDATE_EMAIL) === false)) {
            throw new BusinessStorageException('The customers business dataset failed validation.');
        }
        $customerIds[$record['id']] = true;
    }

    $refillingItemIds = [];
    foreach ($business['refilling-items'] as $record) {
        if (!is_array($record) || array_keys($record) !== ['id', 'name', 'isActive']
            || !is_string($record['id']) || preg_match('/^RFI\d{4,}$/D', $record['id']) !== 1 || isset($refillingItemIds[$record['id']])
            || !is_string($record['name']) || trim($record['name']) === '' || !is_bool($record['isActive'])) {
            throw new BusinessStorageException('The refilling-items business dataset failed validation.');
        }
        $refillingItemIds[$record['id']] = true;
    }

    $receivableIds = [];
    $receivableFields = ['id', 'customerId', 'invoiceNumber', 'invoiceDate', 'invoiceAmount', 'dueDate', 'businessType', 'payments', 'remarks'];
    $paymentFields = ['id', 'paymentDate', 'amount', 'paymentMode', 'reference', 'remarks'];
    foreach ($business['receivables'] as $record) {
        if (!is_array($record) || array_keys($record) !== $receivableFields
            || !is_string($record['id']) || preg_match('/^REC\d{4,}$/D', $record['id']) !== 1 || isset($receivableIds[$record['id']])
            || !is_string($record['customerId']) || !isset($customerIds[$record['customerId']])
            || !is_string($record['invoiceNumber']) || trim($record['invoiceNumber']) === ''
            || !business_stored_date_is_valid($record['invoiceDate'])
            || (!is_int($record['invoiceAmount']) && !is_float($record['invoiceAmount'])) || !is_finite((float) $record['invoiceAmount']) || (float) $record['invoiceAmount'] <= 0
            || !business_stored_date_is_valid($record['dueDate'], true)
            || !in_array($record['businessType'], ['PRODUCT', 'REFILLING'], true)
            || !is_array($record['payments']) || !array_is_list($record['payments']) || !is_string($record['remarks'])) {
            throw new BusinessStorageException('The receivables business dataset failed validation.');
        }
        $paymentIds = [];
        $paidCents = 0;
        foreach ($record['payments'] as $payment) {
            if (!is_array($payment) || array_keys($payment) !== $paymentFields
                || !is_string($payment['id']) || preg_match('/^PAY\d{4,}$/D', $payment['id']) !== 1 || isset($paymentIds[$payment['id']])
                || (!is_int($payment['amount']) && !is_float($payment['amount'])) || !is_finite((float) $payment['amount']) || (float) $payment['amount'] <= 0
                || !business_stored_date_is_valid($payment['paymentDate']) || !is_string($payment['paymentMode'])
                || !is_string($payment['reference']) || !is_string($payment['remarks'])) {
                throw new BusinessStorageException('The receivables business dataset failed validation.');
            }
            $paymentIds[$payment['id']] = true;
            $paidCents += (int) round((float) $payment['amount'] * 100);
        }
        if ($paidCents > (int) round((float) $record['invoiceAmount'] * 100)) {
            throw new BusinessStorageException('The receivables business dataset failed validation.');
        }
        $receivableIds[$record['id']] = true;
    }

    $certificateIds = [];
    $certificateFields = ['id', 'certificateNumber', 'customerId', 'invoiceNumber', 'certificateDate', 'items', 'remarks'];
    $itemFields = ['id', 'refillingItemId', 'capacity', 'quantity', 'serialNumbers', 'refillingDate', 'nextRefillingDate', 'remark'];
    foreach ($business['certificates'] as $record) {
        if (!is_array($record) || array_keys($record) !== $certificateFields
            || !is_string($record['id']) || preg_match('/^CERT\d{4,}$/D', $record['id']) !== 1 || isset($certificateIds[$record['id']])
            || !is_string($record['certificateNumber']) || trim($record['certificateNumber']) === ''
            || !is_string($record['customerId']) || !isset($customerIds[$record['customerId']])
            || !is_string($record['invoiceNumber']) || trim($record['invoiceNumber']) === ''
            || !business_stored_date_is_valid($record['certificateDate'])
            || !is_array($record['items']) || !array_is_list($record['items']) || count($record['items']) < 1
            || !is_string($record['remarks'])) {
            throw new BusinessStorageException('The certificates business dataset failed validation.');
        }
        $itemIds = [];
        foreach ($record['items'] as $item) {
            if (!is_array($item) || array_keys($item) !== $itemFields
                || !is_string($item['id']) || preg_match('/^CIT\d{4,}$/D', $item['id']) !== 1 || isset($itemIds[$item['id']])
                || !is_string($item['refillingItemId']) || !isset($refillingItemIds[$item['refillingItemId']])
                || !is_string($item['capacity']) || trim($item['capacity']) === ''
                || !is_int($item['quantity']) || $item['quantity'] < 1
                || !is_array($item['serialNumbers']) || !array_is_list($item['serialNumbers'])
                || !business_stored_date_is_valid($item['refillingDate']) || !business_stored_date_is_valid($item['nextRefillingDate'])
                || $item['nextRefillingDate'] < $item['refillingDate'] || !is_string($item['remark'])) {
                throw new BusinessStorageException('The certificates business dataset failed validation.');
            }
            foreach ($item['serialNumbers'] as $serial) {
                if (!is_string($serial) || trim($serial) === '') throw new BusinessStorageException('The certificates business dataset failed validation.');
            }
            $itemIds[$item['id']] = true;
        }
        $certificateIds[$record['id']] = true;
    }

    return $business;
}

/** @return array<string, list<array<string, mixed>>> */
function business_read_all_locked(): array
{
    $business = [];
    foreach (business_dataset_names() as $dataset) {
        business_initialize_dataset_locked($dataset);
        $business[$dataset] = business_read_dataset_file($dataset, business_dataset_path($dataset));
    }
    return business_validate_all($business);
}

/** @return array<string, list<array<string, mixed>>> */
function business_read_all(): array
{
    $lock = business_acquire_lock();
    try { return business_read_all_locked(); }
    finally { business_release_lock($lock); }
}
