<?php
declare(strict_types=1);

ob_start(); // <— ensure no output hits the client before we set headers

$__reqStart = microtime(true);

// Request ID header + helper
require_once __DIR__ . '/../shared/request_id.php';
if (function_exists('cbms_request_id')) {
    $rid = cbms_request_id();
    if (!headers_sent()) {
        header('X-Request-ID: ' . $rid);
    }
}

// --- Security headers (safe defaults) ---
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');                      // prevent clickjacking
    header('X-Content-Type-Options: nosniff');                  // stop MIME sniffing
    header('Referrer-Policy: strict-origin-when-cross-origin'); // modern, privacy-friendly default
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
}

if (!headers_sent()) {
    header(
        "Content-Security-Policy-Report-Only: "
        . "default-src 'self'; "
        . "img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "font-src 'self' data:; "
        . "connect-src 'self'"
    );
}

// --- Small translated error renderer (404/405) ---
if (!function_exists('_et')) {
    function _et(string $key, string $fallback): string {
        return function_exists('__t') ? __t($key) : $fallback;
    }
}

function renderHttpError(int $code, string $titleKey, string $messageKey, ?string $detail = null): void {
    http_response_code($code);

    $title   = _et($titleKey, $code === 404 ? 'Not Found' : 'Method Not Allowed');
    $message = _et($messageKey, $code === 404
        ? 'We could not find the page you requested.'
        : 'The HTTP method is not allowed for this endpoint.');

    // If request id helper is available, show it
    $rid = function_exists('cbms_request_id') ? cbms_request_id() : null;

    // Minimal, self-contained box (no Bootstrap dependency)
    echo "<div style='max-width:640px;margin:2rem auto;padding:1rem;
                      border:1px solid #ced4da;border-radius:8px;
                      background:#f8f9fa;color:#212529;font-family:sans-serif'>
            <h2 style='margin:0 0 .5rem 0;'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h2>
            <p style='margin:.25rem 0 1rem 0;'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";

    if ($detail) {
        echo "<p style='margin:.25rem 0;color:#6c757d'><small>" .
             htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . "</small></p>";
    }
    if ($rid) {
        $refLabel = _et('reference_id', 'Reference ID');
        echo "<p style='margin:.25rem 0;color:#6c757d'><small>" .
             htmlspecialchars($refLabel, ENT_QUOTES, 'UTF-8') . ": " .
             htmlspecialchars($rid, ENT_QUOTES, 'UTF-8') . "</small></p>";
    }

    $home = _et('home', 'Home');
    echo "  <div style='margin-top:.75rem'>
              <a href='index.php?route=home/index'
                 style='display:inline-block;padding:.4rem .75rem;margin-right:.5rem;
                        background:#0d6efd;color:#fff;text-decoration:none;border-radius:4px'>{$home}</a>
            </div>
          </div>";
}


// Basic request context (safe to compute without DB)
$__method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
$__uri    = $_SERVER['REQUEST_URI']    ?? '';
$__route  = $_GET['route']             ?? '(unknown)';
$__ip     = $_SERVER['REMOTE_ADDR']    ?? '';

// Error reporting: never leak raw PHP errors directly from the entrypoint.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Composer autoloader so vendor libs (e.g., PhpSpreadsheet) resolve
$rootVendor = __DIR__ . '/../../vendor/autoload.php';   // C:\xampp\htdocs\CBMSv21\vendor\autoload.php
$localVendor = __DIR__ . '/../vendor/autoload.php';     // fallback if vendor/ exists under backend-php

if (file_exists($rootVendor)) {
    require_once $rootVendor;
} elseif (file_exists($localVendor)) {
    require_once $localVendor;
}


// Global handlers first
require_once __DIR__ . '/../shared/error_handler.php';

// Bootstrap (env, DB, logger, lang, session, etc.)
require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../shared/logger.php';
require_once __DIR__ . '/../shared/env.php';

register_shutdown_function(function () use ($__reqStart, $__method, $__uri, $__route, $__ip) {
    $durationMs = round((microtime(true) - $__reqStart) * 1000, 2);
    $status     = http_response_code() ?: 200;

    // Try to set timing headers BEFORE the buffer flushes
    if (!headers_sent()) {
        header('X-Response-Time: ' . $durationMs . ' ms');
        header('Server-Timing: total;dur=' . $durationMs);
    } else {
        if (function_exists('app_log')) {
            app_log('Response-time headers not set; headers already sent', [
                'file'    => __FILE__,
                'line'    => __LINE__,
                'time_ms' => $durationMs,
            ], 'debug');
        }
    }

    // Decide log level for request summary
    $threshold  = 1000; // 1s default if nothing else is configured
    $level      = ($durationMs >= $threshold) ? 'warn' : 'debug';

    if (function_exists('app_log')) {
        app_log('Request complete', [
            'method'         => $__method,
            'uri'            => $__uri,
            'route'          => $__route,
            'status'         => $status,
            'time_ms'        => $durationMs,
            'memory_peak_kb' => round(memory_get_peak_usage(true) / 1024),
            'ip'             => $__ip,
        ], $level);
    }

    // Now flush output buffer (so headers go out with the response)
    if (ob_get_level() > 0) {
        @ob_end_flush();
    }
});

// Tiny PSR-4 autoloader for App\*
//spl_autoload_register(function (string $class): void {
//    $prefix  = 'App\\';
//    $baseDir = __DIR__ . '/../app/';
//    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
 //       return;
//    }
//    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
//    $file     = $baseDir . $relative . '.php';
//    if (is_file($file)) {
//        require $file;
//    }
//});

// Optional: diagnostic ping
if (function_exists('app_log')) {
    app_log('diagnostic ping', ['uri' => $_SERVER['REQUEST_URI'] ?? ''], 'info');
}

// Routing
$routes = require __DIR__ . '/../config/routes.php';
$route  = trim((string)($_GET['route'] ?? ''), '/');
$map    = $routes[$route] ?? ($routes[''] ?? null);

if ($map === null || !str_contains($map, '@')) {
    http_response_code(404);
    echo "<div class='alert alert-danger' style='padding:1em;margin:1em;'><strong>Route not found</strong></div>";
    exit;
}

[$ctrl, $action] = explode('@', $map, 2);
$fqcn = 'App\\Controllers\\' . $ctrl;

if (!class_exists($fqcn)) {
    http_response_code(500);
    if (envFlag('APP_DEBUG', false)) {
        echo "<div class='alert alert-danger' style='padding:1em;margin:1em;'><strong>Controller not found:</strong> " . htmlspecialchars($fqcn, ENT_QUOTES, 'UTF-8') . "</div>";
    } else {
        renderFriendlyErrorBox(_et('application_error', 'Application Error'));
    }
    exit;
}

$controller = new $fqcn();

if (!method_exists($controller, $action)) {
    http_response_code(500);
    if (envFlag('APP_DEBUG', false)) {
        echo "<div class='alert alert-danger' style='padding:1em;margin:1em;'>
                <strong>Action not found:</strong> " . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . "
              </div>";
    } else {
        renderFriendlyErrorBox(_et('application_error', 'Application Error'));
    }
    exit;
}

try {
    // ✅ wrap controller execution in try/catch
    $controller->$action();
} catch (Throwable $e) {
    while (ob_get_level() > 0) { @ob_end_clean(); }

    http_response_code(500);
    app_log('Unhandled Exception', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ], 'error');

    if (!envFlag('APP_DEBUG', false)) {
        renderFriendlyErrorBox(_et('application_error', 'Application Error'));
        exit;
    }

    $refId = function_exists('cbms_request_id') ? cbms_request_id() : uniqid('ERR');

    echo "<!doctype html>
<html lang='" . htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8') . "'>
<head>
  <meta charset='utf-8'>
  <title>" . htmlspecialchars(_et('application_error', 'Application Error'), ENT_QUOTES, 'UTF-8') . "</title>
  <link href='assets/bootstrap.min.css' rel='stylesheet'>
  <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
  <div class='container py-5'>
    <div class='alert alert-danger shadow-sm border border-danger-subtle'>
      <h4 class='alert-heading'>
        <i class='bi bi-exclamation-triangle-fill me-2'></i>" . htmlspecialchars(_et('application_error', 'Application Error'), ENT_QUOTES, 'UTF-8') . "
      </h4>
      <p class='mb-2'>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>
      <hr class='my-3'>
      <p class='small text-muted mb-1'>File: " . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . " (line " . $e->getLine() . ")</p>
      <p class='small text-muted'>Reference ID: " . htmlspecialchars($refId, ENT_QUOTES, 'UTF-8') . "</p>
    </div>
    <a href='index.php?route=home/index' class='btn btn-outline-primary'>
      <i class='bi bi-house-door'></i> Back to Home
    </a>
  </div>
</body>
</html>";
    exit;
}



