<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/paths.php';

function password_reset_base_url(): string
{
    $url = app_password_reset_base_url();
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('Password recovery is unavailable.');
    }
    return $url;
}

function password_recovery_config(): array
{
    $config = require dirname(__DIR__) . '/config.php';
    if (!is_array($config)
        || !isset($config['recovery_email'])
        || !is_string($config['recovery_email'])
        || filter_var($config['recovery_email'], FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Password recovery is unavailable.');
    }
    return $config;
}

function send_password_reset_email(string $token, array $config): void
{
    require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
    $url = password_reset_base_url() . '?token=' . rawurlencode($token);
    $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = (string) $config['smtp_host'];
    $mailer->SMTPAuth = true;
    $mailer->Username = (string) $config['smtp_username'];
    $mailer->Password = (string) $config['smtp_password'];
    $mailer->Port = (int) $config['smtp_port'];
    $mailer->SMTPSecure = ($config['smtp_encryption'] ?? 'tls') === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom((string) $config['from_email'], (string) $config['from_name']);
    $mailer->addAddress((string) $config['recovery_email']);
    $mailer->isHTML(true);
    $mailer->Subject = 'Reset your Laxmikant Traders admin password';
    $mailer->Body = '<p>A password reset was requested for the Laxmikant Traders catalogue administrator.</p>'
        . '<p><a href="' . $safeUrl . '">Reset your password</a></p>'
        . '<p>This link expires in 30 minutes and can be used once. If you did not request it, ignore this email.</p>';
    $mailer->AltBody = "A password reset was requested for the Laxmikant Traders catalogue administrator.\n\n"
        . $url . "\n\nThis link expires in 30 minutes and can be used once. If you did not request it, ignore this email.";
    $mailer->send();
}
