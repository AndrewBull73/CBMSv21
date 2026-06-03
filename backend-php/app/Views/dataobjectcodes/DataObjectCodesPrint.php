<?php declare(strict_types=1);
/** @var array $rows */

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: "Segoe UI", Arial, sans-serif; font-size: 10pt; color: #333; }
    table { border-collapse: collapse; width: 100%; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 9.5pt; }
    th { background: #f2f2f2; text-align: left; }
    .center { text-align: center; }
  </style>
</head>
<body>
  <h2 style="text-align:center; margin:0;">Data Object Codes</h2>
  <table>
    <thead>
      <tr>
        <th>Code</th>
        <th>Name</th>
        <th>Parent</th>
        <th>Type ID</th>
        <th>Status</th>
        <th>Updated</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="center">No records found</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= h((string)$r['DataObjectCode']) ?></td>
            <td><?= h((string)$r['DataObjectName']) ?></td>
            <td><?= h((string)($r['DataObjectCodeParent'] ?? '')) ?></td>
            <td><?= (int)$r['DataObjectTypeID'] ?></td>
            <td><?= h((string)($r['DataObjectCodeStatus'] ?? '')) ?></td>
            <td><?= h((string)($r['DateUpdated'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
