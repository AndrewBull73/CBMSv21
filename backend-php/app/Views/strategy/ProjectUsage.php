<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money_fmt')) {
    function money_fmt(mixed $value): string
    {
        return number_format((float) $value, 2);
    }
}

$record = is_array($record ?? null) ? $record : [];
$usageSummary = is_array($usageSummary ?? null) ? $usageSummary : [];
$sourceMappings = is_array($sourceMappings ?? null) ? $sourceMappings : [];
$linkedPrograms = is_array($linkedPrograms ?? null) ? $linkedPrograms : [];
$linkedObjectives = is_array($linkedObjectives ?? null) ? $linkedObjectives : [];
$linkedOrgUnits = is_array($linkedOrgUnits ?? null) ? $linkedOrgUnits : [];
$fundingSubmissionUsage = is_array($fundingSubmissionUsage ?? null) ? $fundingSubmissionUsage : [];
$activityUsage = is_array($activityUsage ?? null) ? $activityUsage : [];
$resourceEnvelopeUsage = is_array($resourceEnvelopeUsage ?? null) ? $resourceEnvelopeUsage : [];
$projectFundingSummary = is_array($projectFundingSummary ?? null) ? $projectFundingSummary : [];
$projectFundingBreakdown = is_array($projectFundingBreakdown ?? null) ? $projectFundingBreakdown : [];
$narrativeUsage = is_array($narrativeUsage ?? null) ? $narrativeUsage : [];
$fiscalRiskUsage = is_array($fiscalRiskUsage ?? null) ? $fiscalRiskUsage : [];
$id = (int) ($record['ProjectID'] ?? 0);
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$projectTitle = trim((string) ($record['ProjectName'] ?? ''));
$projectCode = trim((string) ($record['ProjectCode'] ?? ''));

$screenHeader = [
    'title' => 'Project Usage',
    'icon' => 'bi-diagram-3',
];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-2">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>
      <div class="small text-muted mb-3">
        Selected project:
        <strong><?= h($projectTitle !== '' ? $projectTitle : 'Unnamed project') ?></strong>
        <?php if ($projectCode !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($projectCode) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body text-center py-3"><div class="small text-muted">Funding lines</div><div class="fw-semibold"><?= (int) ($usageSummary['FundingSubmissionCount'] ?? 0) ?></div></div></div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body text-center py-3"><div class="small text-muted">Activities</div><div class="fw-semibold"><?= (int) ($usageSummary['ActivityCount'] ?? 0) ?></div></div></div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body text-center py-3"><div class="small text-muted">Source mappings</div><div class="fw-semibold"><?= (int) ($usageSummary['SourceMappingCount'] ?? 0) ?></div></div></div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body text-center py-3"><div class="small text-muted">Envelope targets</div><div class="fw-semibold"><?= (int) ($usageSummary['ResourceEnvelopeCount'] ?? 0) ?></div></div></div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body text-center py-3"><div class="small text-muted">Narratives</div><div class="fw-semibold"><?= (int) ($usageSummary['NarrativeCount'] ?? 0) ?></div></div></div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body text-center py-3"><div class="small text-muted">Fiscal risks</div><div class="fw-semibold"><?= (int) ($usageSummary['FiscalRiskCount'] ?? 0) ?></div></div></div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this inquiry before recoding, archiving, or reshaping a project. It shows where the project already participates in funding submissions, activities, envelope restrictions, narratives, and fiscal risk records.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Project Actions</h5>
          <div class="d-flex flex-wrap gap-2">
            <a href="index.php?route=strategy-setup/projects" id="project-usage-back-btn" class="btn btn-sm btn-outline-secondary">Back to Register</a>
            <a href="index.php?route=strategy-fiscal/resource-envelope-form&restricted_project_id=<?= $id ?>&restriction_code=EARMARKED&restriction_scope_type_code=PROJECT&restriction_reference=<?= urlencode((string) ($projectCode !== '' ? $projectCode : ('PROJECT-' . $id))) ?>" id="project-usage-add-funding-btn" class="btn btn-sm btn-outline-success">Add Funding</a>
            <a href="index.php?route=strategy-setup/project-form&id=<?= $id ?>" id="project-usage-edit-project-btn" class="btn btn-sm btn-primary">Edit Project</a>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-xl-6">
              <div class="card shadow-sm h-100">
                <div class="card-header"><h5 class="mb-0">Strategic Links</h5></div>
                <div class="card-body">
                  <div class="mb-3">
                    <div class="small text-muted mb-1">Programs</div>
                    <?php if ($linkedPrograms === []): ?><div class="text-muted">No linked programs.</div><?php else: foreach ($linkedPrograms as $row): ?><span class="badge text-bg-light border me-1 mb-1"><?= h(trim((string) (($row['ProgramCode'] ?? '') . ' - ' . ($row['ProgramName'] ?? '')), ' -')) ?></span><?php endforeach; endif; ?>
                  </div>
                  <div class="mb-3">
                    <div class="small text-muted mb-1">Objectives</div>
                    <?php if ($linkedObjectives === []): ?><div class="text-muted">No linked objectives.</div><?php else: foreach ($linkedObjectives as $row): ?><span class="badge text-bg-light border me-1 mb-1"><?= h((string) ($row['ObjectiveText'] ?? '')) ?></span><?php endforeach; endif; ?>
                  </div>
                  <div>
                    <div class="small text-muted mb-1">Org Units</div>
                    <?php if ($linkedOrgUnits === []): ?><div class="text-muted">No linked org units.</div><?php else: foreach ($linkedOrgUnits as $row): ?><span class="badge text-bg-light border me-1 mb-1"><?= h(trim((string) (($row['DataObjectCode'] ?? '') . ' - ' . ($row['OrgUnitName'] ?? '')), ' -')) ?></span><?php endforeach; endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-xl-6">
              <div class="card shadow-sm h-100">
                <div class="card-header"><h5 class="mb-0">Source Mappings</h5></div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="project-usage-source-mappings-table">
                      <thead class="table-light"><tr><th>FY</th><th>DataObject</th><th>Segment</th><th>Name</th></tr></thead>
                      <tbody>
                      <?php if ($sourceMappings === []): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No source mappings found.</td></tr>
                      <?php else: foreach ($sourceMappings as $row): ?>
                        <tr>
                          <td><?= (int) ($row['FiscalYearID'] ?? 0) ?></td>
                          <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                          <td><?= h((string) ($row['SourceSegmentCode'] ?? '')) ?></td>
                          <td><?= h((string) ($row['SourceSegmentName'] ?? '')) ?></td>
                        </tr>
                      <?php endforeach; endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Funding Submission Usage</h5></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="project-funding-submission-usage-table">
              <thead class="table-light"><tr><th>Submission</th><th>Line</th><th>Status</th><th>DataObject</th><th class="text-end">Current Year</th></tr></thead>
              <tbody>
              <?php if ($fundingSubmissionUsage === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No funding submission lines use this project.</td></tr>
              <?php else: foreach ($fundingSubmissionUsage as $row): ?>
                <tr>
                  <td><?= h((string) ($row['RequestTitle'] ?? '')) ?></td>
                  <td><?= h((string) ($row['BidTitle'] ?? '')) ?></td>
                  <td><?= h((string) ($row['LineStatusCode'] ?? $row['SubmissionStatusCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                  <td class="text-end"><?= money_fmt($row['CurrentYearRequestedAmount'] ?? 0) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Activity Usage</h5></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="project-activity-usage-table">
              <thead class="table-light"><tr><th>Activity</th><th>Program</th><th>Output</th><th>Type</th><th>Status</th></tr></thead>
              <tbody>
              <?php if ($activityUsage === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No activities are linked to this project.</td></tr>
              <?php else: foreach ($activityUsage as $row): ?>
                <tr>
                  <td><?= h((string) ($row['ActivityName'] ?? '')) ?></td>
                  <td><?= h((string) ($row['ProgramName'] ?? '')) ?></td>
                  <td><?= h((string) ($row['OutputName'] ?? '')) ?></td>
                  <td><?= h((string) ($row['ActivityTypeCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['ImplementationStatusCode'] ?? '')) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Project Funding</h5>
          <a href="index.php?route=strategy-fiscal/resource-envelope-lines" id="project-open-resource-envelope-btn" class="btn btn-sm btn-outline-secondary">Open Resource Envelope</a>
        </div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <div class="border rounded p-2 text-center h-100"><div class="small text-muted">Envelope lines</div><div class="fw-semibold"><?= (int) ($projectFundingSummary['EnvelopeLineCount'] ?? 0) ?></div></div>
            </div>
            <div class="col-md-3">
              <div class="border rounded p-2 text-center h-100"><div class="small text-muted">Funding sources</div><div class="fw-semibold"><?= (int) ($projectFundingSummary['FundingSourceCount'] ?? 0) ?></div></div>
            </div>
            <div class="col-md-3">
              <div class="border rounded p-2 text-center h-100"><div class="small text-muted">Current year</div><div class="fw-semibold"><?= money_fmt($projectFundingSummary['CurrentYearAmount'] ?? 0) ?></div></div>
            </div>
            <div class="col-md-3">
              <div class="border rounded p-2 text-center h-100"><div class="small text-muted">Total horizon</div><div class="fw-semibold"><?= money_fmt($projectFundingSummary['TotalHorizonAmount'] ?? 0) ?></div></div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="project-funding-breakdown-table">
              <thead class="table-light"><tr><th>Funding Source</th><th class="text-end">Lines</th><th class="text-end">Current</th><th class="text-end">Horizon</th></tr></thead>
              <tbody>
              <?php if ($projectFundingBreakdown === []): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No funding sources have been linked to this project yet.</td></tr>
              <?php else: foreach ($projectFundingBreakdown as $row): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h((string) (($row['FundingSourceName'] ?? '') !== '' ? $row['FundingSourceName'] : ($row['FundingTypeName'] ?? ''))) ?></div>
                    <div class="small text-muted"><?= h(trim((string) (($row['FundingTypeCode'] ?? '') . ' / ' . (($row['FundingSourceCode'] ?? '') !== '' ? $row['FundingSourceCode'] : 'Type-level')), ' /')) ?></div>
                  </td>
                  <td class="text-end"><?= (int) ($row['EnvelopeLineCount'] ?? 0) ?></td>
                  <td class="text-end"><?= money_fmt($row['CurrentYearAmount'] ?? 0) ?></td>
                  <td class="text-end"><?= money_fmt($row['TotalHorizonAmount'] ?? 0) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Resource Envelope Usage</h5></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="project-resource-envelope-usage-table">
              <thead class="table-light"><tr><th>FY</th><th>Version</th><th>Funding</th><th>Instrument</th><th>Restriction</th><th class="text-end">Current</th><th class="text-end">Horizon</th></tr></thead>
              <tbody>
              <?php if ($resourceEnvelopeUsage === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No resource envelope targets use this project.</td></tr>
              <?php else: foreach ($resourceEnvelopeUsage as $row): ?>
                <?php $horizonAmount = (float) ($row['CurrentYearAmount'] ?? 0) + (float) ($row['OuterYear1Amount'] ?? 0) + (float) ($row['OuterYear2Amount'] ?? 0) + (float) ($row['OuterYear3Amount'] ?? 0) + (float) ($row['OuterYear4Amount'] ?? 0) + (float) ($row['OuterYear5Amount'] ?? 0); ?>
                <tr>
                  <td><?= (int) ($row['FiscalYearID'] ?? 0) ?></td>
                  <td><?= (int) ($row['VersionID'] ?? 0) ?></td>
                  <td>
                    <div class="fw-semibold"><?= h((string) (($row['FundingSourceName'] ?? '') !== '' ? $row['FundingSourceName'] : ($row['FundingTypeName'] ?? ''))) ?></div>
                    <div class="small text-muted"><?= h(trim((string) (($row['FundingTypeCode'] ?? '') . ' / ' . (($row['FundingSourceCode'] ?? '') !== '' ? $row['FundingSourceCode'] : 'Type-level')), ' /')) ?></div>
                  </td>
                  <td><?= h((string) ($row['FinancingInstrumentCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['RestrictionCode'] ?? $row['RestrictionReference'] ?? '')) ?></td>
                  <td class="text-end"><?= money_fmt($row['CurrentYearAmount'] ?? 0) ?></td>
                  <td class="text-end"><?= money_fmt($horizonAmount) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Narrative Usage</h5></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="project-narrative-usage-table">
              <thead class="table-light"><tr><th>FY</th><th>Version</th><th>Section</th><th>Title</th><th>Scope</th></tr></thead>
              <tbody>
              <?php if ($narrativeUsage === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No narratives are linked to this project.</td></tr>
              <?php else: foreach ($narrativeUsage as $row): ?>
                <tr>
                  <td><?= (int) ($row['FiscalYearID'] ?? 0) ?></td>
                  <td><?= (int) ($row['VersionID'] ?? 0) ?></td>
                  <td><?= h((string) ($row['SectionCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['NarrativeTitle'] ?? '')) ?></td>
                  <td><?= h((string) (($row['ProgramName'] ?? '') !== '' ? $row['ProgramName'] : (($row['SectorName'] ?? '') !== '' ? $row['SectorName'] : ($row['OrgUnitName'] ?? 'Global')))) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Fiscal Risk Usage</h5></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="project-fiscal-risk-usage-table">
              <thead class="table-light"><tr><th>Type</th><th>Title</th><th class="text-end">Likelihood</th><th class="text-end">Impact</th><th>Owner</th><th class="text-end">Exposure</th></tr></thead>
              <tbody>
              <?php if ($fiscalRiskUsage === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No fiscal risks are linked to this project.</td></tr>
              <?php else: foreach ($fiscalRiskUsage as $row): ?>
                <tr>
                  <td><?= h((string) ($row['RiskTypeCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['RiskTitle'] ?? '')) ?></td>
                  <td class="text-end"><?= h((string) ($row['LikelihoodScore'] ?? '')) ?></td>
                  <td class="text-end"><?= h((string) ($row['ImpactScore'] ?? '')) ?></td>
                  <td><?= h((string) ($row['OwnerOrgUnitName'] ?? '')) ?></td>
                  <td class="text-end"><?= money_fmt($row['EstimatedFiscalExposure'] ?? 0) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
