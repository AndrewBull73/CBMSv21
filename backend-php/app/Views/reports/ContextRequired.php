<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$definition = is_array($definition ?? null) ? $definition : [];
$inputs = is_array($inputs ?? null) ? $inputs : [];
$missingContext = is_array($missingContext ?? null) ? $missingContext : [];
$fiscalYears = is_array($fiscalYears ?? null) ? $fiscalYears : [];
$versions = is_array($versions ?? null) ? $versions : [];
$returnUrl = (string) ($returnUrl ?? 'index.php?route=reports/catalogue');
$launchUrl = (string) ($launchUrl ?? 'index.php?route=reports/catalogue');
$pickerUrl = (string) ($pickerUrl ?? 'index.php?route=dataobjects/picker');
$clearScopeUrl = (string) ($clearScopeUrl ?? 'index.php?route=reports/catalogue');
$canAutoLaunch = (bool) ($canAutoLaunch ?? false);

$reportName = (string) ($definition['ReportName'] ?? 'Report');
$reportCode = (string) ($definition['ReportCode'] ?? '');
$currentScope = trim((string) ($inputs['DataObjectCode'] ?? ''));
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-funnel me-2"></i>Set Report Context</h3>
    </div>
    <div class="card-body">

      <div class="alert alert-info">
        <div><strong><?= h($reportName) ?></strong><?= $reportCode !== '' ? ' <span class="text-muted">(' . h($reportCode) . ')</span>' : '' ?></div>
        <div class="small mt-1">This report launches directly from the menu, so required context must be set before SSRS can open it.</div>
      </div>

      <?php if ($missingContext !== []): ?>
        <div class="alert alert-warning">
          Missing required context: <?= h(implode(', ', $missingContext)) ?>.
        </div>
      <?php else: ?>
        <div class="alert alert-success">
          All required context is present. You can launch the report now.
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Current Fiscal Year</div>
            <div><?= (int) ($inputs['FiscalYearID'] ?? 0) > 0 ? h((string) $inputs['FiscalYearID']) : '<span class="text-muted">Not set</span>' ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Current Version</div>
            <div><?= (int) ($inputs['VersionID'] ?? 0) > 0 ? h((string) $inputs['VersionID']) : '<span class="text-muted">Not set</span>' ?></div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Current Data Scope</div>
            <div><?= $currentScope !== '' ? h($currentScope) : '<span class="text-muted">Not set</span>' ?></div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-6">
          <div class="card border-0 bg-light h-100">
            <div class="card-body">
              <h5 class="card-title">Fiscal Year and Version</h5>
              <form id="reportContextForm" method="post" action="index.php?route=context/set">
                <?= csrf_field() ?>
                <input type="hidden" name="return" value="<?= h($returnUrl) ?>">
                <input type="hidden" name="FiscalYearID" id="FiscalYearID_hidden" value="<?= (int) ($inputs['FiscalYearID'] ?? 0) ?>">
                <input type="hidden" name="VersionID" id="VersionID_hidden" value="<?= (int) ($inputs['VersionID'] ?? 0) ?>">

                <div class="mb-3">
                  <label class="form-label">Fiscal Year</label>
                  <select class="form-select" id="FiscalYearID_select">
                    <option value="">Choose fiscal year</option>
                    <?php foreach ($fiscalYears as $fy): ?>
                      <?php $fyId = (int) ($fy['FiscalYearID'] ?? 0); ?>
                      <option value="<?= $fyId ?>" <?= $fyId === (int) ($inputs['FiscalYearID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h((string) ($fy['YearLabel'] ?? $fyId)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">Version</label>
                  <select class="form-select" id="VersionID_select">
                    <option value="">Choose version</option>
                    <?php foreach ($versions as $version): ?>
                      <?php $versionId = (int) ($version['VersionID'] ?? 0); ?>
                      <option value="<?= $versionId ?>" <?= $versionId === (int) ($inputs['VersionID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h((string) ($version['VersionLabel'] ?? $versionId)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <button type="submit" class="btn btn-primary">
                  <i class="bi bi-save me-1"></i>Save Fiscal Context
                </button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card border-0 bg-light h-100">
            <div class="card-body">
              <h5 class="card-title">Data Scope</h5>
              <p class="text-muted small">Choose the data object the report should run against.</p>
              <div class="d-flex gap-2 flex-wrap">
                <a href="<?= h($pickerUrl) ?>" class="btn btn-outline-primary">
                  <i class="bi bi-diagram-3 me-1"></i>Select Data Scope
                </a>
                <?php if ($currentScope !== ''): ?>
                  <a href="<?= h($clearScopeUrl) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle me-1"></i>Clear Scope
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-4">
        <a href="index.php?route=reports/catalogue" class="btn btn-outline-secondary">Back to Catalogue</a>
        <a href="<?= h($launchUrl) ?>" class="btn btn-success<?= $canAutoLaunch ? '' : ' disabled' ?>"<?= $canAutoLaunch ? '' : ' aria-disabled="true"' ?>>
          <i class="bi bi-play-circle me-1"></i>Launch Report
        </a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fySelect = document.getElementById('FiscalYearID_select');
    const verSelect = document.getElementById('VersionID_select');
    const fyHidden = document.getElementById('FiscalYearID_hidden');
    const verHidden = document.getElementById('VersionID_hidden');

    if (!fySelect || !verSelect || !fyHidden || !verHidden) {
        return;
    }

    fySelect.addEventListener('change', async () => {
        fyHidden.value = fySelect.value;
        verHidden.value = '';
        verSelect.innerHTML = '<option value="">Loading versions...</option>';

        if (!fySelect.value) {
            verSelect.innerHTML = '<option value="">Choose version</option>';
            return;
        }

        try {
            const response = await fetch('index.php?route=context/listVersions&FiscalYearID=' + encodeURIComponent(fySelect.value), { credentials: 'same-origin' });
            const versions = await response.json();
            verSelect.innerHTML = '<option value="">Choose version</option>';

            if (Array.isArray(versions)) {
                versions.forEach((version) => {
                    const option = document.createElement('option');
                    option.value = version.VersionID;
                    option.textContent = version.VersionLabel || version.VersionID;
                    verSelect.appendChild(option);
                });
            }
        } catch (error) {
            verSelect.innerHTML = '<option value="">Unable to load versions</option>';
        }
    });

    verSelect.addEventListener('change', () => {
        verHidden.value = verSelect.value;
    });

    <?php if ($canAutoLaunch): ?>
    window.setTimeout(() => {
        window.location.href = <?= json_encode($launchUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    }, 400);
    <?php endif; ?>
});
</script>
