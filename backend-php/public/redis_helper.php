<?php
// redis_helper.php

require_once __DIR__ . '/../../vendor/autoload.php'; // Assuming Predis is installed via Composer

function getRedis() {
    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
        // 'password' => 'yourpass', // if needed
    ]);
    return $redis;
}

function buildKey($context, $keyName) {
    // $context = ['FormulaSetCode' => 'BUDGET-FY26', 'FiscalYearID' => 2026, 'VersionID' => 1, 'DataObjectCode' => 'CC001']
    return sprintf(
        'calc:%s:%d:%d:%s:%s',
        $context['FormulaSetCode'],
        $context['FiscalYearID'],
        $context['VersionID'],
        $context['DataObjectCode'],
        $keyName
    );
}

// Example usage (test in a separate file)
$redis = getRedis();
$context = [
    'FormulaSetCode' => 'BUDGET-FY26',
    'FiscalYearID' => 2026,
    'VersionID' => 1,
    'DataObjectCode' => 'CC001'
];
$key = buildKey($context, 'wages');
$redis->set($key, 100000); // Test set
echo $redis->get($key); // Test get