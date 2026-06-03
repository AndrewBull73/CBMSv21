<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$diagnostics = is_array($diagnostics ?? null) ? $diagnostics : [];
$filters = is_array($diagnostics['Filters'] ?? null) ? $diagnostics['Filters'] : [];
$workflowAreas = is_array($diagnostics['WorkflowAreaOptions'] ?? null) ? $diagnostics['WorkflowAreaOptions'] : [];
$workflowStages = is_array($diagnostics['WorkflowStageOptions'] ?? null) ? $diagnostics['WorkflowStageOptions'] : [];
$fiscalYears = is_array($diagnostics['FiscalYears'] ?? null) ? $diagnostics['FiscalYears'] : [];
$versions = is_array($diagnostics['Versions'] ?? null) ? $diagnostics['Versions'] : [];
$dataObjectCodes = is_array($diagnostics['DataObjectCodes'] ?? null) ? $diagnostics['DataObjectCodes'] : [];
$scopeChain = is_array($diagnostics['ScopeChain'] ?? null) ? $diagnostics['ScopeChain'] : [];
$attempts = is_array($diagnostics['Attempts'] ?? null) ? $diagnostics['Attempts'] : [];
$resolvedAssignments = is_array($diagnostics['ResolvedAssignments'] ?? null) ? $diagnostics['ResolvedAssignments'] : [];
$tableInstalled = !empty($tableInstalled);
$supportsAssignments = !empty($supportsAssignments);
$ctx = is_array($_ctx ?? null) ? $_ctx : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ($ctx['FiscalYearID'] ?? '')));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ($ctx['VersionID'] ?? '')));
$matchedAttempts = count(array_filter($attempts, static fn(array $attempt): bool => (int) ($attempt['Matched'] ?? 0) === 1));
$screenHeader = [
    'titleKey' => 'workflow_engine_diagnostics_title',
    'title' => 'Workflow Engine Diagnostics',
    'icon' => 'bi-search',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        <?= h(__t('current_context')) ?>:
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-0">
          <strong><?= h(__t('workflow_engine_tables_missing_title')) ?></strong> <?= h(__t('workflow_engine_tables_missing_diagnostics_help')) ?> <code>create_workflow_engine_foundation_v1.sql</code>.
        </div>
      <?php elseif (!$supportsAssignments): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-0">
          <strong><?= h(__t('workflow_assignment_tables_missing_title')) ?></strong> <?= h(__t('workflow_assignment_tables_missing_diagnostics_help')) ?>
        </div>
      <?php else: ?>
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Scope Levels</div>
                <div class="fs-4 fw-semibold"><?= h((string) count($scopeChain)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Resolution Attempts</div>
                <div class="fs-4 fw-semibold"><?= h((string) count($attempts)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Matched Attempts</div>
                <div class="fs-4 fw-semibold"><?= h((string) $matchedAttempts) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Resolved Assignees</div>
                <div class="fs-4 fw-semibold"><?= h((string) count($resolvedAssignments)) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="alert alert-info border-0 shadow-sm mb-4">
          <?= h(__t('workflow_engine_diagnostics_intro')) ?>
        </div>

        <form method="get" action="index.php" class="row g-3 mb-4" id="workflow-engine-diagnostics-form">
          <input type="hidden" name="route" value="workflow-engine/diagnostics">
          <div class="col-md-3">
            <label class="form-label" for="workflowDiagnosticsArea">Workflow Area</label>
            <select class="form-select" name="workflow_area_code" id="workflowDiagnosticsArea">
              <option value="">Select workflow area</option>
              <?php foreach ($workflowAreas as $workflowArea): ?>
                <option value="<?= h((string) ($workflowArea['code'] ?? '')) ?>" <?= (($filters['workflow_area_code'] ?? '') === (string) ($workflowArea['code'] ?? '')) ? 'selected' : '' ?>>
                  <?= h((string) ($workflowArea['label'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="workflowDiagnosticsStage">Workflow Stage</label>
            <select class="form-select" name="workflow_stage_code" id="workflowDiagnosticsStage">
              <option value="">Select workflow stage</option>
              <?php foreach ($workflowStages as $workflowStage): ?>
                <option value="<?= h((string) ($workflowStage['code'] ?? '')) ?>" <?= (($filters['workflow_stage_code'] ?? '') === (string) ($workflowStage['code'] ?? '')) ? 'selected' : '' ?>>
                  <?= h((string) ($workflowStage['workflow_area_code'] ?? '')) ?> / <?= h((string) ($workflowStage['label'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="workflowDiagnosticsFy">Fiscal Year</label>
            <select class="form-select" name="fy" id="workflowDiagnosticsFy">
              <option value="">Any</option>
              <?php foreach ($fiscalYears as $fyRow): ?>
                <?php $fyValue = (string) ($fyRow['FiscalYearID'] ?? ''); ?>
                <option value="<?= h($fyValue) ?>" <?= (($filters['fy'] ?? '') === $fyValue) ? 'selected' : '' ?>>
                  <?= h((string) ($fyRow['YearLabel'] ?? $fyValue)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="workflowDiagnosticsVersion">Version</label>
            <select class="form-select" name="version_id" id="workflowDiagnosticsVersion">
              <option value="">Any</option>
              <?php foreach ($versions as $versionRow): ?>
                <?php $versionValue = (string) ($versionRow['VersionID'] ?? ''); ?>
                <option value="<?= h($versionValue) ?>" <?= (($filters['version_id'] ?? '') === $versionValue) ? 'selected' : '' ?>>
                  <?= h((string) ($versionRow['VersionLabel'] ?? $versionValue)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="workflowDiagnosticsScope">DataScope</label>
            <input class="form-control" list="diagnosticDataObjectCodes" type="text" name="data_object_code" id="workflowDiagnosticsScope" value="<?= h((string) ($filters['data_object_code'] ?? '')) ?>" placeholder="DataObjectCode">
            <datalist id="diagnosticDataObjectCodes">
              <?php foreach ($dataObjectCodes as $row): ?>
                <option value="<?= h((string) ($row['DataObjectCode'] ?? '')) ?>"><?= h((string) ($row['DataObjectName'] ?? '')) ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-2 d-grid">
            <label class="form-label">&nbsp;</label>
            <button type="submit" id="workflow-engine-diagnostics-run-btn" class="btn btn-primary">Run Diagnostics</button>
          </div>
        </form>

        <?php if ($scopeChain !== []): ?>
          <div class="row g-4 mb-4">
            <div class="col-12 col-xl-4">
              <div class="card shadow-sm h-100">
                <div class="card-header">
                  <h5 class="mb-0">Scope Chain</h5>
                </div>
                <div class="card-body">
                  <div class="small text-muted mb-2">Resolution walks from the requested scope upward through parent scopes, then to global.</div>
                  <ol class="mb-0">
                    <?php foreach ($scopeChain as $scopeCode): ?>
                      <li><code><?= h((string) $scopeCode) ?></code></li>
                    <?php endforeach; ?>
                  </ol>
                </div>
              </div>
            </div>

            <div class="col-12 col-xl-8">
              <div class="card shadow-sm h-100">
                <div class="card-header">
                  <h5 class="mb-0">Resolved Assignments</h5>
                </div>
                <div class="card-body">
                  <?php if ($resolvedAssignments === []): ?>
                    <div class="text-muted">No workflow assignment resolved for this combination.</div>
                  <?php else: ?>
                    <div class="small text-muted mb-3">
                      Resolved at scope <code><?= h((string) ($diagnostics['ResolvedScopeCode'] ?? '')) ?></code>
                      <?php if (!empty($diagnostics['ResolvedFiscalYearID'])): ?>
                        / FY <?= h((string) ($diagnostics['ResolvedFiscalYearID'] ?? '')) ?>
                      <?php endif; ?>
                      <?php if (!empty($diagnostics['ResolvedVersionID'])): ?>
                        / Version <?= h((string) ($diagnostics['ResolvedVersionID'] ?? '')) ?>
                      <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Sequence</th>
                            <th>Assignee</th>
                            <th>Mode</th>
                            <th>Primary</th>
                            <th>Hierarchy Flags</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($resolvedAssignments as $assignmentRow): ?>
                            <tr>
                              <td><?= (int) ($assignmentRow['SequenceNo'] ?? 0) ?></td>
                              <td>
                                <div class="fw-semibold"><?= h((string) ($assignmentRow['DisplayName'] ?? $assignmentRow['Username'] ?? '')) ?></div>
                                <div class="small text-muted"><?= h((string) ($assignmentRow['Username'] ?? '')) ?></div>
                              </td>
                              <td><?= h((string) ($assignmentRow['AssignmentMode'] ?? 'USER')) ?></td>
                              <td><?= ((int) ($assignmentRow['IsPrimary'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
                              <td>
                                <div>Inherit: <?= ((int) ($assignmentRow['InheritFromParentScope'] ?? 0) === 1) ? 'Yes' : 'No' ?></div>
                                <div class="small text-muted">Route By Hierarchy: <?= ((int) ($assignmentRow['RouteByDataObjectHierarchy'] ?? 0) === 1) ? 'Yes' : 'No' ?></div>
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
        <?php endif; ?>

        <div class="card shadow-sm">
          <div class="card-header">
            <h5 class="mb-0">Resolution Attempts</h5>
          </div>
          <div class="card-body">
            <?php if ($attempts === []): ?>
              <div class="text-muted">Provide workflow area and stage to trace assignment resolution.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Scope</th>
                      <th>Exact Scope</th>
                      <th>Fiscal Year</th>
                      <th>Version</th>
                      <th>Result</th>
                      <th class="text-end">Assignments Found</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                      <tr>
                        <td><code><?= h((string) ($attempt['ScopeCode'] ?? '')) ?></code></td>
                        <td><?= ((int) ($attempt['IsExactScope'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
                        <td><?= h((string) ($attempt['FiscalYearID'] ?? 'GLOBAL')) ?></td>
                        <td><?= h((string) ($attempt['VersionID'] ?? 'ALL')) ?></td>
                        <td>
                          <span class="badge <?= ((int) ($attempt['Matched'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= ((int) ($attempt['Matched'] ?? 0) === 1) ? 'Matched' : 'No Match' ?>
                          </span>
                        </td>
                        <td class="text-end"><?= h((string) count((array) ($attempt['Assignments'] ?? []))) ?></td>
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
