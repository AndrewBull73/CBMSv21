<?php
declare(strict_types=1);

use App\Shared\ScreenTestCatalog;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

require_once $root . '/app/Shared/ScreenTestCatalog.php';

$refresh = in_array('--refresh', $argv, true);
$seedPath = $root . '/config/screen_test_catalog_seed.php';
$customSeeds = is_file($seedPath) ? require $seedPath : [];
if (!is_array($customSeeds)) {
    fwrite(STDERR, "Screen test seed file did not return an array.\n");
    exit(1);
}

$catalog = array_replace(ScreenTestCatalog::builtIn(), $customSeeds);
$existingOverrides = ScreenTestCatalog::editableOverrides();
$created = 0;
$refreshed = 0;
$skipped = 0;

foreach ($catalog as $scenarioId => $scenario) {
    if (!is_array($scenario)) {
        continue;
    }

    $scenarioId = trim((string) ($scenario['id'] ?? $scenarioId));
    if ($scenarioId === '') {
        continue;
    }

    if (!$refresh && isset($existingOverrides[$scenarioId])) {
        $skipped++;
        continue;
    }

    ScreenTestCatalog::saveEditableScenario(array_merge(['id' => $scenarioId], $scenario));
    if (isset($existingOverrides[$scenarioId])) {
        $refreshed++;
    } else {
        $created++;
    }
}

echo "Screen test catalogue seeded.\n";
echo 'created=' . $created . "\n";
echo 'refreshed=' . $refreshed . "\n";
echo 'skipped_existing=' . $skipped . "\n";
echo 'catalog=' . ScreenTestCatalog::storagePath() . "\n";
echo $refresh
    ? "Mode: refresh existing seeded scripts.\n"
    : "Mode: add missing scripts only. Use --refresh to overwrite existing editable scripts with seed values.\n";
