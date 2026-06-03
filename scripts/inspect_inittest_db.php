<?php
declare(strict_types=1);

require __DIR__ . '/../backend-php/config/db.php';

$sql = "
    SELECT
        s.name + '.' + t.name AS TableName,
        SUM(p.rows) AS TotalRows
    FROM sys.tables t
    INNER JOIN sys.schemas s
        ON s.schema_id = t.schema_id
    INNER JOIN sys.partitions p
        ON p.object_id = t.object_id
       AND p.index_id IN (0, 1)
    GROUP BY s.name, t.name
    HAVING SUM(p.rows) > 0
    ORDER BY s.name, t.name
";

$stmt = $conn->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($rows as $row) {
    echo ($row['TableName'] ?? '') . '|' . ($row['TotalRows'] ?? '0') . PHP_EOL;
}
