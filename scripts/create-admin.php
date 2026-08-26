<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/includes/paths.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['email:', 'force', 'password-stdin']);
$path = app_private_root() . '/admin.php';
$force = array_key_exists('force', $options);

if (is_file($path) && !$force) {
    fwrite(STDERR, "Admin credentials already exist. Use --force to replace them.\n");
    exit(1);
}

$email = isset($options['email']) ? trim((string) $options['email']) : '';
if ($email === '') {
    fwrite(STDOUT, 'Admin email: ');
    $email = trim((string) fgets(STDIN));
}
$email = strtolower($email);
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Enter a valid admin email.\n");
    exit(1);
}

function read_password(string $prompt, bool $fromStdin): string
{
    fwrite(STDOUT, $prompt);
    if (!$fromStdin) {
        system('stty -echo');
        try {
            return rtrim((string) fgets(STDIN), "\r\n");
        } finally {
            system('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }
    }
    return rtrim((string) fgets(STDIN), "\r\n");
}

$fromStdin = array_key_exists('password-stdin', $options);
$password = read_password('Password: ', $fromStdin);
$confirmation = read_password('Confirm password: ', $fromStdin);
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}
if (strlen($password) < 12 || strlen($password) > 128
    || preg_match('/[a-z]/', $password) !== 1
    || preg_match('/[A-Z]/', $password) !== 1
    || preg_match('/\d/', $password) !== 1
    || preg_match('/[^a-zA-Z\d]/', $password) !== 1
) {
    fwrite(STDERR, "Password must be 12-128 characters with upper, lower, number, and symbol.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$password = $confirmation = '';
if ($hash === false) {
    fwrite(STDERR, "Unable to hash password.\n");
    exit(1);
}

$contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export([
    'admin_email' => $email,
    'password_hash' => $hash,
    'credential_version' => 1,
    'password_updated_at' => gmdate(DATE_ATOM),
], true) . ";\n";

if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
    fwrite(STDERR, "Unable to create private directory.\n");
    exit(1);
}
$temporary = tempnam(dirname($path), 'admin-');
if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write credentials.\n");
    exit(1);
}
chmod($temporary, 0600);
if (!rename($temporary, $path)) {
    @unlink($temporary);
    fwrite(STDERR, "Unable to install credentials.\n");
    exit(1);
}
chmod($path, 0600);
fwrite(STDOUT, "Admin credentials created securely.\n");
