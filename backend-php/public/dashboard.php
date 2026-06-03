<?php
require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

ob_start();
$cbmsSupersetGuestTokenEmbedded = true;
require __DIR__ . '/superset_guest_token.php';
$tokenJson = trim((string) ob_get_clean());
$token_data = json_decode($tokenJson, true);
$token = $token_data["token"] ?? null;

$dashboardUuid = trim((string) envStr('SUPERSET_DASHBOARD_UUID', 'ff4d2a46-9f8b-47fc-8d09-bd16a05112a4'));
$supersetBaseUrl = rtrim((string) envStr('SUPERSET_BASE_URL', 'http://localhost:8088'), '/');
$version = $_GET["VersionID"] ?? "5";
?>

<script src="superset-embedded.min.js"></script>


<div id="superset-dashboard" style="width:100%; height:1200px; border:2px solid red;">
    Loading dashboard…
</div>


<script>
SupersetEmbeddedSdk.embedDashboard({
    id: "<?= htmlspecialchars($dashboardUuid, ENT_QUOTES, 'UTF-8') ?>",
    supersetDomain: "<?= htmlspecialchars($supersetBaseUrl, ENT_QUOTES, 'UTF-8') ?>",
    mountPoint: document.getElementById("superset-dashboard"),
    token: "<?= htmlspecialchars((string) $token, ENT_QUOTES, 'UTF-8') ?>",
    dashboardUiConfig: {
        hideTabBar: true,
        hideTitle: true
    },
    urlParams: {
        "VersionID": "<?= htmlspecialchars((string) $version, ENT_QUOTES, 'UTF-8') ?>"
    }
});
</script>
