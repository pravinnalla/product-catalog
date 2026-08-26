<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog-storage.php';

final class UploadValidationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }
}

function upload_specs(): array
{
    return [
        'product' => ['directory' => 'products', 'prefix' => 'prd-', 'field' => 'image', 'dataset' => 'products', 'maxBytes' => 5 * 1024 * 1024],
        'supplier' => ['directory' => 'suppliers', 'prefix' => 'sup-', 'field' => 'logo', 'dataset' => 'suppliers', 'maxBytes' => 2 * 1024 * 1024],
    ];
}

function upload_spec(string $kind): array
{
    $spec = upload_specs()[$kind] ?? null;
    if (!is_array($spec)) throw new UploadValidationException('Invalid upload type.');
    return $spec;
}

function upload_public_root(): string
{
    return app_upload_root();
}

function upload_public_url_prefix(): string
{
    return app_upload_url_prefix();
}

function upload_directory(string $kind): string
{
    return upload_public_root() . DIRECTORY_SEPARATOR . upload_spec($kind)['directory'];
}

function upload_ensure_directory(string $kind): string
{
    $root = upload_public_root();
    $directory = upload_directory($kind);
    foreach ([$root, $directory] as $path) {
        if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to prepare runtime media storage.');
        }
    }
    return $directory;
}

function upload_validate_original_name(string $name): void
{
    $base = basename($name);
    if ($base !== $name || preg_match('/%(?:2e|2f|5c)/i', $name) === 1
        || preg_match('~^[^.\\\\/][^.\\\\/]*\.(?:jpe?g|png|webp)$~i', $name) !== 1) {
        throw new UploadValidationException('The selected file has an unsupported filename.', 415);
    }
}

function upload_validate_file(array $file, string $kind, bool $requireHttpUpload = true): array
{
    $spec = upload_spec($kind);
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new UploadValidationException('The selected image is too large.', 413);
        }
        throw new UploadValidationException('A valid image file is required.');
    }

    $temporary = $file['tmp_name'] ?? '';
    $size = $file['size'] ?? -1;
    $name = $file['name'] ?? '';
    if (!is_string($temporary) || !is_string($name) || !is_int($size) || $size <= 0 || !is_file($temporary)) {
        throw new UploadValidationException('A valid image file is required.');
    }
    if ($requireHttpUpload && !is_uploaded_file($temporary)) {
        throw new UploadValidationException('Invalid upload request.');
    }
    if ($size > $spec['maxBytes']) throw new UploadValidationException('The selected image is too large.', 413);
    upload_validate_original_name($name);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporary);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new UploadValidationException('Only JPEG, PNG, and WebP images are supported.', 415);
    }
    $expectedOriginal = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if (($mime === 'image/jpeg' && !in_array($expectedOriginal, ['jpg', 'jpeg'], true))
        || ($mime !== 'image/jpeg' && $expectedOriginal !== $extensions[$mime])) {
        throw new UploadValidationException('The file extension does not match the image type.', 415);
    }

    $image = @getimagesize($temporary);
    if (!is_array($image) || ($image['mime'] ?? '') !== $mime) {
        throw new UploadValidationException('The selected file is not a valid image.', 422);
    }
    $width = $image[0] ?? 0;
    $height = $image[1] ?? 0;
    if (!is_int($width) || !is_int($height) || $width < 1 || $height < 1 || $width > 5000 || $height > 5000) {
        throw new UploadValidationException('Image dimensions must not exceed 5000 × 5000 pixels.', 422);
    }

    return ['temporary' => $temporary, 'extension' => $extensions[$mime], 'mime' => $mime, 'width' => $width, 'height' => $height];
}

function upload_generate_filename(string $kind, string $extension): string
{
    $spec = upload_spec($kind);
    $directory = upload_ensure_directory($kind);
    for ($attempt = 0; $attempt < 32; $attempt++) {
        $filename = $spec['prefix'] . bin2hex(random_bytes(12)) . '.' . $extension;
        if (!file_exists($directory . DIRECTORY_SEPARATOR . $filename)) return $filename;
    }
    throw new RuntimeException('Unable to allocate a media filename.');
}

function upload_store(array $file, string $kind): array
{
    $validated = upload_validate_file($file, $kind);
    $filename = upload_generate_filename($kind, $validated['extension']);
    $target = upload_directory($kind) . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($validated['temporary'], $target)) {
        throw new RuntimeException('Unable to store the uploaded image.');
    }
    @chmod($target, 0644);
    return [
        'filename' => $filename,
        'url' => upload_public_url_prefix() . '/' . upload_spec($kind)['directory'] . '/' . $filename,
    ];
}

function upload_is_managed_filename(string $kind, string $filename): bool
{
    $prefix = preg_quote(upload_spec($kind)['prefix'], '/');
    return preg_match('/^' . $prefix . '[a-f0-9]{24}\.(?:jpg|png|webp)$/', $filename) === 1;
}

function upload_delete_if_unreferenced(string $kind, string $filename): bool
{
    if (!upload_is_managed_filename($kind, $filename)) {
        throw new UploadValidationException('The media reference is not runtime-managed.');
    }
    $spec = upload_spec($kind);
    $lock = catalog_acquire_mutation_lock();
    try {
        $catalog = catalog_read_all();
        if (upload_reference_count($catalog, $kind, $filename) > 0) return false;
        $path = upload_directory($kind) . DIRECTORY_SEPARATOR . $filename;
        $realDirectory = realpath(upload_directory($kind));
        $realPath = realpath($path);
        if ($realPath === false) return true;
        if ($realDirectory === false || dirname($realPath) !== $realDirectory || !is_file($realPath)) {
            throw new UploadValidationException('Invalid media reference.');
        }
        if (!@unlink($realPath)) throw new RuntimeException('Unable to remove unused runtime media.');
        return true;
    } finally {
        catalog_release_mutation_lock($lock);
    }
}

function upload_reference_count(array $catalog, string $kind, string $filename): int
{
    $spec = upload_spec($kind);
    return count(array_filter(
        $catalog[$spec['dataset']] ?? [],
        static fn(array $record): bool => ($record[$spec['field']] ?? '') === $filename
    ));
}
