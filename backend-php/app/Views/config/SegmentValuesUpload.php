<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$fiscalYears = is_array($fiscalYears ?? null) ? $fiscalYears : [];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-cloud-upload me-2"></i>Upload Segment Values</h3>
      </div>
      <a href="index.php?route=segment-values/downloadTemplate" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Download Template</a>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">Use the upload template when you need to create or update larger batches of segment values. Uploads match on fiscal year, Org Unit, segment number, and segment code.</div>

      <form method="post" action="index.php?route=segment-values/uploadProcess" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label">Override Fiscal Year</label>
          <select name="UploadFiscalYearID" class="form-select">
            <option value="">Use workbook value</option>
            <?php foreach ($fiscalYears as $fy): ?>
              <?php $fyId = (string) ($fy['FiscalYearID'] ?? ''); ?>
              <option value="<?= h($fyId) ?>" <?= ($ctxFy > 0 && (string) $ctxFy === $fyId) ? 'selected' : '' ?>>
                <?= h($fyId . ' - ' . (string) ($fy['YearLabel'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label">Spreadsheet File</label>
          <input class="form-control" type="file" name="uploadFile" accept=".xlsx,.xls,.csv" required>
        </div>

        <div class="d-flex justify-content-between">
          <a href="index.php?route=segment-values/list" class="btn btn-secondary">Back</a>
          <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-upload me-1"></i>Upload Spreadsheet</button>
        </div>
      </form>
    </div>
  </div>
</div>
