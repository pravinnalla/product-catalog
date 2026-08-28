<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

$root = sys_get_temp_dir() . '/business-storage-test-' . bin2hex(random_bytes(6));
putenv('APP_PRIVATE_ROOT=' . $root);
require_once dirname(__DIR__) . '/api/includes/business-storage.php';
$results = [];

function business_test(array &$results, string $label, callable $callback): void
{
    try { $results[$label] = $callback() === true; }
    catch (Throwable) { $results[$label] = false; }
}

function business_test_throws(callable $callback): bool
{
    try { $callback(); return false; }
    catch (Throwable) { return true; }
}

function business_test_remove(string $directory): void
{
    if (!is_dir($directory)) return;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($directory);
}

try {
    business_test($results, 'missing customers dataset initializes privately', static fn(): bool =>
        business_read_dataset('customers') === [] && is_file(business_dataset_path('customers'))
            && str_starts_with(business_dataset_path('customers'), app_private_root() . '/business/'));
    business_test($results, 'valid write and read', static function (): bool {
        business_mutate_dataset('customers', static function (array &$records): void {
            $records[] = ['id' => 'CUS0001', 'name' => 'ABC Industries'];
        });
        return business_read_dataset('customers')[0]['id'] === 'CUS0001';
    });
    business_test($results, 'missing receivables dataset initializes privately', static fn(): bool =>
        business_read_dataset('receivables') === [] && is_file(business_dataset_path('receivables')));
    business_test($results, 'receivable write preserves embedded payments', static function (): bool {
        $record = ['id' => 'REC0001', 'customerId' => 'CUS0001', 'invoiceNumber' => 'GST/26-27/001',
            'invoiceDate' => '2026-08-26', 'invoiceAmount' => 10000.00, 'dueDate' => null,
            'businessType' => 'PRODUCT', 'payments' => [['id' => 'PAY0001', 'paymentDate' => '2026-08-27',
                'amount' => 4000.00, 'paymentMode' => 'BANK', 'reference' => 'NEFT123', 'remarks' => '']], 'remarks' => ''];
        business_mutate_dataset('receivables', static function (array &$records) use ($record): void { $records[] = $record; });
        $stored = business_read_dataset('receivables');
        return ($stored[0]['id'] ?? '') === 'REC0001' && ($stored[0]['payments'][0]['id'] ?? '') === 'PAY0001'
            && (float) ($stored[0]['payments'][0]['amount'] ?? 0) === 4000.0;
    });
    business_test($results, 'missing refilling-items dataset initializes privately', static fn(): bool =>
        business_read_dataset('refilling-items') === [] && is_file(business_dataset_path('refilling-items')));
    business_test($results, 'refilling item write preserves active status', static function (): bool {
        business_mutate_dataset('refilling-items', static function (array &$records): void {
            $records[] = ['id' => 'RFI0001', 'name' => 'ABC Fire Extinguisher', 'isActive' => false];
        });
        $stored = business_read_dataset('refilling-items');
        return ($stored[0] ?? null) === ['id' => 'RFI0001', 'name' => 'ABC Fire Extinguisher', 'isActive' => false];
    });
    business_test($results, 'missing certificates dataset initializes privately', static fn(): bool =>
        business_read_dataset('certificates') === [] && is_file(business_dataset_path('certificates')));
    business_test($results, 'certificate write preserves items and serial numbers', static function (): bool {
        $record = ['id' => 'CERT0001', 'certificateNumber' => 'CERT/26-27/001', 'customerId' => 'CUS0001', 'invoiceNumber' => 'GST/001', 'certificateDate' => '2026-08-26',
            'items' => [['id' => 'CIT0001', 'refillingItemId' => 'RFI0001', 'capacity' => '9 KG', 'quantity' => 4, 'serialNumbers' => ['ABC001'], 'refillingDate' => '2026-08-26', 'nextRefillingDate' => '2027-08-25', 'remark' => 'OK']], 'remarks' => ''];
        business_mutate_dataset('certificates', static function (array &$records) use ($record): void { $records[] = $record; });
        return business_read_dataset('certificates')[0]['items'][0]['serialNumbers'] === ['ABC001'];
    });
    business_test($results, 'next ID does not collide', static fn(): bool =>
        business_next_record_id([['id' => 'CUS0001'], ['id' => 'CUS0003']], 'CUS') === 'CUS0004');
    business_test($results, 'customer-name normalization', static fn(): bool =>
        business_normalize_text(' ABC   INDUSTRIES ') === business_normalize_text('abc industries'));
    $before = file_get_contents(business_dataset_path('customers'));
    file_put_contents(business_dataset_path('customers'), '{broken');
    business_test($results, 'malformed JSON is rejected without replacement', static function () use ($before): bool {
        return business_test_throws(static fn() => business_read_dataset('customers'))
            && file_get_contents(business_dataset_path('customers')) === '{broken'
            && $before !== '{broken';
    });
    business_test($results, 'unsupported future dataset names are rejected', static fn(): bool =>
        business_test_throws(static fn() => business_read_dataset('../admin')));
    file_put_contents(business_dataset_path('receivables'), '{broken');
    business_test($results, 'malformed receivables JSON is rejected without reset', static fn(): bool =>
        business_test_throws(static fn() => business_read_dataset('receivables'))
            && file_get_contents(business_dataset_path('receivables')) === '{broken');
    file_put_contents(business_dataset_path('refilling-items'), '{broken');
    business_test($results, 'malformed refilling-items JSON is rejected without reset', static fn(): bool =>
        business_test_throws(static fn() => business_read_dataset('refilling-items'))
            && file_get_contents(business_dataset_path('refilling-items')) === '{broken');
    file_put_contents(business_dataset_path('certificates'), '{broken');
    business_test($results, 'malformed certificates JSON is rejected without reset', static fn(): bool =>
        business_test_throws(static fn() => business_read_dataset('certificates'))
            && file_get_contents(business_dataset_path('certificates')) === '{broken');
    foreach ($results as $label => $passed) fwrite(STDOUT, ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL);
    exit(in_array(false, $results, true) ? 1 : 0);
} finally {
    business_test_remove($root);
}
