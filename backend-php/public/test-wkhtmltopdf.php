<?php
declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

ini_set('display_errors', '0');
set_time_limit(120);

// 1) Full path to wkhtmltopdf.exe (quotes handled below)
$bin = 'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe';

// 2) URL to the test page (adjust if your local URL differs)
$url = "http://localhost:8080/CBMSv21/backend-php/public/test_page.php";

// 3) Temp output path
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wkhtmltest_' . uniqid('', true) . '.pdf';

// Build command
$cmd = '"' . $bin . '"' .
       ' --quiet --disable-javascript --enable-local-file-access --disable-smart-shrinking ' .
       escapeshellarg($url) . ' ' . escapeshellarg($tmp);


// Run and capture output/error
$output = [];
$code   = 0;
exec($cmd . ' 2>&1', $output, $code);

// On error, show diagnostics
if ($code !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "wkhtmltopdf FAILED\n";
    echo "Exit code: $code\n";
    echo "Command: $cmd\n\n";
    echo implode("\n", $output);
    exit;
}

// Stream the PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="wkhtmltest.pdf"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
