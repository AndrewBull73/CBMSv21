<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
}

$rows = is_array($rows ?? null) ? $rows : [];
$status = trim((string) ($status ?? ''));
$csrf = h(csrf_token());

$severityBadge = static function (mixed $severity): string {
    $map = [
        '1' => 'info',
        '2' => 'success',
        '3' => 'warning',
        '4' => 'danger',
        'info' => 'info',
        'success' => 'success',
        'warning' => 'warning',
        'danger' => 'danger',
    ];
    $key = strtolower(trim((string) $severity));
    $label = $map[$key] ?? 'info';
    $class = match ($label) {
        'success' => 'text-bg-success',
        'warning' => 'text-bg-warning',
        'danger' => 'text-bg-danger',
        default => 'text-bg-info',
    };

    return '<span class="badge ' . $class . '">' . h($label) . '</span>';
};
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-megaphone me-2"></i><?= h($title ?? 'System Messages') ?></strong>
    <a href="index.php?route=systemmessages/createForm" class="btn btn-sm btn-primary">
      <i class="bi bi-plus-circle me-1"></i>Create Message
    </a>
  </div>
  <div class="card-body">
    <form method="get" action="index.php" class="row g-2 mb-3">
      <input type="hidden" name="route" value="systemmessages/list">
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value=""<?= $status === '' ? ' selected' : '' ?>>All statuses</option>
          <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option>
          <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option>
          <option value="archived"<?= $status === 'archived' ? ' selected' : '' ?>>Archived</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <a href="index.php?route=systemmessages/list" class="btn btn-outline-secondary">
          <i class="bi bi-x-circle me-1"></i>Reset
        </a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Severity</th>
            <th>Scope</th>
            <th>Audience</th>
            <th>Window</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-4">No system messages found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $messageId = (int) ($row['MessageID'] ?? 0);
                $rowStatus = trim((string) ($row['Status'] ?? 'draft'));
                $scopeText = 'FY ' . (($row['FiscalYearID'] ?? null) !== null ? (string) $row['FiscalYearID'] : 'any')
                    . ' / Ver ' . (($row['VersionID'] ?? null) !== null ? (string) $row['VersionID'] : 'any')
                    . ' / Code ' . ((!empty($row['ScopeDataObjectCode'])) ? (string) $row['ScopeDataObjectCode'] : 'GLOBAL');
                $audienceText = !empty($row['ScopeDataObjectCode'])
                    ? ('Scoped to ' . (string) $row['ScopeDataObjectCode'] . ' + descendants')
                    : 'Global';
                if ((int)($row['RoleCount'] ?? 0) > 0) {
                    $audienceText .= ' / Roles ' . (int)($row['RoleCount'] ?? 0);
                }
              ?>
              <tr>
                <td><?= $messageId ?></td>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['Title'] ?? '')) ?></div>
                  <div class="small text-muted">Created <?= h((string) ($row['CreatedAtUTC'] ?? '')) ?></div>
                </td>
                <td><span class="badge text-bg-secondary"><?= h($rowStatus) ?></span></td>
                <td><?= $severityBadge($row['Severity'] ?? 'info') ?></td>
                <td><?= h($scopeText) ?></td>
                <td><?= h($audienceText) ?></td>
                <td>
                  <div class="small"><?= h((string) ($row['DeliveryStartUTC'] ?? '')) ?></div>
                  <div class="small text-muted"><?= h((string) ($row['DeliveryEndUTC'] ?? '-')) ?></div>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                    <a href="index.php?route=systemmessages/editForm&MessageID=<?= $messageId ?>" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    <a href="index.php?route=systemmessages/preview&MessageID=<?= $messageId ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-eye me-1"></i>Preview
                    </a>
                    <?php if ($rowStatus !== 'published'): ?>
                      <form method="post" action="index.php?route=systemmessages/setStatus" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="MessageID" value="<?= $messageId ?>">
                        <input type="hidden" name="Status" value="published">
                        <button type="submit" class="btn btn-sm btn-outline-success">
                          <i class="bi bi-megaphone me-1"></i>Publish
                        </button>
                      </form>
                    <?php endif; ?>
                    <?php if ($rowStatus !== 'draft'): ?>
                      <form method="post" action="index.php?route=systemmessages/setStatus" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="MessageID" value="<?= $messageId ?>">
                        <input type="hidden" name="Status" value="draft">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                          <i class="bi bi-save me-1"></i>Draft
                        </button>
                      </form>
                    <?php endif; ?>
                    <?php if ($rowStatus !== 'archived'): ?>
                      <form method="post" action="index.php?route=systemmessages/setStatus" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="MessageID" value="<?= $messageId ?>">
                        <input type="hidden" name="Status" value="archived">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                          <i class="bi bi-archive me-1"></i>Archive
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
