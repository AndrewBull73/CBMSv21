<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('option_label_with_code')) {
    function option_label_with_code(?string $code, ?string $label): string
    {
        $code = trim((string) $code);
        $label = trim((string) $label);
        if ($code !== '' && $label !== '') {
            return $code . ' - ' . $label;
        }
        return $label !== '' ? $label : $code;
    }
}

$record = $record ?? [];
$sourceProject = $sourceProject ?? null;
$sourceMappings = $sourceMappings ?? [];
$usageSummary = $usageSummary ?? [];
$projectFundingSummary = $projectFundingSummary ?? [];
$projectFundingBreakdown = $projectFundingBreakdown ?? [];
$projectFundingLines = $projectFundingLines ?? [];
$programOptions = $programOptions ?? [];
$objectiveOptions = $objectiveOptions ?? [];
$orgUnitOptions = $orgUnitOptions ?? [];
$linkedPrograms = $linkedPrograms ?? [];
$linkedObjectives = $linkedObjectives ?? [];
$linkedOrgUnits = $linkedOrgUnits ?? [];
$selectedProgramIds = $selectedProgramIds ?? [];
$selectedObjectiveIds = $selectedObjectiveIds ?? [];
$selectedLinkedDataObjectCodes = $selectedLinkedDataObjectCodes ?? [];
$supportsProjectSourceMaps = (bool) ($supportsProjectSourceMaps ?? false);
$supportsProjectProgramLinks = (bool) ($supportsProjectProgramLinks ?? false);
$supportsProjectObjectiveLinks = (bool) ($supportsProjectObjectiveLinks ?? false);
$supportsProjectOrgUnitLinks = (bool) ($supportsProjectOrgUnitLinks ?? false);

$id = (int) ($record['ProjectID'] ?? 0);
$projectCode = (string) ($record['ProjectCode'] ?? $sourceProject['ProjectCode'] ?? '');
$projectName = (string) ($record['ProjectName'] ?? $sourceProject['ProjectName'] ?? '');
$sourceDataObjectCode = (string) ($record['SourceDataObjectCode'] ?? $sourceProject['DataObjectCode'] ?? '');
$leadDataObjectCode = (string) ($record['LeadDataObjectCode'] ?? '');
$sponsorDataObjectCode = (string) ($record['SponsorDataObjectCode'] ?? '');
?>
<div class="container mt-4">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom">
      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
        <div>
          <h3 class="mb-1"><?= h((string) ($title ?? ($id > 0 ? 'Edit Project' : 'Create Project'))) ?></h3>
          <div class="text-muted small">
            Maintain the reusable strategic project master without scrolling through one long form.
          </div>
        </div>
        <div class="d-flex gap-2">
          <a href="index.php?route=strategy-setup/projects" id="projects-back-btn" class="btn btn-sm btn-outline-secondary">Back</a>
          <?php if ($id > 0): ?>
            <a href="index.php?route=strategy-fiscal/resource-envelope-form&restricted_project_id=<?= $id ?>&restriction_code=EARMARKED&restriction_scope_type_code=PROJECT&restriction_reference=<?= urlencode($projectCode !== '' ? $projectCode : ('PROJECT-' . $id)) ?>" id="project-add-funding-btn" class="btn btn-sm btn-outline-success">Add Funding</a>
            <a href="index.php?route=strategy-setup/project-usage&id=<?= $id ?>" id="project-view-usage-btn" class="btn btn-sm btn-outline-primary">View Usage</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="card-body pt-3">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($sourceProject !== null): ?>
        <div class="alert alert-info">
          Source project: <strong><?= h((string) ($sourceProject['ProjectCode'] ?? '')) ?></strong> - <?= h((string) ($sourceProject['ProjectName'] ?? '')) ?>
          <?php if (!empty($sourceProject['DataObjectCode'])): ?>
            <span class="text-muted">(DataObject <?= h((string) $sourceProject['DataObjectCode']) ?>)</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=strategy-setup/save-project" id="project-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="ProjectID" value="<?= $id ?>">
        <input type="hidden" name="SourceSegmentNo" value="<?= (int) ($segmentMapping['SegmentNo'] ?? 0) ?>">
        <input type="hidden" name="SourceDataObjectCode" value="<?= h($sourceDataObjectCode) ?>">
        <input type="hidden" name="DataObjectCode" value="<?= h((string) ($sourceProject['DataObjectCode'] ?? $sourceDataObjectCode)) ?>">

        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 p-2 mb-3 rounded border bg-light-subtle">
          <div>
            <div class="fw-semibold"><?= h($projectName !== '' ? $projectName : 'New Project') ?></div>
            <div class="small text-muted">
              <?= h($projectCode !== '' ? $projectCode : 'Project code not yet set') ?>
              <?php if (!empty($record['LifecycleStatusCode'])): ?>
                <span class="mx-1">/</span><?= h((string) $record['LifecycleStatusCode']) ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="d-flex flex-wrap gap-3 align-items-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="projectActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="projectActiveFlag">Active</label>
            </div>
            <button type="submit" id="project-save-btn" class="btn btn-sm btn-primary"><?= $id > 0 ? 'Save Project' : 'Create Project' ?></button>
          </div>
        </div>

        <ul class="nav nav-tabs nav-tabs-sm mb-3 small" id="projectFormTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="project-summary-tab" data-bs-toggle="tab" data-bs-target="#project-summary" type="button" role="tab" aria-controls="project-summary" aria-selected="true">Summary</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="project-delivery-tab" data-bs-toggle="tab" data-bs-target="#project-delivery" type="button" role="tab" aria-controls="project-delivery" aria-selected="false">Delivery</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="project-financials-tab" data-bs-toggle="tab" data-bs-target="#project-financials" type="button" role="tab" aria-controls="project-financials" aria-selected="false">Financials</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="project-funding-tab" data-bs-toggle="tab" data-bs-target="#project-funding" type="button" role="tab" aria-controls="project-funding" aria-selected="false">Project Funding</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="project-links-tab" data-bs-toggle="tab" data-bs-target="#project-links" type="button" role="tab" aria-controls="project-links" aria-selected="false">Strategic Links</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="project-source-tab" data-bs-toggle="tab" data-bs-target="#project-source" type="button" role="tab" aria-controls="project-source" aria-selected="false">Source Mappings</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="project-custom-tab" data-bs-toggle="tab" data-bs-target="#project-custom" type="button" role="tab" aria-controls="project-custom" aria-selected="false">Custom Attributes</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="project-usage-tab" data-bs-toggle="tab" data-bs-target="#project-usage" type="button" role="tab" aria-controls="project-usage" aria-selected="false">Usage</button>
          </li>
        </ul>

        <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white" id="projectFormTabContent">
          <div class="tab-pane fade show active" id="project-summary" role="tabpanel" aria-labelledby="project-summary-tab" tabindex="0">
            <div class="mb-3">
              <h5 class="mb-1">Project Summary</h5>
              <p class="text-muted small mb-0">Core identity, classification, and ownership fields used across Strategy and funding workflows.</p>
            </div>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label" for="ProjectCode">Project Code</label>
                <input type="text" name="ProjectCode" id="ProjectCode" class="form-control form-control-sm" value="<?= h($projectCode) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="ProjectName">Project Name</label>
                <input type="text" name="ProjectName" id="ProjectName" class="form-control form-control-sm" value="<?= h($projectName) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label" for="ExternalReference">External Reference</label>
                <input type="text" name="ExternalReference" id="ExternalReference" class="form-control form-control-sm" value="<?= h((string) ($record['ExternalReference'] ?? '')) ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label" for="ProjectTypeCode">Project Type</label>
                <select name="ProjectTypeCode" id="ProjectTypeCode" class="form-select form-select-sm">
                  <?php foreach (['CAPITAL', 'REFORM', 'ICT', 'INFRASTRUCTURE', 'SERVICE_DELIVERY', 'DONOR', 'OTHER'] as $option): ?>
                    <option value="<?= h($option) ?>" <?= (string) ($record['ProjectTypeCode'] ?? 'OTHER') === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="LifecycleStatusCode">Lifecycle Status</label>
                <select name="LifecycleStatusCode" id="LifecycleStatusCode" class="form-select form-select-sm">
                  <?php foreach (['IDEA', 'PIPELINE', 'APPRAISED', 'APPROVED', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'CANCELLED'] as $option): ?>
                    <option value="<?= h($option) ?>" <?= (string) ($record['LifecycleStatusCode'] ?? 'PIPELINE') === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="PriorityCode">Priority</label>
                <select name="PriorityCode" id="PriorityCode" class="form-select form-select-sm">
                  <?php foreach (['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'] as $option): ?>
                    <option value="<?= h($option) ?>" <?= (string) ($record['PriorityCode'] ?? 'MEDIUM') === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label" for="LeadDataObjectCode">Lead DataScope Org Unit</label>
                <select name="LeadDataObjectCode" id="LeadDataObjectCode" class="form-select form-select-sm">
                  <option value="">Select lead org unit</option>
                  <?php foreach ($orgUnitOptions as $option): ?>
                    <?php $optionCode = (string) ($option['DataObjectCode'] ?? ''); ?>
                    <option value="<?= h($optionCode) ?>" <?= $leadDataObjectCode === $optionCode ? 'selected' : '' ?>>
                      <?= h(option_label_with_code($optionCode, (string) ($option['DataObjectName'] ?? ''))) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="SponsorDataObjectCode">Sponsor DataScope Org Unit</label>
                <select name="SponsorDataObjectCode" id="SponsorDataObjectCode" class="form-select form-select-sm">
                  <option value="">Select sponsor org unit</option>
                  <?php foreach ($orgUnitOptions as $option): ?>
                    <?php $optionCode = (string) ($option['DataObjectCode'] ?? ''); ?>
                    <option value="<?= h($optionCode) ?>" <?= $sponsorDataObjectCode === $optionCode ? 'selected' : '' ?>>
                      <?= h(option_label_with_code($optionCode, (string) ($option['DataObjectName'] ?? ''))) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label" for="ProjectCategoryCode">Project Category</label>
                <input type="text" name="ProjectCategoryCode" id="ProjectCategoryCode" class="form-control form-control-sm" value="<?= h((string) ($record['ProjectCategoryCode'] ?? '')) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="ProjectManagerName">Project Manager</label>
                <input type="text" name="ProjectManagerName" id="ProjectManagerName" class="form-control form-control-sm" value="<?= h((string) ($record['ProjectManagerName'] ?? '')) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="RiskRatingCode">Risk Rating</label>
                <select name="RiskRatingCode" id="RiskRatingCode" class="form-select form-select-sm">
                  <option value="">Select rating</option>
                  <?php foreach (['LOW', 'MEDIUM', 'HIGH', 'SEVERE'] as $option): ?>
                    <option value="<?= h($option) ?>" <?= (string) ($record['RiskRatingCode'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label" for="ProjectDescription">Description</label>
                <textarea name="ProjectDescription" id="ProjectDescription" class="form-control form-control-sm" rows="4"><?= h((string) ($record['ProjectDescription'] ?? '')) ?></textarea>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="project-delivery" role="tabpanel" aria-labelledby="project-delivery-tab" tabindex="0">
            <div class="mb-3">
              <h5 class="mb-1">Delivery And Implementation</h5>
              <p class="text-muted small mb-0">Track implementation timing, location, and delivery characteristics.</p>
            </div>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="StartDate" class="form-control form-control-sm" value="<?= h((string) ($record['StartDate'] ?? '')) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="EndDate" class="form-control form-control-sm" value="<?= h((string) ($record['EndDate'] ?? '')) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Location Code</label>
                <input type="text" name="LocationCode" class="form-control form-control-sm" value="<?= h((string) ($record['LocationCode'] ?? '')) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Funding Status</label>
                <select name="FundingStatusCode" class="form-select form-select-sm">
                  <option value="">Select funding status</option>
                  <?php foreach (['UNFUNDED', 'PART_FUNDED', 'FULLY_FUNDED', 'DONOR_PENDING', 'CLOSED'] as $option): ?>
                    <option value="<?= h($option) ?>" <?= (string) ($record['FundingStatusCode'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Location Description</label>
                <input type="text" name="LocationDescription" class="form-control form-control-sm" value="<?= h((string) ($record['LocationDescription'] ?? '')) ?>">
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2 h-100">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="CapitalFlag" id="projectCapitalFlag" <?= ((int) ($record['CapitalFlag'] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="projectCapitalFlag">Capital Project</label>
                  </div>
                  <div class="small text-muted mt-2">Use this for infrastructure or other capital-heavy projects.</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2 h-100">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="ProcurementRequiredFlag" id="projectProcurementFlag" <?= ((int) ($record['ProcurementRequiredFlag'] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="projectProcurementFlag">Procurement Required</label>
                  </div>
                  <div class="small text-muted mt-2">Flags projects that will move through a procurement workflow.</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2 h-100">
                  <div class="small text-muted">Current Status Snapshot</div>
                  <div class="fw-semibold mt-1"><?= h((string) ($record['LifecycleStatusCode'] ?? 'PIPELINE')) ?></div>
                  <div class="small text-muted mt-2">Priority <?= h((string) ($record['PriorityCode'] ?? 'MEDIUM')) ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="project-financials" role="tabpanel" aria-labelledby="project-financials-tab" tabindex="0">
            <div class="mb-3">
              <h5 class="mb-1">Financial Summary</h5>
              <p class="text-muted small mb-0">Capture top-level costing and affordability for pipeline and review reporting.</p>
            </div>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Estimated Total Cost</label>
                <input type="number" step="0.000001" name="EstimatedTotalCost" class="form-control form-control-sm" value="<?= h((string) ($record['EstimatedTotalCost'] ?? '')) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Approved Total Cost</label>
                <input type="number" step="0.000001" name="ApprovedTotalCost" class="form-control form-control-sm" value="<?= h((string) ($record['ApprovedTotalCost'] ?? '')) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Funding Gap</label>
                <input type="number" step="0.000001" name="FundingGapAmount" class="form-control form-control-sm" value="<?= h((string) ($record['FundingGapAmount'] ?? '')) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Currency</label>
                <input type="text" name="CurrencyCode" class="form-control form-control-sm" maxlength="10" value="<?= h((string) ($record['CurrencyCode'] ?? '')) ?>">
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Estimated</div>
                  <div class="fw-semibold"><?= h((string) ($record['EstimatedTotalCost'] ?? '0')) ?></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Approved</div>
                  <div class="fw-semibold"><?= h((string) ($record['ApprovedTotalCost'] ?? '0')) ?></div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Funding Gap</div>
                  <div class="fw-semibold"><?= h((string) ($record['FundingGapAmount'] ?? '0')) ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="project-funding" role="tabpanel" aria-labelledby="project-funding-tab" tabindex="0">
            <div class="mb-3">
              <h5 class="mb-1">Project Funding</h5>
              <p class="text-muted small mb-0">This project funding view is linked to the resource envelope so we can identify where project money is expected to come from.</p>
            </div>
            <?php if ($id <= 0): ?>
              <div class="alert alert-info py-2 mb-0">Save the project first, then add project funding lines from the resource envelope.</div>
            <?php else: ?>
              <div class="d-flex flex-wrap gap-2 mb-3">
                <a href="index.php?route=strategy-fiscal/resource-envelope-form&restricted_project_id=<?= $id ?>&restriction_code=EARMARKED&restriction_scope_type_code=PROJECT&restriction_reference=<?= urlencode($projectCode !== '' ? $projectCode : ('PROJECT-' . $id)) ?>" id="project-tab-add-funding-btn" class="btn btn-sm btn-outline-success">Add Project Funding</a>
                <a href="index.php?route=strategy-fiscal/resource-envelope-lines" id="project-tab-open-resource-envelope-btn" class="btn btn-sm btn-outline-secondary">Open Resource Envelope</a>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-3">
                  <div class="border rounded p-2 text-center h-100">
                    <div class="small text-muted">Envelope Lines</div>
                    <div class="fw-semibold"><?= (int) ($projectFundingSummary['EnvelopeLineCount'] ?? 0) ?></div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-2 text-center h-100">
                    <div class="small text-muted">Funding Sources</div>
                    <div class="fw-semibold"><?= (int) ($projectFundingSummary['FundingSourceCount'] ?? 0) ?></div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-2 text-center h-100">
                    <div class="small text-muted">Current Year</div>
                    <div class="fw-semibold"><?= h(number_format((float) ($projectFundingSummary['CurrentYearAmount'] ?? 0), 2)) ?></div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="border rounded p-2 text-center h-100">
                    <div class="small text-muted">Total Horizon</div>
                    <div class="fw-semibold"><?= h(number_format((float) ($projectFundingSummary['TotalHorizonAmount'] ?? 0), 2)) ?></div>
                  </div>
                </div>
              </div>

              <div class="row g-3">
                <div class="col-xl-5">
                  <div class="border rounded p-2 h-100">
                    <div class="fw-semibold mb-2">Funding Breakdown</div>
                    <div class="table-responsive">
                      <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>Funding Source</th>
                            <th class="text-end">Current</th>
                            <th class="text-end">Horizon</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php if ($projectFundingBreakdown === []): ?>
                          <tr><td colspan="3" class="text-center text-muted py-3">No project funding has been linked yet.</td></tr>
                        <?php else: foreach ($projectFundingBreakdown as $row): ?>
                          <tr>
                            <td>
                              <div class="fw-semibold"><?= h((string) ($row['FundingSourceName'] !== '' ? $row['FundingSourceName'] : $row['FundingTypeName'])) ?></div>
                              <div class="small text-muted">
                                <?= h(trim((string) (($row['FundingTypeCode'] ?? '') . ' / ' . (($row['FundingSourceCode'] ?? '') !== '' ? $row['FundingSourceCode'] : 'Type-level')), ' /')) ?>
                              </div>
                            </td>
                            <td class="text-end"><?= h(number_format((float) ($row['CurrentYearAmount'] ?? 0), 2)) ?></td>
                            <td class="text-end"><?= h(number_format((float) ($row['TotalHorizonAmount'] ?? 0), 2)) ?></td>
                          </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                <div class="col-xl-7">
                  <div class="border rounded p-2 h-100">
                    <div class="fw-semibold mb-2">Resource Envelope Lines</div>
                    <div class="table-responsive">
                      <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                          <tr>
                            <th>FY</th>
                            <th>Funding</th>
                            <th>Restriction</th>
                            <th class="text-end">Current</th>
                            <th class="text-end">Horizon</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php if ($projectFundingLines === []): ?>
                          <tr><td colspan="5" class="text-center text-muted py-3">No project funding lines linked yet.</td></tr>
                        <?php else: foreach ($projectFundingLines as $row): ?>
                          <?php $horizonAmount = (float) ($row['CurrentYearAmount'] ?? 0) + (float) ($row['OuterYear1Amount'] ?? 0) + (float) ($row['OuterYear2Amount'] ?? 0) + (float) ($row['OuterYear3Amount'] ?? 0) + (float) ($row['OuterYear4Amount'] ?? 0) + (float) ($row['OuterYear5Amount'] ?? 0); ?>
                          <tr>
                            <td><?= (int) ($row['FiscalYearID'] ?? 0) ?></td>
                            <td>
                              <div class="fw-semibold"><?= h((string) (($row['FundingSourceName'] ?? '') !== '' ? $row['FundingSourceName'] : ($row['FundingTypeName'] ?? ''))) ?></div>
                              <div class="small text-muted">
                                <?= h(trim((string) (($row['FundingTypeCode'] ?? '') . ' / ' . (($row['FundingSourceCode'] ?? '') !== '' ? $row['FundingSourceCode'] : 'Type-level')), ' /')) ?>
                              </div>
                            </td>
                            <td><?= h((string) ($row['RestrictionCode'] ?? $row['RestrictionReference'] ?? '')) ?></td>
                            <td class="text-end"><?= h(number_format((float) ($row['CurrentYearAmount'] ?? 0), 2)) ?></td>
                            <td class="text-end"><?= h(number_format($horizonAmount, 2)) ?></td>
                          </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="tab-pane fade" id="project-links" role="tabpanel" aria-labelledby="project-links-tab" tabindex="0">
            <div class="mb-3">
              <h5 class="mb-1">Strategic Links</h5>
              <p class="text-muted small mb-0">Connect the project to programs, objectives, and additional implementing DataScope org units.</p>
            </div>
            <div class="row g-4">
              <div class="col-md-4">
                <label class="form-label">Programs</label>
                <?php if ($supportsProjectProgramLinks): ?>
                  <select name="ProgramIDs[]" class="form-select form-select-sm" multiple size="12">
                    <?php foreach ($programOptions as $option): ?>
                      <?php $programId = (int) ($option['ProgramID'] ?? 0); ?>
                      <option value="<?= $programId ?>" <?= in_array($programId, $selectedProgramIds, true) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['ProgramCode'] ?? ''), (string) ($option['ProgramName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <div class="alert alert-warning mb-0">Run <code>create_tblSbProjectProgramLink.sql</code> to enable project-to-program links.</div>
                <?php endif; ?>
              </div>
              <div class="col-md-4">
                <label class="form-label">Objectives</label>
                <?php if ($supportsProjectObjectiveLinks): ?>
                  <select name="ObjectiveIDs[]" class="form-select form-select-sm" multiple size="12">
                    <?php foreach ($objectiveOptions as $option): ?>
                      <?php $objectiveId = (int) ($option['ObjectiveID'] ?? 0); ?>
                      <option value="<?= $objectiveId ?>" <?= in_array($objectiveId, $selectedObjectiveIds, true) ? 'selected' : '' ?>>
                        <?= h((string) ($option['ObjectiveText'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <div class="alert alert-warning mb-0">Run <code>create_tblSbProjectObjectiveLink.sql</code> to enable project-to-objective links.</div>
                <?php endif; ?>
              </div>
              <div class="col-md-4">
                <label class="form-label">Additional Implementing DataScope Org Units</label>
                <?php if ($supportsProjectOrgUnitLinks): ?>
                  <select name="LinkedDataObjectCodes[]" class="form-select form-select-sm" multiple size="12">
                    <?php foreach ($orgUnitOptions as $option): ?>
                      <?php $optionCode = (string) ($option['DataObjectCode'] ?? ''); ?>
                      <option value="<?= h($optionCode) ?>" <?= in_array($optionCode, $selectedLinkedDataObjectCodes, true) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code($optionCode, (string) ($option['DataObjectName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <div class="alert alert-warning mb-0">Run <code>create_tblSbProjectOrgUnitLink.sql</code> to enable project-to-org-unit links.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="project-source" role="tabpanel" aria-labelledby="project-source-tab" tabindex="0">
            <div class="mb-3">
              <h5 class="mb-1">Source Mappings</h5>
              <p class="text-muted small mb-0">Review where this project came from in the segment-backed source structure.</p>
            </div>
            <div class="border rounded p-2">
              <?php if ($supportsProjectSourceMaps): ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Fiscal Year</th>
                        <th>DataObject</th>
                        <th>Segment</th>
                        <th>Name</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if ($sourceMappings === []): ?>
                        <tr><td colspan="4" class="text-muted text-center py-3">No source mappings recorded yet.</td></tr>
                      <?php else: ?>
                        <?php foreach ($sourceMappings as $mapping): ?>
                          <tr>
                            <td><?= (int) ($mapping['FiscalYearID'] ?? 0) ?></td>
                            <td><?= h((string) ($mapping['DataObjectCode'] ?? '')) ?></td>
                            <td><?= h((string) ($mapping['SourceSegmentCode'] ?? '')) ?></td>
                            <td><?= h((string) ($mapping['SourceSegmentName'] ?? '')) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="text-muted">Source mappings are still stored on the base project table until <code>create_tblSbProjectSourceMap.sql</code> is run.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="tab-pane fade" id="project-custom" role="tabpanel" aria-labelledby="project-custom-tab" tabindex="0">
            <div class="mb-3">
              <h5 class="mb-1">Custom Attributes</h5>
              <p class="text-muted small mb-0">Client-specific project fields are kept here so the core project master stays stable.</p>
            </div>
            <div class="row g-3">
              <?php require __DIR__ . '/_CustomAttributes.php'; ?>
              <?php if (empty($customAttributeFields ?? [])): ?>
                <div class="col-12">
                  <div class="text-muted">No custom project attributes are configured for this environment.</div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="tab-pane fade" id="project-usage" role="tabpanel" aria-labelledby="project-usage-tab" tabindex="0">
            <div class="mb-3">
              <h5 class="mb-1">Usage Snapshot</h5>
              <p class="text-muted small mb-0">Reference-only summary of where this project is already being used.</p>
            </div>
            <div class="row g-3">
              <div class="col-md-4 col-xl-2">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Funding Lines</div>
                  <div class="fw-semibold"><?= (int) ($usageSummary['FundingSubmissionCount'] ?? 0) ?></div>
                </div>
              </div>
              <div class="col-md-4 col-xl-2">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Activities</div>
                  <div class="fw-semibold"><?= (int) ($usageSummary['ActivityCount'] ?? 0) ?></div>
                </div>
              </div>
              <div class="col-md-4 col-xl-2">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Source Mappings</div>
                  <div class="fw-semibold"><?= (int) ($usageSummary['SourceMappingCount'] ?? 0) ?></div>
                </div>
              </div>
              <div class="col-md-4 col-xl-2">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Envelope Targets</div>
                  <div class="fw-semibold"><?= (int) ($usageSummary['ResourceEnvelopeCount'] ?? 0) ?></div>
                </div>
              </div>
              <div class="col-md-4 col-xl-2">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Narratives</div>
                  <div class="fw-semibold"><?= (int) ($usageSummary['NarrativeCount'] ?? 0) ?></div>
                </div>
              </div>
              <div class="col-md-4 col-xl-2">
                <div class="border rounded p-2 text-center h-100">
                  <div class="small text-muted">Fiscal Risks</div>
                  <div class="fw-semibold"><?= (int) ($usageSummary['FiscalRiskCount'] ?? 0) ?></div>
                </div>
              </div>

              <div class="col-lg-4">
                <div class="border rounded p-2 h-100">
                  <div class="fw-semibold mb-2">Linked Programs</div>
                  <?php if ($linkedPrograms === []): ?>
                    <div class="text-muted">No linked programs.</div>
                  <?php else: ?>
                    <?php foreach ($linkedPrograms as $link): ?>
                      <span class="badge text-bg-light border me-1 mb-1"><?= h(option_label_with_code((string) ($link['ProgramCode'] ?? ''), (string) ($link['ProgramName'] ?? ''))) ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="border rounded p-2 h-100">
                  <div class="fw-semibold mb-2">Linked Objectives</div>
                  <?php if ($linkedObjectives === []): ?>
                    <div class="text-muted">No linked objectives.</div>
                  <?php else: ?>
                    <?php foreach ($linkedObjectives as $link): ?>
                      <span class="badge text-bg-light border me-1 mb-1"><?= h((string) ($link['ObjectiveText'] ?? '')) ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="border rounded p-2 h-100">
                  <div class="fw-semibold mb-2">Linked Org Units</div>
                  <?php if ($linkedOrgUnits === []): ?>
                    <div class="text-muted">No linked org units.</div>
                  <?php else: ?>
                    <?php foreach ($linkedOrgUnits as $link): ?>
                      <span class="badge text-bg-light border me-1 mb-1"><?= h(option_label_with_code((string) ($link['DataObjectCode'] ?? ''), (string) ($link['OrgUnitName'] ?? ''))) ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>

              <?php if ($id > 0): ?>
                <div class="col-12">
                  <a href="index.php?route=strategy-setup/project-usage&id=<?= $id ?>" id="project-open-usage-screen-btn" class="btn btn-sm btn-outline-primary">Open Full Usage Screen</a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
          <div class="d-flex gap-2">
            <a href="index.php?route=strategy-setup/projects" id="project-footer-back-btn" class="btn btn-sm btn-secondary">Back</a>
            <?php if ($id > 0): ?>
              <a href="index.php?route=strategy-fiscal/resource-envelope-form&restricted_project_id=<?= $id ?>&restriction_code=EARMARKED&restriction_scope_type_code=PROJECT&restriction_reference=<?= urlencode($projectCode !== '' ? $projectCode : ('PROJECT-' . $id)) ?>" id="project-footer-add-funding-btn" class="btn btn-sm btn-outline-success">Add Funding</a>
              <a href="index.php?route=strategy-setup/project-usage&id=<?= $id ?>" id="project-footer-view-usage-btn" class="btn btn-sm btn-outline-primary">View Usage</a>
            <?php endif; ?>
          </div>
          <button type="submit" id="project-footer-save-btn" class="btn btn-sm btn-primary"><?= $id > 0 ? 'Save Project' : 'Create Project' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
