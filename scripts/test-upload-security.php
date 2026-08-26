<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);
require_once dirname(__DIR__) . '/api/includes/upload.php';

$temporaryRoot = sys_get_temp_dir() . '/catalog-upload-test-' . bin2hex(random_bytes(6));
mkdir($temporaryRoot, 0700, true);
putenv('RUNTIME_MEDIA_ROOT=' . $temporaryRoot . '/uploads');
$results = [];

function fixture(string $path, string $format): void
{
    $image = imagecreatetruecolor(4, 4);
    imagefill($image, 0, 0, imagecolorallocate($image, 190, 30, 45));
    match ($format) {
        'jpg' => imagejpeg($image, $path, 85),
        'png' => imagepng($image, $path),
        'webp' => imagewebp($image, $path, 85),
    };
    imagedestroy($image);
}

function fileArray(string $path, string $name): array
{
    return ['error' => UPLOAD_ERR_OK, 'tmp_name' => $path, 'size' => filesize($path), 'name' => $name];
}

function expectValid(array &$results, string $label, array $file, string $kind): void
{
    try { upload_validate_file($file, $kind, false); $results[$label] = true; }
    catch (Throwable) { $results[$label] = false; }
}

function expectInvalid(array &$results, string $label, array $file, string $kind, int $status): void
{
    try { upload_validate_file($file, $kind, false); $results[$label] = false; }
    catch (UploadValidationException $exception) { $results[$label] = $exception->status === $status; }
    catch (Throwable) { $results[$label] = false; }
}

try {
    foreach (['jpg', 'png', 'webp'] as $extension) fixture($temporaryRoot . '/valid.' . $extension, $extension);
    expectValid($results, 'valid JPEG', fileArray($temporaryRoot . '/valid.jpg', 'image.jpg'), 'product');
    expectValid($results, 'valid PNG', fileArray($temporaryRoot . '/valid.png', 'image.png'), 'supplier');
    expectValid($results, 'valid WebP', fileArray($temporaryRoot . '/valid.webp', 'image.webp'), 'product');

    file_put_contents($temporaryRoot . '/text', 'not an image');
    file_put_contents($temporaryRoot . '/php', '<?php echo 1;');
    file_put_contents($temporaryRoot . '/svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    file_put_contents($temporaryRoot . '/empty', '');
    file_put_contents($temporaryRoot . '/malformed', "\x89PNG\r\n\x1a\ninvalid");
    expectInvalid($results, 'renamed PHP', fileArray($temporaryRoot . '/php', 'shell.jpg'), 'product', 415);
    expectInvalid($results, 'renamed text', fileArray($temporaryRoot . '/text', 'text.png'), 'product', 415);
    expectInvalid($results, 'SVG', fileArray($temporaryRoot . '/svg', 'image.svg'), 'product', 415);
    expectInvalid($results, 'empty file', fileArray($temporaryRoot . '/empty', 'empty.png'), 'product', 400);
    expectInvalid($results, 'malformed image', fileArray($temporaryRoot . '/malformed', 'bad.png'), 'product', 415);
    expectInvalid($results, 'double extension', fileArray($temporaryRoot . '/valid.jpg', 'shell.php.jpg'), 'product', 415);
    expectInvalid($results, 'traversal filename', fileArray($temporaryRoot . '/valid.jpg', '../image.jpg'), 'product', 415);
    expectInvalid($results, 'encoded traversal filename', fileArray($temporaryRoot . '/valid.jpg', '%2e%2e%2fimage.jpg'), 'product', 415);
    expectInvalid($results, 'wrong MIME extension', fileArray($temporaryRoot . '/valid.png', 'image.jpg'), 'product', 415);

    copy($temporaryRoot . '/valid.png', $temporaryRoot . '/below-limit.png');
    $handle = fopen($temporaryRoot . '/below-limit.png', 'ab');
    ftruncate($handle, 2 * 1024 * 1024 - 1); fclose($handle);
    expectValid($results, 'below supplier size limit', fileArray($temporaryRoot . '/below-limit.png', 'large.png'), 'supplier');
    copy($temporaryRoot . '/valid.png', $temporaryRoot . '/above-limit.png');
    $handle = fopen($temporaryRoot . '/above-limit.png', 'ab');
    ftruncate($handle, 2 * 1024 * 1024 + 1); fclose($handle);
    expectInvalid($results, 'above supplier size limit', fileArray($temporaryRoot . '/above-limit.png', 'large.png'), 'supplier', 413);

    $oversizedDimensions = file_get_contents($temporaryRoot . '/valid.png');
    $oversizedDimensions = substr_replace($oversizedDimensions, pack('NN', 5001, 5001), 16, 8);
    file_put_contents($temporaryRoot . '/dimensions.png', $oversizedDimensions);
    expectInvalid($results, 'excessive dimensions', fileArray($temporaryRoot . '/dimensions.png', 'dimensions.png'), 'product', 422);

    $managed = upload_generate_filename('product', 'png');
    $results['generated filename'] = upload_is_managed_filename('product', $managed) && !str_contains($managed, 'image');
    $sharedCatalog = ['products' => [['image' => $managed], ['image' => $managed]]];
    $results['shared reference count'] = upload_reference_count($sharedCatalog, 'product', $managed) === 2;
    file_put_contents(upload_directory('product') . DIRECTORY_SEPARATOR . $managed, 'fixture');
    $results['unreferenced cleanup'] = upload_delete_if_unreferenced('product', $managed)
        && !file_exists(upload_directory('product') . DIRECTORY_SEPARATOR . $managed);

    $failed = array_keys(array_filter($results, static fn(bool $passed): bool => !$passed));
    foreach ($results as $label => $passed) echo ($passed ? 'PASS ' : 'FAIL ') . $label . "\n";
    exit($failed === [] ? 0 : 1);
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporaryRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    @rmdir($temporaryRoot);
}
