<?php
require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

if (empty($cbmsSupersetGuestTokenEmbedded)) {
    header('Content-Type: application/json');
}

$dashboardUuid = trim((string) envStr('SUPERSET_DASHBOARD_UUID', ''));
$supersetBaseUrl = rtrim((string) envStr('SUPERSET_BASE_URL', 'http://localhost:8088'), '/');
$username = trim((string) envStr('SUPERSET_USERNAME', ''));
$password = (string) envStr('SUPERSET_PASSWORD', '');

if ($dashboardUuid === '' || $username === '' || $password === '') {
    http_response_code(500);
    echo json_encode([
        'error' => 'Superset guest token environment is not configured.',
    ]);
    exit;
}

$loginUrl = $supersetBaseUrl . '/api/v1/security/login';
$guestUrl = $supersetBaseUrl . '/api/v1/security/guest_token/';

// Step 1: login to get an access token.
$payload = json_encode([
    'username' => $username,
    'password' => $password,
    'provider' => 'db',
    'refresh' => true,
]);

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$loginError = '';
$loginStatus = 0;
$result = curl_exec($ch);
$loginStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($result === false) {
    $loginError = (string) curl_error($ch);
}
curl_close($ch);

$loginData = is_string($result) ? json_decode($result, true) : null;
$accessToken = is_array($loginData) ? (string) ($loginData['access_token'] ?? '') : '';
if ($accessToken === '') {
    http_response_code(502);
    echo json_encode([
        'error' => 'Superset login failed.',
        'status' => $loginStatus,
        'detail' => $loginError !== '' ? $loginError : 'No access token returned.',
    ]);
    exit;
}

// Step 2: request a guest token.
$payload = json_encode([
    'user' => ['username' => 'guest'],
    'resources' => [
        ['type' => 'dashboard', 'id' => $dashboardUuid],
    ],
    'rls' => [],
]);

$ch = curl_init($guestUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $accessToken,
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$guestStatus = 0;
$guestError = '';
$guest = curl_exec($ch);
$guestStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($guest === false) {
    $guestError = (string) curl_error($ch);
}
curl_close($ch);

if (!is_string($guest) || $guest === '') {
    http_response_code(502);
    echo json_encode([
        'error' => 'Superset guest token request failed.',
        'status' => $guestStatus,
        'detail' => $guestError !== '' ? $guestError : 'No guest token response returned.',
    ]);
    exit;
}

echo $guest;
