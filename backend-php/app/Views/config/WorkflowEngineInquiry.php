<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('workflow_instance_badge_class')) {
    function workflow_instance_badge_class(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'APPROVED' => 'text-bg-success',
            'CANCELLED' => 'text-bg-secondary',
            default => 'text-bg-warning',
        };
    }
}

$inquiry = is_array($inquiry ?? null) ? $inquiry : [];
$filters = is_array($inquiry['Filters'] ?? null) ? $inquiry['Filters'] : [];
$workflowAreas = is_array($inquiry['WorkflowAreaOptions'] ?? null) ? $inquiry['WorkflowAreaOptions'] : [];
$workflowStages = is_array($inquiry['WorkflowStageOptions'] ?? null) ? $inquiry['WorkflowStageOptions'] : [];
$fiscalYears = is_array($inquiry['FiscalYears'] ?? null) ? $inquiry['FiscalYears'] : [];
$versions = is_array($inquiry['Versions'] ?? null) ? $inquiry['Versions'] : [];
$dataObjectCodes = is_array($inquiry['DataObjectCodes'] ?? null) ? $inquiry['DataObjectCodes'] : [];
$users = is_array($inquiry['Users'] ?? null) ? $inquiry['Users'] : [];
$stateBucketOptions = is_array($inquiry['StateBucketOptions'] ?? null) ? $inquiry['StateBucketOptions'] : [];
$summary = is_array($inquiry['Summary'] ?? null) ? $inquiry['Summary'] : [];
$rows = is_array($inquiry['Rows'] ?? null) ? $inquiry['Rows'] : [];
$rowLimit = (int) ($inquiry['RowLimit'] ?? 250);
$selectedWorkflowInstanceId = (int) ($inquiry['SelectedWorkflowInstanceID'] ?? 0);
$selected = is_array($inquiry['SelectedInstance'] ?? null) ? $inquiry['SelectedInstance'] : [];
$selectedInstance = is_array($selected['Instance'] ?? null) ? $selected['Instance'] : null;
$selectedAssignments = is_array($selected['CurrentAssignments'] ?? null) ? $selected['CurrentAssignments'] : [];
$selectedAllowedActions = is_array($selected['AllowedActions'] ?? null) ? $selected['AllowedActions'] : [];
$selectedHistory = is_array($selected['History'] ?? null) ? $selected['History'] : [];
$tableInstalled = !empty($tableInstalled);
$supportsAssignments = !empty($supportsAssignments);
$ctx = is_array($_ctx ?? null) ? $_ctx : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ($ctx['FiscalYearID'] ?? '')));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ($ctx['VersionID'] ?? '')));
$screenHeader = [
    'titleKey' => 'workflow_engine_inquiry_title',
    'title' => 'Workflow Engine Inquiry',
    'icon' => 'bi-kanban',
];

$filterQuery = http_build_query([
    'route' => 'workflow-engine/inquiry',
    'workflow_area_code' => (string) ($filters['workflow_area_code'] ?? ''),
    'current_stage_code' => (string) ($filters['current_stage_code'] ?? ''),
    'fy' => (string) ($filters['fy'] ?? ''),
    'version_id' => (string) ($filters['version_id'] ?? ''),
    'data_object_code' => (string) ($filters['data_object_code'] ?? ''),
    'assigned_user_id' => (string) ($filters['assigned_user_id'] ?? ''),
    'state_bucket' => (string) ($filters['state_bucket'] ?? 'OPEN'),
    'q' => (string) ($filters['q'] ?? ''),
]);
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
          <strong><?= h(__t('workflow_instance_tables_missing_title')) ?></strong> <?= h(__t('workflow_instance_tables_missing_help')) ?> <code>create_workflow_engine_foundation_v1.sql</code>.
        </div>
      <?php else: ?>
        <?php if (!$supportsAssignments): ?>
          <div class="alert alert-warning border-0 shadow-sm mb-4">
            <strong><?= h(__t('workflow_assignment_tables_missing_title')) ?></strong> <?= h(__t('workflow_assignment_tables_missing_inquiry_help')) ?>
          </div>
        <?php endif; ?>

        <div class="alert alert-info border-0 shadow-sm mb-4">
          <?= h(__t('workflow_engine_inquiry_intro')) ?>
        </div>

        <form method="get" action="index.php" class="row g-3 mb-4" id="workflow-engine-inquiry-form">
          <input type="hidden" name="route" value="workflow-engine/inquiry">
          <div class="col-md-3">
            <label class="form-label">Workflow Area</label>
            <select class="form-select" name="workflow_area_code" id="workflowInquiryArea">
              <option value="">All workflow areas</option>
              <?php foreach ($workflowAreas as $workflowArea): ?>
                <?php $areaCode = (string) ($workflowArea['code'] ?? ''); ?>
                <option value="<?= h($areaCode) ?>" <?= (($filters['workflow_area_code'] ?? '') === $areaCode) ? 'selected' : '' ?>>
                  <?= h((string) ($workflowArea['label'] ?? $areaCode)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Current Stage</label>
            <select class="form-select" name="current_stage_code" id="workflowInquiryStage">
              <option value="">All stages</option>
              <?php foreach ($workflowStages as $workflowStage): ?>
                <?php $stageCode = (string) ($workflowStage['code'] ?? ''); ?>
                <?php $stageAreaCode = (string) ($workflowStage['workflow_area_code'] ?? ''); ?>
                <option value="<?= h($stageCode) ?>" data-workflow-area-code="<?= h($stageAreaCode) ?>" <?= (($filters['current_stage_code'] ?? '') === $stageCode) ? 'selected' : '' ?>>
                  <?= h($stageAreaCode) ?> / <?= h((string) ($workflowStage['label'] ?? $stageCode)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
              <label class="form-label" for="workflowInquiryState">State</label>
              <select class="form-select" name="state_bucket" id="workflowInquiryState">
              <?php foreach ($stateBucketOptions as $option): ?>
                <?php $optionCode = (string) ($option['code'] ?? 'OPEN'); ?>
                <option value="<?= h($optionCode) ?>" <?= (($filters['state_bucket'] ?? 'OPEN') === $optionCode) ? 'selected' : '' ?>>
                  <?= h((string) ($option['label'] ?? $optionCode)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
              <label class="form-label" for="workflowInquiryFy">Fiscal Year</label>
              <select class="form-select" name="fy" id="workflowInquiryFy">
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
              <label class="form-label" for="workflowInquiryVersion">Version</label>
              <select class="form-select" name="version_id" id="workflowInquiryVersion">
              <option value="">Any</option>
              <?php foreach ($versions as $versionRow): ?>
                <?php $versionValue = (string) ($versionRow['VersionID'] ?? ''); ?>
                <option value="<?= h($versionValue) ?>" <?= (($filters['version_id'] ?? '') === $versionValue) ? 'selected' : '' ?>>
                  <?= h((string) ($versionRow['VersionLabel'] ?? $versionValue)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
              <label class="form-label" for="workflowInquiryScope">Scope</label>
              <input class="form-control" list="workflowInquiryDataObjectCodes" type="text" name="data_object_code" id="workflowInquiryScope" value="<?= h((string) ($filters['data_object_code'] ?? '')) ?>" placeholder="DataObjectCode">
            <datalist id="workflowInquiryDataObjectCodes">
              <?php foreach ($dataObjectCodes as $row): ?>
                <option value="<?= h((string) ($row['DataObjectCode'] ?? '')) ?>"><?= h((string) ($row['DataObjectName'] ?? '')) ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-3">
              <label class="form-label" for="workflowInquiryAssignee">Current Assignee</label>
              <select class="form-select" name="assigned_user_id" id="workflowInquiryAssignee">
              <option value="">Any</option>
              <?php foreach ($users as $user): ?>
                <?php $userId = (string) ($user['UserID'] ?? ''); ?>
                <?php $displayName = trim((string) ($user['DisplayName'] ?? '')) !== '' ? (string) ($user['DisplayName'] ?? '') : (string) ($user['Username'] ?? ''); ?>
                <option value="<?= h($userId) ?>" <?= (($filters['assigned_user_id'] ?? '') === $userId) ? 'selected' : '' ?>>
                  <?= h($displayName) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
              <label class="form-label" for="workflowInquirySearch">Search</label>
              <input class="form-control" type="text" name="q" id="workflowInquirySearch" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Title, record key, table, or note">
          </div>
          <div class="col-md-2 d-grid">
            <label class="form-label">&nbsp;</label>
            <button type="submit" id="workflow-engine-inquiry-run-btn" class="btn btn-primary">Run Inquiry</button>
          </div>
        </form>

        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Matching Instances</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['TotalCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Open</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['OpenCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['ApprovedCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Cancelled</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['CancelledCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
        </div>

        <?php if (($summary['TotalCount'] ?? 0) > $rowLimit): ?>
          <div class="alert alert-info border-0 shadow-sm mb-4">
            Showing the most recent <?= h((string) $rowLimit) ?> matching workflow instances. Refine the filters to narrow the result set further.
          </div>
        <?php endif; ?>

        <div class="row g-4">
          <div class="col-12 col-xl-8">
            <div class="card shadow-sm h-100">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Workflow Instances</h5>
                <div class="small text-muted">Showing <?= h((string) count($rows)) ?> row(s)</div>
              </div>
              <div class="card-body">
                <?php if ($rows === []): ?>
                  <div class="text-muted">No workflow instances matched the current filters.</div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="workflow-engine-inquiry-table">
                      <thead class="table-light">
                        <tr>
                          <th>Workflow</th>
                          <th>Record</th>
                          <th>Context</th>
                          <th>Stage</th>
                          <th>Assignee</th>
                          <th>Last Action</th>
                          <th class="text-end">Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($rows as $row): ?>
                          <?php $instanceId = (int) ($row['WorkflowInstanceID'] ?? 0); ?>
                          <?php $isSelected = $selectedWorkflowInstanceId === $instanceId; ?>
                          <tr class="<?= $isSelected ? 'table-primary' : '' ?>">
                            <td>
                              <div class="fw-semibold"><?= h((string) ($row['WorkflowAreaName'] ?? $row['WorkflowAreaCode'] ?? '')) ?></div>
                              <div class="small text-muted"><?= h((string) ($row['WorkflowAreaCode'] ?? '')) ?></div>
                            </td>
                            <td>
                              <div class="fw-semibold"><?= h((string) ($row['WorkflowTitle'] ?? $row['RecordKey'] ?? '')) ?></div>
                              <div class="small text-muted">
                                <?= h((string) ($row['RecordTableName'] ?? '')) ?>
                                <span class="mx-1">/</span>
                                <?= h((string) ($row['RecordKey'] ?? '')) ?>
                              </div>
                            </td>
                            <td>
                              <div>FY <?= h((string) ($row['YearLabel'] ?? $row['FiscalYearID'] ?? '-')) ?></div>
                              <div class="small text-muted"><?= h((string) ($row['VersionLabel'] ?? $row['VersionID'] ?? '-')) ?></div>
                              <div class="small text-muted">
                                <?= h((string) ($row['ScopeDataObjectCode'] ?? '')) ?>
                                <?php if (trim((string) ($row['ScopeDataObjectName'] ?? '')) !== ''): ?>
                                  <span class="mx-1">-</span><?= h((string) ($row['ScopeDataObjectName'] ?? '')) ?>
                                <?php endif; ?>
                              </div>
                            </td>
                            <td>
                              <span class="badge <?= h(workflow_instance_badge_class((string) ($row['CurrentStatusCode'] ?? ''))) ?>"><?= h((string) ($row['CurrentStatusCode'] ?? '')) ?></span>
                              <?php if (trim((string) ($row['CurrentStageName'] ?? '')) !== '' && strtoupper((string) ($row['CurrentStageName'] ?? '')) !== strtoupper((string) ($row['CurrentStatusCode'] ?? ''))): ?>
                                <div class="small text-muted mt-1"><?= h((string) ($row['CurrentStageName'] ?? '')) ?></div>
                              <?php endif; ?>
                            </td>
                            <td>
                              <?php if (trim((string) ($row['CurrentAssignmentUserName'] ?? '')) !== ''): ?>
                                <div class="fw-semibold"><?= h((string) ($row['CurrentAssignmentUserName'] ?? '')) ?></div>
                                <div class="small text-muted"><?= h((string) ($row['CurrentAssignmentUsername'] ?? '')) ?></div>
                              <?php elseif (trim((string) ($row['CurrentAssignmentScopeCode'] ?? '')) !== ''): ?>
                                <div class="fw-semibold"><?= h((string) ($row['CurrentAssignmentScopeCode'] ?? '')) ?></div>
                                <div class="small text-muted">Scope only</div>
                              <?php else: ?>
                                <span class="text-muted">Unassigned</span>
                              <?php endif; ?>
                            </td>
                            <td>
                              <div><?= h((string) ($row['LastActionCode'] ?? 'CREATE')) ?></div>
                              <div class="small text-muted"><?= h((string) ($row['LastActionByName'] ?? '')) ?></div>
                              <div class="small text-muted"><?= h((string) ($row['LastActionDate'] ?? $row['UpdatedDate'] ?? '')) ?></div>
                            </td>
                            <td class="text-end">
                              <a href="index.php?<?= h($filterQuery) ?>&workflow_instance_id=<?= $instanceId ?>" id="workflow-engine-inquiry-open-btn-<?= $instanceId ?>" class="btn btn-outline-primary btn-sm">Open</a>
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

          <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Selected Instance</h5>
                <?php if ($selectedInstance !== null): ?>
                  <span class="badge <?= h(workflow_instance_badge_class((string) ($selectedInstance['CurrentStatusCode'] ?? ''))) ?>"><?= h((string) ($selectedInstance['CurrentStatusCode'] ?? '')) ?></span>
                <?php endif; ?>
              </div>
              <div class="card-body">
                <?php if ($selectedInstance === null): ?>
                  <div class="text-muted">Open a workflow instance from the table to review its current stage, assignees, and history.</div>
                <?php else: ?>
                  <div class="mb-3">
                    <div class="fw-semibold"><?= h((string) ($selectedInstance['WorkflowTitle'] ?? $selectedInstance['RecordKey'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($selectedInstance['WorkflowAreaName'] ?? $selectedInstance['WorkflowAreaCode'] ?? '')) ?></div>
                  </div>

                  <div class="d-flex gap-2 flex-wrap mb-3">
                    <a href="index.php?route=workflow-engine/form&workflow_area_code=<?= urlencode((string) ($selectedInstance['WorkflowAreaCode'] ?? '')) ?>" id="workflow-engine-inquiry-definition-btn" class="btn btn-sm btn-outline-primary">Definition</a>
                    <a href="index.php?route=workflow-engine/diagnostics&workflow_area_code=<?= urlencode((string) ($selectedInstance['WorkflowAreaCode'] ?? '')) ?>&workflow_stage_code=<?= urlencode((string) ($selectedInstance['CurrentStageCode'] ?? '')) ?>&fy=<?= urlencode((string) ($selectedInstance['FiscalYearID'] ?? '')) ?>&version_id=<?= urlencode((string) ($selectedInstance['VersionID'] ?? '')) ?>&data_object_code=<?= urlencode((string) ($selectedInstance['ScopeDataObjectCode'] ?? '')) ?>" id="workflow-engine-inquiry-diagnostics-btn" class="btn btn-sm btn-outline-primary">Diagnostics</a>
                  </div>

                  <div class="row g-3 mb-3">
                    <div class="col-6">
                      <div class="text-muted small">Workflow Instance</div>
                      <div class="fw-semibold"><?= h((string) ($selectedInstance['WorkflowInstanceID'] ?? '')) ?></div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted small">Current Stage</div>
                      <div class="fw-semibold"><?= h((string) ($selectedInstance['CurrentStageName'] ?? $selectedInstance['CurrentStageCode'] ?? '')) ?></div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted small">Fiscal Year</div>
                      <div class="fw-semibold"><?= h((string) ($selectedInstance['YearLabel'] ?? $selectedInstance['FiscalYearID'] ?? '-')) ?></div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted small">Version</div>
                      <div class="fw-semibold"><?= h((string) ($selectedInstance['VersionLabel'] ?? $selectedInstance['VersionID'] ?? '-')) ?></div>
                    </div>
                    <div class="col-12">
                      <div class="text-muted small">Record</div>
                      <div class="fw-semibold"><?= h((string) ($selectedInstance['RecordTableName'] ?? '')) ?></div>
                      <div class="small text-muted">Key <?= h((string) ($selectedInstance['RecordKey'] ?? '')) ?></div>
                    </div>
                    <div class="col-12">
                      <div class="text-muted small">Scope</div>
                      <div class="fw-semibold"><?= h((string) ($selectedInstance['ScopeDataObjectCode'] ?? '')) ?></div>
                      <?php if (trim((string) ($selectedInstance['ScopeDataObjectName'] ?? '')) !== ''): ?>
                        <div class="small text-muted"><?= h((string) ($selectedInstance['ScopeDataObjectName'] ?? '')) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="col-12">
                      <div class="text-muted small">Current Assignment Scope</div>
                      <div class="fw-semibold"><?= h((string) ($selectedInstance['CurrentAssignmentScopeCode'] ?? '')) ?></div>
                      <?php if (trim((string) ($selectedInstance['CurrentAssignmentScopeName'] ?? '')) !== ''): ?>
                        <div class="small text-muted"><?= h((string) ($selectedInstance['CurrentAssignmentScopeName'] ?? '')) ?></div>
                      <?php endif; ?>
                    </div>
                    <div class="col-12">
                      <div class="text-muted small">Current Assignee</div>
                      <?php if (trim((string) ($selectedInstance['CurrentAssignmentUserName'] ?? '')) !== ''): ?>
                        <div class="fw-semibold"><?= h((string) ($selectedInstance['CurrentAssignmentUserName'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($selectedInstance['CurrentAssignmentUsername'] ?? '')) ?></div>
                      <?php else: ?>
                        <div class="text-muted">No current assignee captured.</div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php if (trim((string) ($selectedInstance['WorkflowNote'] ?? '')) !== ''): ?>
                    <div class="alert alert-light border shadow-sm mb-3">
                      <div class="small text-muted mb-1">Latest Note</div>
                      <div class="small"><?= nl2br(h((string) ($selectedInstance['WorkflowNote'] ?? ''))) ?></div>
                    </div>
                  <?php endif; ?>

                  <?php if ($selectedAllowedActions !== []): ?>
                    <div class="mb-3">
                      <div class="text-muted small mb-2">Allowed Next Actions</div>
                      <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($selectedAllowedActions as $actionRow): ?>
                          <span class="badge text-bg-light border"><?= h((string) ($actionRow['WorkflowActionName'] ?? $actionRow['WorkflowActionCode'] ?? '')) ?></span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <div class="card shadow-sm mb-3">
                    <div class="card-header">
                      <h6 class="mb-0">Current Stage Assignments</h6>
                    </div>
                    <div class="card-body">
                      <?php if ($selectedAssignments === []): ?>
                        <div class="text-muted small">No assignment currently resolves for this stage and scope.</div>
                      <?php else: ?>
                        <div class="table-responsive">
                          <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                              <tr>
                                <th>Sequence</th>
                                <th>Assignee</th>
                                <th>Mode</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($selectedAssignments as $assignmentRow): ?>
                                <tr>
                                  <td><?= h((string) ($assignmentRow['SequenceNo'] ?? '')) ?></td>
                                  <td>
                                    <div class="fw-semibold"><?= h((string) ($assignmentRow['DisplayName'] ?? $assignmentRow['Username'] ?? '')) ?></div>
                                    <div class="small text-muted"><?= h((string) ($assignmentRow['Username'] ?? '')) ?></div>
                                  </td>
                                  <td><?= h((string) ($assignmentRow['AssignmentMode'] ?? 'USER')) ?></td>
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
                      <h6 class="mb-0">Workflow History</h6>
                    </div>
                    <div class="card-body">
                      <?php if ($selectedHistory === []): ?>
                        <div class="text-muted small">No workflow history has been recorded for this instance yet.</div>
                      <?php else: ?>
                        <div class="table-responsive">
                          <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                              <tr>
                                <th>Action</th>
                                <th>Transition</th>
                                <th>By</th>
                                <th>Date</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($selectedHistory as $historyRow): ?>
                                <tr>
                                  <td>
                                    <div class="fw-semibold"><?= h((string) ($historyRow['WorkflowActionCode'] ?? '')) ?></div>
                                    <?php if (trim((string) ($historyRow['ActionNote'] ?? '')) !== ''): ?>
                                      <div class="small text-muted"><?= h((string) ($historyRow['ActionNote'] ?? '')) ?></div>
                                    <?php endif; ?>
                                  </td>
                                  <td>
                                    <div><?= h((string) ($historyRow['FromStageName'] ?? $historyRow['FromStageCode'] ?? '-')) ?></div>
                                    <div class="small text-muted">to <?= h((string) ($historyRow['ToStageName'] ?? $historyRow['ToStageCode'] ?? '')) ?></div>
                                  </td>
                                  <td>
                                    <div><?= h((string) ($historyRow['ActionByName'] ?? '')) ?></div>
                                    <?php if (trim((string) ($historyRow['AssignmentUserName'] ?? '')) !== ''): ?>
                                      <div class="small text-muted">Assigned: <?= h((string) ($historyRow['AssignmentUserName'] ?? '')) ?></div>
                                    <?php endif; ?>
                                  </td>
                                  <td><?= h((string) ($historyRow['ActionDate'] ?? '')) ?></td>
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
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const areaSelect = document.getElementById('workflowInquiryArea');
  const stageSelect = document.getElementById('workflowInquiryStage');
  if (!areaSelect || !stageSelect) {
    return;
  }

  const options = Array.from(stageSelect.options).map(function (option) {
    return {
      value: option.value,
      text: option.text,
      selected: option.selected,
      workflowAreaCode: option.dataset.workflowAreaCode || ''
    };
  });

  function rebuildStageOptions() {
    const selectedArea = (areaSelect.value || '').toUpperCase();
    const currentValue = stageSelect.value;
    stageSelect.innerHTML = '';

    options.forEach(function (option) {
      if (option.value !== '' && option.workflowAreaCode !== '' && selectedArea !== '' && option.workflowAreaCode.toUpperCase() !== selectedArea) {
        return;
      }

      const node = document.createElement('option');
      node.value = option.value;
      node.textContent = option.text;
      if (option.workflowAreaCode) {
        node.dataset.workflowAreaCode = option.workflowAreaCode;
      }
      if (option.value === currentValue) {
        node.selected = true;
      }
      stageSelect.appendChild(node);
    });

    if (currentValue && !Array.from(stageSelect.options).some(function (option) { return option.value === currentValue; })) {
      stageSelect.value = '';
    }
  }

  areaSelect.addEventListener('change', rebuildStageOptions);
  rebuildStageOptions();
});
</script>
