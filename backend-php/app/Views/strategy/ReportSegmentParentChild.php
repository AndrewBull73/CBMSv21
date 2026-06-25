<?php
declare(strict_types=1);

/** @var array $contextLabels */
/** @var array $summary */
/** @var array $segmentOptions */
/** @var array $issueCounts */
/** @var array $issues */
/** @var array $resolvedLinks */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('segmentParentChildIssueGuidance')) {
    function segmentParentChildIssueGuidance(string $issueType): array
    {
        return match ($issueType) {
            'Child parent segment number is missing or wrong' => [
                'meaning' => 'The child value is not pointing to the parent segment selected at the top of the screen.',
                'next' => 'Check ParentSegmentNo on the child row, then reload or correct the segment value parent fields.',
            ],
            'Child parent segment code is missing' => [
                'meaning' => 'The child value has no parent segment code, so the application cannot find the parent row.',
                'next' => 'Populate ParentSegmentCode for the child row from the source segment data.',
            ],
            'ParentSegmentValueID is missing' => [
                'meaning' => 'The child has parent code fields, but the direct ParentSegmentValueID link has not been resolved.',
                'next' => 'Use Resolve Parent Links. If it remains missing, check that the parent value exists in the expected DataObjectCode scope.',
            ],
            'No active matching parent row' => [
                'meaning' => 'No active parent segment value matches the child parent code in the selected scope.',
                'next' => 'Confirm the parent segment value has been loaded, is active, and uses the correct DataObjectCode.',
            ],
            'Multiple active matching parent rows' => [
                'meaning' => 'More than one active parent row can match this child link, so the relationship is ambiguous.',
                'next' => 'Check duplicate parent codes and ParentSegmentDataObjectCode so only one parent row is valid for the child.',
            ],
            'Duplicate active segment key' => [
                'meaning' => 'More than one active row has the same FiscalYearID, DataObjectCode, SegmentNo, and SegmentCode.',
                'next' => 'Deactivate or remove duplicate segment values before resolving parent links.',
            ],
            'ParentSegmentValueID points to a different parent' => [
                'meaning' => 'The direct parent ID points to a row that does not match the parent segment number/code fields.',
                'next' => 'Use Resolve Parent Links to refresh the ID, then review the parent code fields if the warning remains.',
            ],
            'Child DataObjectCode does not start with parent DataObjectCode' => [
                'meaning' => 'The resolved parent is outside the expected DataObjectCode prefix relationship.',
                'next' => 'Review whether the parent DataObjectCode scope is correct, or run the check with prefix checking ignored if this relationship is valid.',
            ],
            default => [
                'meaning' => 'This row failed one of the parent-child validation checks.',
                'next' => 'Review the child value, parent link fields, and matching parent count shown below.',
            ],
        };
    }
}

if (!function_exists('segmentParentChildSqlLiteral')) {
    function segmentParentChildSqlLiteral($value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        return "N'" . str_replace("'", "''", (string) $value) . "'";
    }
}

if (!function_exists('segmentParentChildDebugSql')) {
    function segmentParentChildDebugSql(array $row): string
    {
        $fiscalYearId = (int) ($row['FiscalYearID'] ?? 0);
        $segmentValueId = (int) ($row['SegmentValueID'] ?? 0);
        $dataObjectCode = segmentParentChildSqlLiteral($row['DataObjectCode'] ?? null);
        $childSegmentNo = (int) ($row['SegmentNo'] ?? 0);
        $childSegmentCode = segmentParentChildSqlLiteral($row['SegmentCode'] ?? null);
        $parentSegmentNo = (int) ($row['ParentSegmentNo'] ?? 0);
        $parentSegmentCode = segmentParentChildSqlLiteral($row['ParentSegmentCode'] ?? null);

        return <<<SQL
DECLARE @FiscalYearID int = {$fiscalYearId};
DECLARE @ChildSegmentValueID int = {$segmentValueId};
DECLARE @ChildDataObjectCode nvarchar(50) = {$dataObjectCode};
DECLARE @ChildSegmentNo int = {$childSegmentNo};
DECLARE @ChildSegmentCode nvarchar(50) = {$childSegmentCode};
DECLARE @ParentSegmentNo int = {$parentSegmentNo};
DECLARE @ParentSegmentCode nvarchar(50) = {$parentSegmentCode};

SELECT
    RecordType = 'Child',
    sv.*
FROM dbo.tblSegmentValues sv
WHERE sv.SegmentValueID = @ChildSegmentValueID;

SELECT
    RecordType = 'Child by key',
    sv.*
FROM dbo.tblSegmentValues sv
WHERE sv.FiscalYearID = @FiscalYearID
  AND sv.DataObjectCode = @ChildDataObjectCode
  AND sv.SegmentNo = @ChildSegmentNo
  AND sv.SegmentCode = @ChildSegmentCode
ORDER BY sv.ActiveFlag DESC, sv.SegmentValueID;

;WITH selected_key AS (
    SELECT
        FiscalYearID,
        DataObjectCode,
        SegmentNo,
        SegmentCode
    FROM dbo.tblSegmentValues
    WHERE SegmentValueID = @ChildSegmentValueID
)
SELECT
    RecordType = 'All active duplicate rows for selected key',
    duplicate.*
FROM selected_key k
INNER JOIN dbo.tblSegmentValues duplicate
    ON duplicate.FiscalYearID = k.FiscalYearID
   AND ISNULL(duplicate.DataObjectCode, N'') = ISNULL(k.DataObjectCode, N'')
   AND duplicate.SegmentNo = k.SegmentNo
   AND ISNULL(duplicate.SegmentCode, N'') = ISNULL(k.SegmentCode, N'')
   AND duplicate.ActiveFlag = 1
ORDER BY duplicate.DataObjectCode, duplicate.SegmentNo, duplicate.SegmentCode, duplicate.SegmentValueID;

SELECT
    RecordType = 'Possible parent',
    parent.*
FROM dbo.tblSegmentValues parent
WHERE parent.FiscalYearID = @FiscalYearID
  AND parent.SegmentNo = @ParentSegmentNo
  AND parent.SegmentCode = @ParentSegmentCode
ORDER BY
    CASE WHEN parent.DataObjectCode = @ChildDataObjectCode THEN 0 ELSE 1 END,
    parent.ActiveFlag DESC,
    parent.DataObjectCode,
    parent.SegmentValueID;

SELECT
    RecordType = 'Resolved parent by ParentSegmentValueID',
    parent.*
FROM dbo.tblSegmentValues child
INNER JOIN dbo.tblSegmentValues parent
    ON parent.SegmentValueID = child.ParentSegmentValueID
WHERE child.SegmentValueID = @ChildSegmentValueID;
SQL;
    }
}

$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$summary = is_array($summary ?? null) ? $summary : [];
$segmentOptions = is_array($segmentOptions ?? null) ? $segmentOptions : [];
$issueCounts = is_array($issueCounts ?? null) ? $issueCounts : [];
$issues = is_array($issues ?? null) ? $issues : [];
$resolvedLinks = is_array($resolvedLinks ?? null) ? $resolvedLinks : [];

$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$parentSegmentNo = (int) ($summary['parent_segment_no'] ?? 0);
$childSegmentNo = (int) ($summary['child_segment_no'] ?? 0);
$requireSameDataObjectCode = (int) ($summary['require_same_data_object_code'] ?? 1) === 1;
$checkCodePrefix = (int) ($summary['check_code_prefix'] ?? 1) === 1;
$ready = (bool) ($summary['ready'] ?? false);

$segmentLabelByNo = [];
foreach ($segmentOptions as $option) {
    $segmentLabelByNo[(int) ($option['SegmentNo'] ?? 0)] = (string) ($option['SegmentName'] ?? '');
}

$screenHeader = [
    'title' => 'Segment Parent-Child Check',
    'icon' => 'bi-diagram-2',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 align-items-end mb-4">
        <div class="col-lg-10">
          <form method="get" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="route" value="strategy-reports/segment-parent-child">
            <div class="col-md-3">
              <label class="form-label">Parent Segment</label>
              <select name="parent_segment_no" class="form-select">
                <option value="" <?= $parentSegmentNo <= 0 ? 'selected' : '' ?>>Select parent segment</option>
                <?php foreach ($segmentOptions as $option): ?>
                  <?php $segmentNo = (int) ($option['SegmentNo'] ?? 0); ?>
                  <option value="<?= $segmentNo ?>" <?= $segmentNo === $parentSegmentNo ? 'selected' : '' ?>>
                    <?= h((string) $segmentNo . ' - ' . (string) ($option['SegmentName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Child Segment</label>
              <select name="child_segment_no" class="form-select">
                <option value="" <?= $childSegmentNo <= 0 ? 'selected' : '' ?>>Select child segment</option>
                <?php foreach ($segmentOptions as $option): ?>
                  <?php $segmentNo = (int) ($option['SegmentNo'] ?? 0); ?>
                  <option value="<?= $segmentNo ?>" <?= $segmentNo === $childSegmentNo ? 'selected' : '' ?>>
                    <?= h((string) $segmentNo . ' - ' . (string) ($option['SegmentName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Match Scope</label>
              <select name="same_data_object" class="form-select">
                <option value="1" <?= $requireSameDataObjectCode ? 'selected' : '' ?>>DataObject hierarchy</option>
                <option value="0" <?= !$requireSameDataObjectCode ? 'selected' : '' ?>>Any DataObjectCode</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">DataObject Prefix</label>
              <select name="check_prefix" class="form-select">
                <option value="1" <?= $checkCodePrefix ? 'selected' : '' ?>>Check</option>
                <option value="0" <?= !$checkCodePrefix ? 'selected' : '' ?>>Ignore</option>
              </select>
            </div>
            <div class="col-md-1 d-grid">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-arrow-repeat"></i>
              </button>
            </div>
          </form>
        </div>
        <div class="col-lg-2 d-grid">
          <form method="post" action="index.php?route=strategy-reports/segment-parent-child/resolve-parent-links">
            <?= csrf_field() ?>
            <input type="hidden" name="parent_segment_no" value="<?= $parentSegmentNo ?>">
            <input type="hidden" name="child_segment_no" value="<?= $childSegmentNo ?>">
            <input type="hidden" name="same_data_object" value="<?= $requireSameDataObjectCode ? '1' : '0' ?>">
            <input type="hidden" name="check_prefix" value="<?= $checkCodePrefix ? '1' : '0' ?>">
            <button type="submit" class="btn btn-outline-secondary w-100">
              <i class="bi bi-link-45deg me-1"></i>Resolve Parent Links
            </button>
          </form>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Parent rows</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['parent_rows'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Child rows</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['child_rows'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100 <?= $ready ? 'border-success' : 'border-warning' ?>">
            <div class="card-body">
              <div class="text-muted small">Status</div>
              <div class="fs-6 fw-semibold"><?= $ready ? 'Ready' : 'Review' ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100 border-danger">
            <div class="card-body">
              <div class="text-muted small">Errors</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['error_rows'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100 border-warning">
            <div class="card-body">
              <div class="text-muted small">Warnings</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['warning_rows'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Issue rows</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['issue_rows'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert <?= $ready ? 'alert-success' : 'alert-info' ?> border-0 shadow-sm mb-4">
        <?php if ($parentSegmentNo <= 0 || $childSegmentNo <= 0): ?>
          Select a parent segment and child segment to run the check.
        <?php else: ?>
          Checking child segment
          <strong><?= h((string) $childSegmentNo . ' - ' . ($segmentLabelByNo[$childSegmentNo] ?? '')) ?></strong>
          against parent segment
          <strong><?= h((string) $parentSegmentNo . ' - ' . ($segmentLabelByNo[$parentSegmentNo] ?? '')) ?></strong>.
          <?php if ($requireSameDataObjectCode): ?>
            Parent rows may resolve from the child DataObjectCode, one of its parent DataObjectCodes, or the parent link code scope.
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Issue Summary</h5>
        </div>
        <div class="card-body">
          <?php if ($parentSegmentNo <= 0 || $childSegmentNo <= 0): ?>
            <p class="text-muted mb-0">No check has been selected yet.</p>
          <?php elseif ($issueCounts === []): ?>
            <p class="text-muted mb-0">No parent-child issues were found for the selected segments.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Severity</th>
                    <th>Issue</th>
                    <th class="text-end">Rows</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($issueCounts as $row): ?>
                  <?php $severity = strtoupper((string) ($row['Severity'] ?? '')); ?>
                  <tr>
                    <td>
                      <span class="badge <?= $severity === 'ERROR' ? 'text-bg-danger' : 'text-bg-warning' ?>">
                        <?= h($severity) ?>
                      </span>
                    </td>
                    <td><?= h((string) ($row['IssueType'] ?? '')) ?></td>
                    <td class="text-end"><?= (int) ($row['IssueRows'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Issue Details</h5>
        </div>
        <div class="card-body">
          <?php if ($issues === []): ?>
            <p class="text-muted mb-0">No detailed issue rows to show.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Severity</th>
                    <th>Issue</th>
                    <th>DataObjectCode</th>
                    <th>Child</th>
                    <th>Parent Link</th>
                    <th class="text-end">Matches</th>
                    <th>Detail</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($issues as $row): ?>
                  <?php
                    $severity = strtoupper((string) ($row['Severity'] ?? ''));
                    $issueType = (string) ($row['IssueType'] ?? '');
                    $guidance = segmentParentChildIssueGuidance($issueType);
                  ?>
                  <tr>
                    <td>
                      <span class="badge <?= $severity === 'ERROR' ? 'text-bg-danger' : 'text-bg-warning' ?>">
                        <?= h($severity) ?>
                      </span>
                    </td>
                    <td>
                      <details>
                        <summary class="fw-semibold text-primary" style="cursor: pointer;">
                          <?= h($issueType) ?>
                        </summary>
                        <div class="mt-2 small">
                          <div class="mb-2">
                            <span class="fw-semibold">What this means:</span>
                            <?= h((string) $guidance['meaning']) ?>
                          </div>
                          <div class="mb-2">
                            <span class="fw-semibold">Next step:</span>
                            <?= h((string) $guidance['next']) ?>
                          </div>
                          <dl class="row mb-0">
                            <dt class="col-sm-5">SegmentValueID</dt>
                            <dd class="col-sm-7"><?= h((string) ($row['SegmentValueID'] ?? '')) ?></dd>
                            <dt class="col-sm-5">Child SegmentNo</dt>
                            <dd class="col-sm-7"><?= h((string) ($row['SegmentNo'] ?? '')) ?></dd>
                            <dt class="col-sm-5">Child SegmentCode</dt>
                            <dd class="col-sm-7"><?= h((string) ($row['SegmentCode'] ?? '')) ?></dd>
                            <dt class="col-sm-5">Parent SegmentNo</dt>
                            <dd class="col-sm-7"><?= h((string) ($row['ParentSegmentNo'] ?? '')) ?></dd>
                            <dt class="col-sm-5">Parent SegmentCode</dt>
                            <dd class="col-sm-7"><?= h((string) ($row['ParentSegmentCode'] ?? '')) ?></dd>
                            <dt class="col-sm-5">Matching parents</dt>
                            <dd class="col-sm-7"><?= h((string) ($row['MatchingParentCount'] ?? '')) ?></dd>
                          </dl>
                          <div class="mt-3">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary mb-2"
                                    data-parent-child-lookup
                                    data-segment-value-id="<?= (int) ($row['SegmentValueID'] ?? 0) ?>">
                              View Records
                            </button>
                            <div class="fw-semibold mb-1">SSMS lookup SQL</div>
                            <textarea class="form-control font-monospace small" rows="30" readonly><?= h(segmentParentChildDebugSql($row)) ?></textarea>
                          </div>
                        </div>
                      </details>
                    </td>
                    <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['SegmentCode'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['SegmentName'] ?? '')) ?></div>
                    </td>
                    <td>
                      <div><?= h((string) ($row['ParentSegmentNo'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ParentSegmentCode'] ?? '')) ?></div>
                    </td>
                    <td class="text-end"><?= h((string) ($row['MatchingParentCount'] ?? '')) ?></td>
                    <td><?= h((string) ($row['Detail'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Resolved Parent Links</h5>
        </div>
        <div class="card-body">
          <?php if ($resolvedLinks === []): ?>
            <p class="text-muted mb-0">No active child rows are in scope for this check.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>DataObjectCode</th>
                    <th>Child</th>
                    <th>Parent Link</th>
                    <th>Resolved Parent</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($resolvedLinks as $row): ?>
                  <tr>
                    <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['ChildSegmentCode'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ChildSegmentName'] ?? '')) ?></div>
                    </td>
                    <td>
                      <div><?= h((string) ($row['ParentSegmentNo'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ParentSegmentCode'] ?? '')) ?></div>
                    </td>
                    <td>
                      <?php if (!empty($row['ParentSegmentValueID'])): ?>
                        <div class="fw-semibold"><?= h((string) ($row['ParentSegmentCode'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($row['ParentDataObjectCode'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($row['ParentSegmentName'] ?? '')) ?></div>
                      <?php else: ?>
                        <span class="text-danger">Not resolved</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="segmentParentChildLookupModal" tabindex="-1" aria-labelledby="segmentParentChildLookupTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="segmentParentChildLookupTitle">Issue Record Lookup</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="segmentParentChildLookupStatus" class="text-muted">Select an issue row to load records.</div>
        <div id="segmentParentChildLookupBody"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="segmentParentChildDeleteConfirmModal" tabindex="-1" aria-labelledby="segmentParentChildDeleteConfirmTitle" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="segmentParentChildDeleteConfirmTitle">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Delete this duplicate segment value?</p>
        <div class="small text-muted" id="segmentParentChildDeleteConfirmText"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="segmentParentChildDeleteConfirmButton">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const modalEl = document.getElementById('segmentParentChildLookupModal');
  const statusEl = document.getElementById('segmentParentChildLookupStatus');
  const bodyEl = document.getElementById('segmentParentChildLookupBody');
  const titleEl = document.getElementById('segmentParentChildLookupTitle');
  const deleteConfirmEl = document.getElementById('segmentParentChildDeleteConfirmModal');
  const deleteConfirmTextEl = document.getElementById('segmentParentChildDeleteConfirmText');
  const deleteConfirmButton = document.getElementById('segmentParentChildDeleteConfirmButton');
  const csrfToken = <?= json_encode(csrf_token()) ?>;
  let currentLookupSegmentValueId = '';
  let pendingDeleteSegmentValueId = '';

  if (!modalEl || !statusEl || !bodyEl || !titleEl || !deleteConfirmEl || !deleteConfirmTextEl || !deleteConfirmButton) {
    return;
  }

  const labels = {
    child: 'Selected Child Row',
    child_key_matches: 'Child Rows With Same Key',
    active_duplicate_rows: 'Active Duplicate Rows',
    possible_parent_rows: 'Possible Parent Rows',
    resolved_parent_rows: 'Resolved Parent Rows'
  };

  function appendText(parent, tagName, text, className) {
    const el = document.createElement(tagName);
    if (className) {
      el.className = className;
    }
    el.textContent = text;
    parent.appendChild(el);
    return el;
  }

  function renderTable(key, rows) {
    const section = document.createElement('section');
    section.className = 'mb-4';
    appendText(section, 'h6', labels[key] || key, 'fw-semibold mb-2');

    if (!Array.isArray(rows) || rows.length === 0) {
      appendText(section, 'p', 'No rows found.', 'text-muted small mb-0');
      return section;
    }

    const columns = Object.keys(rows[0] || {});
    const wrapper = document.createElement('div');
    wrapper.className = 'table-responsive';
    const table = document.createElement('table');
    table.className = 'table table-sm table-bordered table-hover align-middle mb-0';
    const thead = document.createElement('thead');
    thead.className = 'table-light';
    const headRow = document.createElement('tr');
    const showDeleteActions = key === 'child_key_matches'
      && rows.filter(function (row) {
        return String(row.ActiveFlag) === '1';
      }).length > 1;

    if (showDeleteActions) {
      appendText(headRow, 'th', 'Actions');
    }
    columns.forEach(function (column) {
      appendText(headRow, 'th', column);
    });
    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    rows.forEach(function (row) {
      const tr = document.createElement('tr');
      if (showDeleteActions) {
        const actionTd = document.createElement('td');
        if (String(row.ActiveFlag) === '1') {
          const deleteButton = document.createElement('button');
          deleteButton.type = 'button';
          deleteButton.className = 'btn btn-sm btn-outline-danger';
          deleteButton.textContent = 'Delete';
          deleteButton.setAttribute('data-parent-child-delete-row', '1');
          deleteButton.setAttribute('data-segment-value-id', row.SegmentValueID === null || row.SegmentValueID === undefined ? '' : String(row.SegmentValueID));
          deleteButton.setAttribute('data-segment-code', row.SegmentCode === null || row.SegmentCode === undefined ? '' : String(row.SegmentCode));
          deleteButton.setAttribute('data-data-object-code', row.DataObjectCode === null || row.DataObjectCode === undefined ? '' : String(row.DataObjectCode));
          actionTd.appendChild(deleteButton);
        }
        tr.appendChild(actionTd);
      }
      columns.forEach(function (column) {
        const td = document.createElement('td');
        td.textContent = row[column] === null || row[column] === undefined ? '' : String(row[column]);
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });

    table.appendChild(tbody);
    wrapper.appendChild(table);
    section.appendChild(wrapper);
    return section;
  }

  function showModal() {
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
      return;
    }

    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
  }

  function showDeleteConfirmModal() {
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(deleteConfirmEl).show();
      return;
    }

    deleteConfirmEl.classList.add('show');
    deleteConfirmEl.style.display = 'block';
    deleteConfirmEl.removeAttribute('aria-hidden');
  }

  function hideDeleteConfirmModal() {
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(deleteConfirmEl).hide();
      return;
    }

    deleteConfirmEl.classList.remove('show');
    deleteConfirmEl.style.display = 'none';
    deleteConfirmEl.setAttribute('aria-hidden', 'true');
  }

  function loadLookup(segmentValueId) {
    if (segmentValueId === '' || segmentValueId === '0') {
      return;
    }

    currentLookupSegmentValueId = segmentValueId;
    titleEl.textContent = 'Issue Record Lookup: SegmentValueID ' + segmentValueId;
    statusEl.textContent = 'Loading records...';
    statusEl.className = 'text-muted mb-3';
    bodyEl.innerHTML = '';
    showModal();

    fetch('index.php?route=strategy-reports/segment-parent-child/issue-lookup&segment_value_id=' + encodeURIComponent(segmentValueId), {
      headers: {
        'Accept': 'application/json'
      }
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || payload.ok !== true) {
          throw new Error(payload && payload.message ? payload.message : 'Lookup failed.');
        }

        statusEl.textContent = '';
        bodyEl.innerHTML = '';
        const lookup = payload.lookup || {};
        Object.keys(labels).forEach(function (key) {
          bodyEl.appendChild(renderTable(key, lookup[key] || []));
        });
      })
      .catch(function (error) {
        statusEl.textContent = error.message || 'Lookup failed.';
        statusEl.className = 'text-danger mb-3';
        bodyEl.innerHTML = '';
      });
  }

  function requestDeleteSegmentValue(button) {
    const segmentValueId = button.getAttribute('data-segment-value-id') || '';
    if (segmentValueId === '' || segmentValueId === '0') {
      return;
    }

    pendingDeleteSegmentValueId = segmentValueId;
    const segmentCode = button.getAttribute('data-segment-code') || '';
    const dataObjectCode = button.getAttribute('data-data-object-code') || '';
    deleteConfirmTextEl.textContent = 'SegmentValueID ' + segmentValueId + ', DataObjectCode ' + dataObjectCode + ', SegmentCode ' + segmentCode + '. This removes the row from tblSegmentValues.';
    showDeleteConfirmModal();
  }

  function deleteSegmentValue(segmentValueId) {
    const body = new URLSearchParams();
    body.set('_csrf', csrfToken);
    body.set('segment_value_id', segmentValueId);

    statusEl.textContent = 'Deleting SegmentValueID ' + segmentValueId + '...';
    statusEl.className = 'text-muted mb-3';

    fetch('index.php?route=strategy-reports/segment-parent-child/delete-issue-row', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body: body.toString()
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || payload.ok !== true) {
          throw new Error(payload && payload.message ? payload.message : 'Delete failed.');
        }

        statusEl.textContent = 'Deleted SegmentValueID ' + segmentValueId + '. Reloading records...';
        statusEl.className = 'text-success mb-3';
        pendingDeleteSegmentValueId = '';
        hideDeleteConfirmModal();
        loadLookup(currentLookupSegmentValueId);
      })
      .catch(function (error) {
        statusEl.textContent = error.message || 'Delete failed.';
        statusEl.className = 'text-danger mb-3';
      });
  }

  document.addEventListener('click', function (event) {
    const lookupButton = event.target.closest('[data-parent-child-lookup]');
    if (lookupButton) {
      loadLookup(lookupButton.getAttribute('data-segment-value-id') || '');
      return;
    }

    const deleteButton = event.target.closest('[data-parent-child-delete-row]');
    if (deleteButton) {
      requestDeleteSegmentValue(deleteButton);
    }
  });

  deleteConfirmButton.addEventListener('click', function () {
    if (pendingDeleteSegmentValueId !== '') {
      deleteSegmentValue(pendingDeleteSegmentValueId);
    }
  });
})();
</script>
