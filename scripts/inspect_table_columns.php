<?php
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php inspect_table_columns.php <table_name>\n");
    exit(1);
}

$tableName = trim((string) $argv[1]);
if ($tableName === '') {
    fwrite(STDERR, "Table name is required.\n");
    exit(1);
}

require __DIR__ . '/../backend-php/config/db.php';

$stmt = $conn->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'dbo'
      AND TABLE_NAME = :table
    ORDER BY ORDINAL_POSITION
");
$stmt->execute([':table' => $tableName]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($rows as $row) {
    echo ($row['COLUMN_NAME'] ?? '') . PHP_EOL;
}
