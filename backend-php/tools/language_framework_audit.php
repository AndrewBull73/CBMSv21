<?php
declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Project root not found.\n");
    exit(1);
}

$langDir = $projectRoot . DIRECTORY_SEPARATOR . 'lang';
$viewRoot = $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views';
$controllerRoot = $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers';

if (!is_dir($langDir) || !is_dir($viewRoot) || !is_dir($controllerRoot)) {
    fwrite(STDERR, "Required project folders were not found.\n");
    exit(1);
}

$langFiles = glob($langDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
sort($langFiles);

$languagePacks = [];
foreach ($langFiles as $langFile) {
    $code = basename($langFile, '.php');
    $pack = require $langFile;
    $languagePacks[$code] = is_array($pack) ? $pack : [];
}

$defaultCode = array_key_exists('en', $languagePacks) ? 'en' : array_key_first($languagePacks);
$defaultPack = $defaultCode !== null ? ($languagePacks[$defaultCode] ?? []) : [];

$phpFiles = static function (string $root): array {
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
        $files[] = $path;
    }
    sort($files);

    return $files;
};

$countTranslationUsage = static function (array $files, string $projectRoot): array {
    $withTranslation = [];
    $withoutTranslation = [];

    foreach ($files as $file) {
        $content = (string) file_get_contents($file);
        $relative = str_replace('\\', '/', substr($file, strlen($projectRoot) + 1));
        if (strpos($content, '__t(') !== false) {
            $withTranslation[] = $relative;
        } else {
            $withoutTranslation[] = $relative;
        }
    }

    return [$withTranslation, $withoutTranslation];
};

$summarizeByModule = static function (array $files): array {
    $summary = [];
    foreach ($files as $relativePath) {
        $parts = explode('/', $relativePath);
        $module = $parts[2] ?? 'root';
        $summary[$module] = ($summary[$module] ?? 0) + 1;
    }
    arsort($summary);

    return $summary;
};

$findHardcodedLang = static function (array $files, string $projectRoot): array {
    $matches = [];
    foreach ($files as $file) {
        $content = (string) file_get_contents($file);
        if (strpos($content, '<html lang="en"') === false) {
            continue;
        }
        $matches[] = str_replace('\\', '/', substr($file, strlen($projectRoot) + 1));
    }

    return $matches;
};

$viewFiles = $phpFiles($viewRoot);
$controllerFiles = $phpFiles($controllerRoot);
[$viewsWithTranslation, $viewsWithoutTranslation] = $countTranslationUsage($viewFiles, $projectRoot);
[$controllersWithTranslation, $controllersWithoutTranslation] = $countTranslationUsage($controllerFiles, $projectRoot);
$hardcodedLangViews = $findHardcodedLang($viewFiles, $projectRoot);

echo "CBMSv21 Language Framework Audit\n";
echo "Project root: {$projectRoot}\n\n";

echo "Language Packs\n";
foreach ($languagePacks as $code => $pack) {
    $missingKeys = $defaultCode !== null && $code !== $defaultCode
        ? count(array_diff_key($defaultPack, $pack))
        : 0;
    echo str_pad($code, 8) . ' keys=' . count($pack);
    if ($code !== $defaultCode) {
        echo ' missing_vs_' . $defaultCode . '=' . $missingKeys;
    }
    echo PHP_EOL;
}

echo PHP_EOL . "Coverage Summary\n";
echo 'views_total=' . count($viewFiles) . PHP_EOL;
echo 'views_with_translation=' . count($viewsWithTranslation) . PHP_EOL;
echo 'views_without_translation=' . count($viewsWithoutTranslation) . PHP_EOL;
echo 'controllers_total=' . count($controllerFiles) . PHP_EOL;
echo 'controllers_with_translation=' . count($controllersWithTranslation) . PHP_EOL;
echo 'controllers_without_translation=' . count($controllersWithoutTranslation) . PHP_EOL;
echo 'note=static __t() usage only; runtime legacy literal translation is applied centrally by BaseController and shared public guards' . PHP_EOL;

echo PHP_EOL . "Top View Modules Without __t()\n";
foreach (array_slice($summarizeByModule($viewsWithoutTranslation), 0, 15, true) as $module => $count) {
    echo str_pad($module, 24) . $count . PHP_EOL;
}

echo PHP_EOL . "Hardcoded <html lang=\"en\"> In Views\n";
if ($hardcodedLangViews === []) {
    echo "(none)\n";
} else {
    foreach ($hardcodedLangViews as $relativePath) {
        echo $relativePath . PHP_EOL;
    }
}

echo PHP_EOL . "Sample Views Without __t()\n";
foreach (array_slice($viewsWithoutTranslation, 0, 25) as $relativePath) {
    echo $relativePath . PHP_EOL;
}
