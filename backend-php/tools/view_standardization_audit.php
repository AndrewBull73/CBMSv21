<?php
declare(strict_types=1);

$root = realpath(__DIR__ . '/../app/Views');
if ($root === false || !is_dir($root)) {
    fwrite(STDERR, "View root not found.\n");
    exit(1);
}

$patterns = [
    'forms_total' => '/<form\b/i',
    'forms_missing_id' => '/<form\b(?![^>]*\bid=)/i',
    'buttons_total' => '/<button\b/i',
    'buttons_missing_id' => '/<button\b(?![^>]*\bid=)/i',
    'tables_total' => '/<table\b/i',
    'tables_missing_id' => '/<table\b(?![^>]*\bid=)/i',
    'screen_header_usage' => '/_ScreenCardHeader\.php/i',
];

$totals = array_fill_keys(array_keys($patterns), 0);
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
        continue;
    }
    if (strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $path = $fileInfo->getRealPath();
    if ($path === false) {
        continue;
    }

    $content = (string) file_get_contents($path);
    $relativePath = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $fileCounts = array_fill_keys(array_keys($patterns), 0);

    foreach ($patterns as $key => $pattern) {
        $count = preg_match_all($pattern, $content, $matches);
        $fileCounts[$key] = $count === false ? 0 : $count;
        $totals[$key] += $fileCounts[$key];
    }

    $files[$relativePath] = $fileCounts;
}

echo "CBMSv21 View Standardization Audit\n";
echo "Root: {$root}\n\n";

echo "Totals\n";
foreach ($totals as $key => $value) {
    echo str_pad($key, 24) . ': ' . $value . PHP_EOL;
}

echo PHP_EOL . "Top Files By Missing Form Ids\n";
$topFormFiles = $files;
uasort($topFormFiles, static fn(array $a, array $b): int => $b['forms_missing_id'] <=> $a['forms_missing_id']);
$formSlice = array_slice($topFormFiles, 0, 20, true);
foreach ($formSlice as $path => $counts) {
    if (($counts['forms_missing_id'] ?? 0) <= 0) {
        continue;
    }
    echo str_pad($path, 64) . ' ' . ($counts['forms_missing_id'] ?? 0) . PHP_EOL;
}

echo PHP_EOL . "Top Files By Missing Button Ids\n";
$topButtonFiles = $files;
uasort($topButtonFiles, static fn(array $a, array $b): int => $b['buttons_missing_id'] <=> $a['buttons_missing_id']);
$buttonSlice = array_slice($topButtonFiles, 0, 20, true);
foreach ($buttonSlice as $path => $counts) {
    if (($counts['buttons_missing_id'] ?? 0) <= 0) {
        continue;
    }
    echo str_pad($path, 64) . ' ' . ($counts['buttons_missing_id'] ?? 0) . PHP_EOL;
}

echo PHP_EOL . "Top Files By Missing Table Ids\n";
$topTableFiles = $files;
uasort($topTableFiles, static fn(array $a, array $b): int => $b['tables_missing_id'] <=> $a['tables_missing_id']);
$tableSlice = array_slice($topTableFiles, 0, 20, true);
foreach ($tableSlice as $path => $counts) {
    if (($counts['tables_missing_id'] ?? 0) <= 0) {
        continue;
    }
    echo str_pad($path, 64) . ' ' . ($counts['tables_missing_id'] ?? 0) . PHP_EOL;
}
