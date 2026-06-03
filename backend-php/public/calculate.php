<?php
require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

// Connect to SQL Server
$conn = new PDO("sqlsrv:Server=YOUR_SERVER;Database=YOUR_DB", "USER", "PASS");

// Connect to Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Load formula
$json = $redis->get("formula:testCalc");
$formula = json_decode($json, true)['expression'];

// Load inputs from Redis
$qty  = $redis->hGet("input:1", "Qty");
$rate = $redis->hGet("input:1", "Rate");
$fx   = $redis->hGet("fx:AUD", "2026");

// Build variable map
$vars = [
    "Qty"    => $qty,
    "Rate"   => $rate,
    "FxRate" => $fx
];

// Substitute variables
$expression = $formula;
foreach ($vars as $key => $value) {
    $expression = str_replace($key, $value, $expression);
}

// Evaluate
$result = eval("return $expression;");

// Save result back to SQL
$stmt = $conn->prepare("UPDATE TestInputs SET Result = ? WHERE RecordId = 1");
$stmt->execute([$result]);

echo "<h1>Calculation Complete</h1>";
echo "<p>Formula: $formula</p>";
echo "<p>Expression: $expression</p>";
echo "<p><strong>Result: $result</strong></p>";
