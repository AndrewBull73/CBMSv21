<?php
declare(strict_types=1);

/** @var array $contextLabels */
/** @var array $overview */
/** @var array $workflowState */
/** @var array $sectorTotals */
/** @var array $programTotals */
/** @var array $economicTotals */
/** @var array $narratives */

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

$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$totalBudget = (float) ($overview['TotalBudgetAmount'] ?? 0);
$workflowStatusLabel = (string) ($workflowState['WorkflowStatusLabel'] ?? 'Draft');
$workflowStatusMessage = (string) ($workflowState['StatusMessage'] ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-grid-1x2 me-2"></i><?= h(__t('strategy_budgeting_overview')) ?></h3>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="index.php?route=strategy-config/import-dashboard" class="btn btn-sm btn-outline-secondary"><?= h(__t('strategy_import_dimensions')) ?></a>
        <a href="index.php?route=strategy-config/configuration-readiness" class="btn btn-sm btn-outline-secondary"><?= h(__t('strategy_configuration_readiness')) ?></a>
        <a href="index.php?route=strategy-reports/submission-readiness" class="btn btn-sm btn-primary"><?= h(__t('strategy_submission_readiness')) ?></a>
        <div class="text-end ms-sm-2">
          <div class="small text-muted"><?= h(__t('strategy_total_budget_active_context')) ?></div>
          <div class="fs-5 fw-semibold"><?= money_fmt($totalBudget) ?></div>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">
        <?= h(__t('strategy_fiscal_context')) ?>:
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

  <div class="alert alert-info border-0 shadow-sm mb-4">
    <div class="fw-semibold mb-1"><?= h(__t('strategy_what_this_screen_is_for')) ?></div>
    <div class="small">
      This page is the landing page for Strategic Budgeting. It is mainly for budget analysts, planners, and reviewers who want a quick sense of:
      the active strategic version, how much budget has been captured, whether the setup looks complete, and where to go next.
    </div>
  </div>

  <?php
    $workflowReturnRoute = 'strategy/index';
    require __DIR__ . '/_WorkflowPanel.php';
  ?>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-4">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">How To Use This Page</h5>
        </div>
        <div class="card-body">
          <p class="mb-2">Think of the strategic model in three layers:</p>
          <p class="mb-2"><strong>1. Setup:</strong> sectors, programs, subprograms, funding, and economic items.</p>
          <p class="mb-2"><strong>2. Planning:</strong> objectives, indicators, outputs, activities, and budgets.</p>
          <p class="mb-0"><strong>3. Review:</strong> configuration readiness, submission readiness, reports, narratives, risks, and workflow status.</p>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Who This Helps</h5>
        </div>
        <div class="card-body">
          <p class="mb-2"><strong>Analysts:</strong> enter and maintain strategic structure and budgets.</p>
          <p class="mb-2"><strong>Reviewers:</strong> check configuration readiness, submission readiness, reports, risks, and BSP content.</p>
          <p class="mb-0"><strong>Approvers:</strong> confirm the version is complete, then submit, approve, or lock it.</p>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Current Context</h5>
        </div>
        <div class="card-body">
          <p class="mb-2"><strong>Workflow:</strong> <?= h($workflowStatusLabel) ?></p>
          <p class="mb-2"><strong>Total strategic budget:</strong> <?= money_fmt($totalBudget) ?></p>
          <p class="mb-0 text-muted small"><?= h($workflowStatusMessage !== '' ? $workflowStatusMessage : 'Strategic workflow state is available for this version.') ?></p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-4">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Start Here</h5>
        </div>
        <div class="card-body d-flex flex-column gap-2">
          <p class="text-muted small mb-2">Use these first if you are still building or cleaning the strategic dataset.</p>
          <a href="index.php?route=strategy-config/segment-mapping" class="btn btn-outline-primary btn-sm">Segment Mapping</a>
          <a href="index.php?route=strategy-config/import-dashboard" class="btn btn-outline-primary btn-sm"><?= h(__t('strategy_import_dimensions')) ?></a>
          <a href="index.php?route=strategy-config/configuration-readiness" class="btn btn-outline-primary btn-sm"><?= h(__t('strategy_configuration_readiness')) ?></a>
          <a href="index.php?route=strategy-reports/submission-readiness" class="btn btn-outline-primary btn-sm"><?= h(__t('strategy_submission_readiness')) ?></a>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Maintain Data</h5>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
          <a href="index.php?route=strategy-setup/sectors" class="btn btn-outline-secondary btn-sm">Sectors</a>
          <a href="index.php?route=strategy-setup/programs" class="btn btn-outline-secondary btn-sm">Programs</a>
          <a href="index.php?route=strategy-setup/sub-programs" class="btn btn-outline-secondary btn-sm">SubPrograms</a>
          <a href="index.php?route=strategy-setup/economic-items" class="btn btn-outline-secondary btn-sm">Economic Items</a>
          <a href="index.php?route=strategy-setup/funding-sources" class="btn btn-outline-secondary btn-sm">Funding Sources</a>
          <a href="index.php?route=strategy-performance/objectives" class="btn btn-outline-secondary btn-sm">Objectives</a>
          <a href="index.php?route=strategy-performance/indicators" class="btn btn-outline-secondary btn-sm">Indicators</a>
          <a href="index.php?route=strategy-performance/targets" class="btn btn-outline-secondary btn-sm">Targets</a>
          <a href="index.php?route=strategy-delivery/outputs" class="btn btn-outline-secondary btn-sm">Outputs</a>
          <a href="index.php?route=strategy-delivery/activities" class="btn btn-outline-secondary btn-sm">Activities</a>
          <a href="index.php?route=strategy-delivery/budgets" class="btn btn-outline-secondary btn-sm">Budgets</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Review, Publish And Control</h5>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
          <a href="index.php?route=strategy-reports/summary" class="btn btn-outline-success btn-sm">Strategic Summary</a>
          <a href="index.php?route=strategy-reports/sector-budget" class="btn btn-outline-success btn-sm">Sector Budget Report</a>
          <a href="index.php?route=strategy-reports/program-budget" class="btn btn-outline-success btn-sm">Program Budget Report</a>
          <a href="index.php?route=strategy-reports/mtff" class="btn btn-outline-success btn-sm">MTFF View</a>
          <a href="index.php?route=strategy-reports/performance" class="btn btn-outline-success btn-sm">Performance Report</a>
          <a href="index.php?route=strategy-fiscal/overview" class="btn btn-outline-info btn-sm">Fiscal Overview</a>
          <a href="index.php?route=strategy-fiscal/resource-envelope" class="btn btn-outline-info btn-sm">Resource Envelope Summary</a>
          <a href="index.php?route=strategy-fiscal/resource-envelope-lines" class="btn btn-outline-info btn-sm">Resource Envelope</a>
          <a href="index.php?route=strategy-fiscal/sector-ceilings" class="btn btn-outline-info btn-sm">Sector Ceilings</a>
          <a href="index.php?route=strategy-fiscal/ceiling-vs-plan" class="btn btn-outline-info btn-sm">Ceiling vs Plan</a>
          <a href="index.php?route=strategy-governance/narratives" class="btn btn-outline-warning btn-sm">BSP Narratives</a>
          <a href="index.php?route=strategy-governance/fiscal-risks" class="btn btn-outline-warning btn-sm">Fiscal Risks</a>
          <a href="index.php?route=strategy-governance/program-risks" class="btn btn-outline-warning btn-sm">Program Risks</a>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-light border shadow-sm mb-4">
    <div class="fw-semibold mb-1">What the total budget means</div>
    <div class="small">
      The total shown on this page is the sum of all active <strong>Activity Budget</strong> lines for the active fiscal year and version.
      If the figure looks unexpected, check the <a href="index.php?route=strategy-delivery/budgets">Activity Budgets</a> screen and the <a href="index.php?route=strategy-reports/sector-budget">Sector Budget Report</a>.
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Programs</div>
          <div class="fs-4 fw-semibold"><?= (int) ($overview['ProgramCount'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Outputs</div>
          <div class="fs-4 fw-semibold"><?= (int) ($overview['OutputCount'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Activities</div>
          <div class="fs-4 fw-semibold"><?= (int) ($overview['ActivityCount'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Objectives</div>
          <div class="fs-4 fw-semibold"><?= (int) ($overview['ObjectiveCount'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Indicators</div>
          <div class="fs-4 fw-semibold"><?= (int) ($overview['IndicatorCount'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-2">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Sectors</div>
          <div class="fs-4 fw-semibold"><?= (int) ($overview['SectorCount'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Top Sectors</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Sector</th>
                  <th class="text-end">Programs</th>
                  <th class="text-end">Activities</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($sectorTotals === []): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No sector budget rows yet.</td></tr>
              <?php else: ?>
                <?php foreach ($sectorTotals as $row): ?>
                  <tr>
                    <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                    <td class="text-end"><?= (int) ($row['ProgramCount'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($row['ActivityCount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['TotalAmount'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Top Economic Items</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Code</th>
                  <th>Economic Item</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($economicTotals === []): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">No economic item budget rows yet.</td></tr>
              <?php else: ?>
                <?php foreach ($economicTotals as $row): ?>
                  <tr>
                    <td><?= h((string) ($row['EconomicCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['EconomicName'] ?? '')) ?></td>
                    <td class="text-end"><?= money_fmt($row['TotalAmount'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-8">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Top Programs</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Program</th>
                  <th>Sector</th>
                  <th>Owner</th>
                  <th class="text-end">Outputs</th>
                  <th class="text-end">Activities</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($programTotals === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No program budget rows yet.</td></tr>
              <?php else: ?>
                <?php foreach ($programTotals as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['ProgramName'] ?? '')) ?></div>
                      <?php if (!empty($row['ProgramCode'])): ?>
                        <div class="small text-muted"><?= h((string) $row['ProgramCode']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                    <td><?= h((string) ($row['OrgUnitName'] ?? '')) ?></td>
                    <td class="text-end"><?= (int) ($row['OutputCount'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($row['ActivityCount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['TotalAmount'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0">Narrative Drafts</h5>
        </div>
        <div class="card-body">
          <?php if ($narratives === []): ?>
            <p class="text-muted mb-0">No narrative rows yet for this fiscal context.</p>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($narratives as $row): ?>
                <div class="list-group-item px-0">
                  <div class="d-flex justify-content-between gap-2">
                    <div>
                      <div class="fw-semibold"><?= h((string) ($row['NarrativeTitle'] ?? 'Untitled')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['SectionCode'] ?? '')) ?></div>
                    </div>
                    <?php if ((int) ($row['LockedFlag'] ?? 0) === 1): ?>
                      <span class="badge text-bg-secondary align-self-start">Locked</span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</div>
