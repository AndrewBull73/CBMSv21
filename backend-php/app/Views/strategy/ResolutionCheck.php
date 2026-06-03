<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$definitions = is_array($definitions ?? null) ? $definitions : [];
$filters = is_array($filters ?? null) ? $filters : [];
$result = is_array($result ?? null) ? $result : [];
$fiscalYearId = (int) ($fiscalYearId ?? 0);
$yearLabel = (string) ($context['YearLabel'] ?? '');
?>
<div class="container mt-4">
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Source Resolution Check</h3>
      <div class="d-flex flex-wrap gap-2">
        <a href="index.php?route=strategy-config/segment-mapping" class="btn btn-sm btn-outline-secondary">Segment Mapping</a>
        <a href="index.php?route=strategy-config/import-dashboard" class="btn btn-sm btn-outline-secondary">Import Dimensions</a>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Fiscal Year:
        <strong><?= h($yearLabel !== '' ? $yearLabel : ($fiscalYearId > 0 ? (string) $fiscalYearId : 'Not set')) ?></strong>
      </div>

      <form method="get" action="index.php" class="row g-3">
        <input type="hidden" name="route" value="strategy-config/resolution-check">
        <div class="col-md-3">
          <label class="form-label">Strategic Dimension</label>
          <select name="dimension_code" class="form-select form-select-sm" required>
            <option value="">Select dimension</option>
            <?php foreach ($definitions as $definition): ?>
              <?php $code = (string) ($definition['Code'] ?? ''); ?>
              <option value="<?= h($code) ?>" <?= (($filters['dimension_code'] ?? '') === $code) ? 'selected' : '' ?>>
                <?= h((string) ($definition['Label'] ?? $code)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Requested DataObjectCode</label>
          <input type="text" name="data_object_code" class="form-control form-control-sm" value="<?= h((string) ($filters['data_object_code'] ?? '')) ?>" placeholder="e.g. 302 or 30201" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Segment Code</label>
          <input type="text" name="segment_code" class="form-control form-control-sm" value="<?= h((string) ($filters['segment_code'] ?? '')) ?>" placeholder="e.g. PROG001" required>
        </div>
        <div class="col-md-3 d-grid">
          <label class="form-label">&nbsp;</label>
          <button type="submit" class="btn btn-sm btn-primary">Check Resolution</button>
        </div>
      </form>
      <div class="form-text mt-2">Resolution order is exact DataObjectCode, then nearest parent, then global <code>0</code>.</div>
    </div>
  </div>

  <?php if (!empty($result)): ?>
    <?php
      $status = (string) ($result['status'] ?? 'invalid');
      $badge = match ($status) {
          'resolved' => 'success',
          'unmapped' => 'warning',
          'not_found' => 'danger',
          default => 'secondary',
      };
      $resolutionType = (string) ($result['resolution_type'] ?? '');
    ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Resolution Result</h5>
        <span class="badge text-bg-<?= h($badge) ?>"><?= h(ucfirst($status)) ?></span>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <div class="small text-muted">Dimension</div>
            <div class="fw-semibold"><?= h((string) ($result['dimension_code'] ?? '')) ?></div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted">Mapped Segment</div>
            <div class="fw-semibold"><?= h((string) ($result['mapped_segment_no'] ?? '')) ?></div>
          </div>
          <div class="col-md-4">
            <div class="small text-muted">Resolution Type</div>
            <div class="fw-semibold"><?= h($resolutionType !== '' ? ucfirst($resolutionType) : 'Not resolved') ?></div>
          </div>
        </div>
        <div class="alert alert-info mb-3"><?= h((string) ($result['message'] ?? '')) ?></div>

        <div class="row g-4">
          <div class="col-lg-5">
            <h6>Requested Scope Chain</h6>
            <div class="list-group">
              <?php foreach (($result['requested_scope_chain'] ?? []) as $scope): ?>
                <div class="list-group-item">
                  <div class="fw-semibold"><?= h((string) ($scope['DataObjectCode'] ?? '')) ?></div>
                  <div class="small text-muted"><?= h((string) ($scope['DataObjectName'] ?? '')) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-lg-7">
            <h6>Matched Source Row</h6>
            <?php if (!empty($result['matched_row'])): ?>
              <div class="border rounded p-3 bg-light">
                <div><strong>Scope:</strong> <?= h((string) ($result['matched_row']['DataObjectCode'] ?? '')) ?> / <?= h((string) ($result['matched_row']['DataObjectName'] ?? '')) ?></div>
                <div><strong>Segment Code:</strong> <?= h((string) ($result['matched_row']['SegmentCode'] ?? '')) ?></div>
                <div><strong>Segment Name:</strong> <?= h((string) ($result['matched_row']['SegmentName'] ?? '')) ?></div>
                <div><strong>Parent Link:</strong> <?= h((string) ($result['matched_row']['ParentSegmentNo'] ?? '')) ?><?= !empty($result['matched_row']['ParentSegmentCode']) ? ' / ' . h((string) $result['matched_row']['ParentSegmentCode']) : '' ?></div>
              </div>
            <?php else: ?>
              <div class="text-muted">No matching source row was resolved.</div>
            <?php endif; ?>

            <h6 class="mt-4">Candidate Rows Seen In Scope</h6>
            <div class="table-responsive">
              <table class="table table-sm table-striped align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Scope</th>
                    <th>Name</th>
                    <th>Segment Code</th>
                    <th>Segment Name</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($result['candidates'])): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No candidate rows found in the requested scope chain.</td></tr>
                  <?php else: ?>
                    <?php foreach (($result['candidates'] ?? []) as $candidate): ?>
                      <tr>
                        <td><?= h((string) ($candidate['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($candidate['DataObjectName'] ?? '')) ?></td>
                        <td><?= h((string) ($candidate['SegmentCode'] ?? '')) ?></td>
                        <td><?= h((string) ($candidate['SegmentName'] ?? '')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
