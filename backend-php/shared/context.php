<?php
declare(strict_types=1);

namespace App\Shared;

require_once __DIR__ . '/SessionHelper.php';

/**
 * Global accessor for current fiscal context.
 * Returns: ['fy' => int, 'ver' => int]
 */
function ctx(): array
{
    return [
        'fy'  => (int) (SessionHelper::get('FiscalYearID') ?? 0),
        'ver' => (int) (SessionHelper::get('VersionID')    ?? 0),
    ];
}
