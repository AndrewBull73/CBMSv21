<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$dimensions = is_array($dimensions ?? null) ? $dimensions : [];
$groups = is_array($groups ?? null) ? $groups : [];
$_csrf = $_csrf ?? csrf_token();
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-sliders2-vertical me-2"></i>Segments</h3>
        <div class="small text-muted mt-1">Maintain the base segment master definition used across CBMS structure and imports.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=segments/downloadTemplate" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Template</a>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#segmentsUploadModal"><i class="bi bi-upload me-1"></i>Upload</button>
        <a id="segments-create-btn" href="index.php?route=segments/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Segment</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="segments/list">
        <div class="col-md-4">
          <input class="form-control" type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search id, code, name, or dimension">
        </div>
        <div class="col-md-3">
          <select name="dimension" class="form-select">
            <option value="">All dimensions</option>
            <?php foreach ($dimensions as $dimension): ?>
              <option value="<?= h($dimension) ?>" <?= (($filters['dimension'] ?? '') === $dimension) ? 'selected' : '' ?>><?= h($dimension) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="group" class="form-select">
            <option value="">All groups</option>
            <?php foreach ($groups as $group): ?>
              <option value="<?= h($group) ?>" <?= (($filters['group'] ?? '') === $group) ? 'selected' : '' ?>><?= h($group) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-outline-primary">Filter</button>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Use this register to review the segment master structure. Open a segment to maintain its lengths, ranges, grouping, and attribute names.</div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Code</th>
              <th>Name</th>
              <th>Dimension</th>
              <th>Group</th>
              <th>Usage</th>
              <th>Range</th>
              <th>Length</th>
              <th>Values</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="10" class="text-center text-muted py-3">No segments found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?= (int) ($row['SegmentID'] ?? 0) ?></td>
                  <td><?= h((string) ($row['SegmentCode'] ?? '')) ?></td>
                  <td>
                    <div><?= h((string) ($row['SegmentName'] ?? '')) ?></div>
                    <div class="small text-muted">
                      <?= h((string) ($row['Type'] ?? '')) ?>
                      <?= !empty($row['Editable']) ? ' / Editable: ' . h((string) ($row['Editable'] ?? '')) : '' ?>
                      <?= !empty($row['Static']) ? ' / Static: ' . h((string) ($row['Static'] ?? '')) : '' ?>
                    </div>
                  </td>
                  <td><?= h((string) ($row['CBMSDimension'] ?? '')) ?></td>
                  <td><?= h((string) ($row['SegmentGroup'] ?? '')) ?></td>
                  <td>
                    <?php
                      $usage = [];
                      if ((int) ($row['UsedInFinancialAccount'] ?? 0) === 1) {
                          $usage[] = 'Financial';
                      }
                      if ((int) ($row['UsedInStrategicPlanning'] ?? 0) === 1) {
                          $usage[] = 'Strategic';
                      }
                    ?>
                    <?= h($usage !== [] ? implode(' / ', $usage) : '') ?>
                  </td>
                  <td>
                    <?php if (!empty($row['StartPoint']) || !empty($row['EndPoint'])): ?>
                      <?= h((string) ($row['StartPoint'] ?? '')) ?>-<?= h((string) ($row['EndPoint'] ?? '')) ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($row['MinLength']) || !empty($row['MaxLength'])): ?>
                      <?= h((string) ($row['MinLength'] ?? '')) ?>-<?= h((string) ($row['MaxLength'] ?? '')) ?>
                    <?php endif; ?>
                  </td>
                  <td><?= (int) ($row['ValueCount'] ?? 0) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="index.php?route=segments/form&id=<?= (int) ($row['SegmentID'] ?? 0) ?>">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="segmentsUploadModal" tabindex="-1" aria-labelledby="segmentsUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="segmentsUploadModalLabel">Upload Segments</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php?route=segments/uploadProcess" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= h((string) $_csrf) ?>">
          <div class="alert alert-light border small">
            Upload an Excel or CSV file using the <code>Segments</code> sheet. Start with the template download if you need the expected column order.
          </div>
          <div class="mb-3">
            <label for="segmentsUploadFile" class="form-label">Spreadsheet File</label>
            <input type="file" class="form-control" id="segmentsUploadFile" name="uploadFile" accept=".xlsx,.xls,.csv" required>
          </div>
          <div class="small text-muted">
            Required columns: <code>SegmentID</code>, <code>SegmentCode</code>, and <code>SegmentName</code>.
            Optional columns can be used for lengths, ranges, dimension mapping, usage flags, parent defaults, and display order.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Upload Spreadsheet</button>
        </div>
      </form>
    </div>
  </div>
</div>
