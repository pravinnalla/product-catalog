<?php

declare(strict_types=1);

// Copy to config.php on the server and replace every placeholder.
return [
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'mailbox@example.com',
    'smtp_password' => 'replace-with-a-secret',
    'smtp_encryption' => 'tls', // tls (port 587) or ssl (port 465)
    'from_email' => 'mailbox@example.com',
    'from_name' => 'Laxmikant Traders',
    'to_email' => 'enquiry@example.com',
    'to_name' => 'Laxmikant Traders',
    'recovery_email' => 'recovery@example.com',
];
