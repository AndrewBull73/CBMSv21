<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$rows = array_values(is_array($rows ?? null) ? $rows : []);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-files me-2"></i>AI Knowledge Documents</h3>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=ai-knowledge/usage" class="btn btn-sm btn-outline-secondary"><i class="bi bi-bar-chart-line me-1"></i>Usage</a>
        <a href="index.php?route=ai-knowledge/upload" class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Upload</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <form class="row g-2 mb-3" method="get" action="index.php">
          <input type="hidden" name="route" value="ai-knowledge/documents">
          <div class="col-md-5"><input class="form-control form-control-sm" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search documents"></div>
          <div class="col-md-3">
            <select class="form-select form-select-sm" name="active">
              <option value="1" <?= (string) ($filters['active'] ?? '1') === '1' ? 'selected' : '' ?>>Active</option>
              <option value="0" <?= (string) ($filters['active'] ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
              <option value="" <?= (string) ($filters['active'] ?? '') === '' ? 'selected' : '' ?>>All</option>
            </select>
          </div>
          <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100" type="submit"><i class="bi bi-search me-1"></i>Filter</button></div>
        </form>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Document</th><th>Context</th><th>Audience</th><th class="text-end">Chunks</th><th></th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No knowledge documents found.</td></tr>
            <?php else: foreach ($rows as $row): ?>
              <tr>
                <td><div class="fw-semibold"><?= h((string) ($row['Title'] ?? '')) ?></div><div class="small text-muted"><?= h((string) ($row['FileName'] ?? '')) ?></div></td>
                <td><div><?= h((string) ($row['Category'] ?? '')) ?></div><div class="small text-muted"><?= h((string) ($row['Module'] ?? '')) ?> FY <?= h((string) ($row['FiscalYearID'] ?? 'All')) ?> / V <?= h((string) ($row['VersionID'] ?? 'All')) ?></div></td>
                <td><span class="badge text-bg-secondary"><?= h((string) ($row['AudienceCode'] ?? 'USER')) ?></span></td>
                <td class="text-end"><?= h((string) (int) ($row['ChunkCount'] ?? 0)) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="index.php?route=ai-knowledge/chunks&amp;id=<?= h((string) (int) ($row['DocumentID'] ?? 0)) ?>"><i class="bi bi-list-ul"></i></a>
                  <a class="btn btn-sm btn-outline-primary" href="index.php?route=ai-knowledge/upload&amp;replace_id=<?= h((string) (int) ($row['DocumentID'] ?? 0)) ?>" title="Replace or re-index this document"><i class="bi bi-arrow-repeat"></i></a>
                  <form class="d-inline" method="post" action="index.php?route=ai-knowledge/toggle-document">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="DocumentID" value="<?= h((string) (int) ($row['DocumentID'] ?? 0)) ?>">
                    <input type="hidden" name="IsActive" value="<?= (int) ($row['IsActive'] ?? 0) === 1 ? '0' : '1' ?>">
                    <button class="btn btn-sm btn-outline-<?= (int) ($row['IsActive'] ?? 0) === 1 ? 'danger' : 'success' ?>" type="submit">
                      <i class="bi bi-<?= (int) ($row['IsActive'] ?? 0) === 1 ? 'pause' : 'play' ?>"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
