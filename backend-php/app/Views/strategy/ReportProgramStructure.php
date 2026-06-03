<?php
declare(strict_types=1);

/** @var array $contextLabels */
/** @var array $summary */
/** @var array $programCodeConflicts */
/** @var array $programNameConflicts */
/** @var array $subProgramCodeConflicts */
/** @var array $subProgramPrefixIssues */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$summary = is_array($summary ?? null) ? $summary : [];
$programCodeConflicts = is_array($programCodeConflicts ?? null) ? $programCodeConflicts : [];
$programNameConflicts = is_array($programNameConflicts ?? null) ? $programNameConflicts : [];
$subProgramCodeConflicts = is_array($subProgramCodeConflicts ?? null) ? $subProgramCodeConflicts : [];
$subProgramPrefixIssues = is_array($subProgramPrefixIssues ?? null) ? $subProgramPrefixIssues : [];
$mappingReady = (bool) ($summary['mapping_ready'] ?? false);

$screenHeader = [
    'title' => 'Program Structure Diagnostics',
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

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this report to check whether program and subprogram codes follow the MTFF-friendly structure you want to enforce: globally unique program codes, globally unique subprogram codes, consistent program naming, and subprogram codes that start with their parent program code.
      </div>

      <?php if (!$mappingReady): ?>
        <div class="alert alert-warning shadow-sm">
          Program Structure Diagnostics needs active <code>PROGRAM</code> and <code>SUBPROGRAM</code> mappings for the selected fiscal year before it can profile <code>tblSegmentValues</code>.
        </div>
      <?php else: ?>
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-2">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Program segment</div>
                <div class="fs-4 fw-semibold"><?= (int) ($summary['program_segment_no'] ?? 0) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Subprogram segment</div>
                <div class="fs-4 fw-semibold"><?= (int) ($summary['sub_program_segment_no'] ?? 0) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Program codes</div>
                <div class="fs-4 fw-semibold"><?= (int) ($summary['program_code_count'] ?? 0) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Subprogram codes</div>
                <div class="fs-4 fw-semibold"><?= (int) ($summary['sub_program_code_count'] ?? 0) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="card shadow-sm h-100 border-warning">
              <div class="card-body">
                <div class="text-muted small">Program code conflicts</div>
                <div class="fs-4 fw-semibold"><?= (int) ($summary['program_code_conflict_count'] ?? 0) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-2">
            <div class="card shadow-sm h-100 border-warning">
              <div class="card-body">
                <div class="text-muted small">Subprogram prefix issues</div>
                <div class="fs-4 fw-semibold"><?= (int) ($summary['sub_program_prefix_issue_count'] ?? 0) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Program Codes With Multiple Names</h5>
          </div>
          <div class="card-body">
            <?php if ($programCodeConflicts === []): ?>
              <p class="text-muted mb-0">No program codes with multiple names were found in the active fiscal year.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Program Code</th>
                      <th>Program Names</th>
                      <th>DataObjectCodes</th>
                      <th class="text-end">Rows</th>
                      <th class="text-end">Distinct Names</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($programCodeConflicts as $row): ?>
                    <tr>
                      <td class="fw-semibold"><?= h((string) ($row['ProgramCode'] ?? '')) ?></td>
                      <td><?= h((string) ($row['ProgramNames'] ?? '')) ?></td>
                      <td><?= h((string) ($row['DataObjectCodes'] ?? '')) ?></td>
                      <td class="text-end"><?= (int) ($row['TotalRows'] ?? 0) ?></td>
                      <td class="text-end"><?= (int) ($row['DistinctNameCount'] ?? 0) ?></td>
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
            <h5 class="mb-0">Program Names With Multiple Codes</h5>
          </div>
          <div class="card-body">
            <?php if ($programNameConflicts === []): ?>
              <p class="text-muted mb-0">No program names with multiple codes were found in the active fiscal year.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Program Name</th>
                      <th>Program Codes</th>
                      <th>DataObjectCodes</th>
                      <th class="text-end">Rows</th>
                      <th class="text-end">Distinct Codes</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($programNameConflicts as $row): ?>
                    <tr>
                      <td class="fw-semibold"><?= h((string) ($row['ProgramName'] ?? '')) ?></td>
                      <td><?= h((string) ($row['ProgramCodes'] ?? '')) ?></td>
                      <td><?= h((string) ($row['DataObjectCodes'] ?? '')) ?></td>
                      <td class="text-end"><?= (int) ($row['TotalRows'] ?? 0) ?></td>
                      <td class="text-end"><?= (int) ($row['DistinctCodeCount'] ?? 0) ?></td>
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
            <h5 class="mb-0">SubProgram Codes With Multiple Names</h5>
          </div>
          <div class="card-body">
            <?php if ($subProgramCodeConflicts === []): ?>
              <p class="text-muted mb-0">No subprogram codes with multiple names were found in the active fiscal year.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>SubProgram Code</th>
                      <th>SubProgram Names</th>
                      <th>Parent Program Codes</th>
                      <th class="text-end">Rows</th>
                      <th class="text-end">Distinct Names</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($subProgramCodeConflicts as $row): ?>
                    <tr>
                      <td class="fw-semibold"><?= h((string) ($row['SubProgramCode'] ?? '')) ?></td>
                      <td><?= h((string) ($row['SubProgramNames'] ?? '')) ?></td>
                      <td><?= h((string) ($row['ParentProgramCodes'] ?? '')) ?></td>
                      <td class="text-end"><?= (int) ($row['TotalRows'] ?? 0) ?></td>
                      <td class="text-end"><?= (int) ($row['DistinctNameCount'] ?? 0) ?></td>
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
            <h5 class="mb-0">SubProgram Prefix Rule Issues</h5>
          </div>
          <div class="card-body">
            <?php if ($subProgramPrefixIssues === []): ?>
              <p class="text-muted mb-0">All linked subprogram codes start with their parent program code.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Parent Program Code</th>
                      <th>SubProgram Code</th>
                      <th>SubProgram Name</th>
                      <th>DataObjectCode</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($subProgramPrefixIssues as $row): ?>
                    <tr>
                      <td class="fw-semibold"><?= h((string) ($row['ParentProgramCode'] ?? '')) ?></td>
                      <td><?= h((string) ($row['SubProgramCode'] ?? '')) ?></td>
                      <td><?= h((string) ($row['SubProgramName'] ?? '')) ?></td>
                      <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
