<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

fwrite(STDOUT, <<<'PLAN'
Auth smoke-test checklist (never prints credentials or tokens):
1. Start PHP: php -S localhost:8000
2. Start Vite: npm run dev
3. Open http://localhost:5173/admin-login.html and sign in.
4. Confirm login, session status, CSRF-protected logout, and post-logout status.
5. Submit five bad passwords and confirm temporary HTTP 429 responses.
6. Temporarily increment credential_version in private/admin.php; the existing session must become unauthenticated.
7. For timeout checks, temporarily lower the constants in api/includes/session.php, sign in, wait, and query session.php.

Automated verification is performed during Phase 2 implementation with a disposable local credential.
PLAN);
