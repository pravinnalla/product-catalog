<?php

declare(strict_types=1);

/** @return array{name:string,address:string,email:string,phones:string} */
function company_public_profile(): array
{
    return [
        'name' => 'LAXMIKANT TRADERS',
        'address' => '266/7, Raviwar Peth, Near Rajendra Chowk, Solapur, Maharashtra - 413005',
        'email' => 'laxmikantj96@yahoo.in',
        'phones' => '7020209306 / 9325337307',
    ];
}

function company_pdf_logo_path(): ?string
{
    foreach ([
        dirname(__DIR__) . '/assets/laxmikant-traders-logo.png',
        dirname(__DIR__, 2) . '/src/assets/images/brands/laxmikant-traders-logo.png',
    ] as $path) if (is_file($path) && is_readable($path)) return $path;
    return null;
}
