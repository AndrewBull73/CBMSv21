<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$context = is_array($context ?? null) ? $context : [];
$monthOptions = is_array($monthOptions ?? null) ? $monthOptions : [];
$yearLabel = (string) ($context['YearLabel'] ?? '');
$versionLabel = (string) ($context['VersionLabel'] ?? '');
$startMonthNo = (int) ($record['StartMonthNo'] ?? 1);
$startMonthLabel = (string) ($monthOptions[$startMonthNo] ?? ('Month ' . $startMonthNo));

$screenHeader = [
    'title' => (string) __t('strategy_fiscal_period_labels'),
    'icon' => 'bi-calendar3',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h(__t('strategy_fiscal_start_month')) ?></div>
              <div class="fs-5 fw-semibold"><?= h($startMonthLabel) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h(__t('strategy_bp_period_labels')) ?></div>
              <div class="fs-5 fw-semibold">12</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h(__t('strategy_outer_year_labels')) ?></div>
              <div class="fs-5 fw-semibold">5</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h(__t('status')) ?></div>
              <div class="fs-5 fw-semibold"><?= empty($periodConfigAvailable) ? 'Pending setup' : 'Ready' ?></div>
            </div>
          </div>
        </div>
      </div>

      <?php if (empty($periodConfigAvailable)): ?>
        <div class="alert alert-warning">
          <?= h(__t('strategy_run_before_maintaining_fiscal_periods')) ?> <code>create_tblSbFiscalPeriodConfig.sql</code>.
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        <?= h(__t('strategy_fiscal_period_labels_intro')) ?> <?= h(__t('strategy_auto_generation_note')) ?>
      </div>

      <form method="post" action="index.php?route=strategy-config/save-fiscal-periods">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Generation Controls</h5>
          </div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-4 col-xl-3">
                <label for="StartMonthNo" class="form-label"><?= h(__t('strategy_fiscal_start_month')) ?></label>
                <select name="StartMonthNo" id="StartMonthNo" class="form-select" <?= empty($periodConfigAvailable) ? 'disabled' : '' ?>>
                  <?php foreach ($monthOptions as $monthNo => $monthLabel): ?>
                    <option value="<?= (int) $monthNo ?>" <?= $startMonthNo === (int) $monthNo ? 'selected' : '' ?>><?= h((string) $monthLabel) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-8 col-xl-9">
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" id="jsGeneratePeriodLabels" class="btn btn-outline-primary" <?= empty($periodConfigAvailable) ? 'disabled' : '' ?>><?= h(__t('strategy_auto_generate_labels')) ?></button>
                  <button type="button" id="jsResetPeriodLabels" class="btn btn-outline-secondary" <?= empty($periodConfigAvailable) ? 'disabled' : '' ?>><?= h(__t('strategy_reset_generated_defaults')) ?></button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-xl-7">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0"><?= h(__t('strategy_bp_period_labels')) ?></h5>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width: 120px;"><?= h(__t('strategy_period')) ?></th>
                        <th><?= h(__t('strategy_label')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php for ($i = 1; $i <= 12; $i++): ?>
                        <tr>
                          <td class="fw-semibold">BP<?= $i ?></td>
                          <td>
                            <input type="text" name="BP<?= $i ?>Label" id="BP<?= $i ?>Label" class="form-control" value="<?= h((string) ($record['BP' . $i . 'Label'] ?? '')) ?>" <?= empty($periodConfigAvailable) ? 'disabled' : '' ?>>
                          </td>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-5">
            <div class="card shadow-sm mb-3">
              <div class="card-header">
                <h5 class="mb-0"><?= h(__t('strategy_outer_year_labels')) ?></h5>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="width: 140px;"><?= h(__t('strategy_period')) ?></th>
                        <th><?= h(__t('strategy_label')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <tr>
                          <td class="fw-semibold"><?= h(__t('strategy_outer_year')) ?> <?= $i ?></td>
                          <td>
                            <input type="text" name="OuterYear<?= $i ?>Label" id="OuterYear<?= $i ?>Label" class="form-control" value="<?= h((string) ($record['OuterYear' . $i . 'Label'] ?? '')) ?>" <?= empty($periodConfigAvailable) ? 'disabled' : '' ?>>
                          </td>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="card shadow-sm">
              <div class="card-header">
                <h5 class="mb-0">Settings</h5>
              </div>
              <div class="card-body">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="ActiveFlag" id="fiscalPeriodConfigActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?> <?= empty($periodConfigAvailable) ? 'disabled' : '' ?>>
                  <label class="form-check-label" for="fiscalPeriodConfigActiveFlag"><?= h(__t('active')) ?></label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
          <button type="submit" class="btn btn-primary" <?= empty($periodConfigAvailable) ? 'disabled' : '' ?>><?= h(__t('strategy_save_fiscal_period_labels')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const startMonthSelect = document.getElementById('StartMonthNo');
    const generateButton = document.getElementById('jsGeneratePeriodLabels');
    const resetButton = document.getElementById('jsResetPeriodLabels');
    const startYear = <?= (int) ($fiscalYearId > 0 ? $fiscalYearId : (int) date('Y')) ?>;
    const monthNames = <?= json_encode(array_values($monthOptions), JSON_THROW_ON_ERROR) ?>;

    if (!startMonthSelect || !generateButton || !resetButton) {
        return;
    }

    const generateLabels = function () {
        const startMonthNo = parseInt(startMonthSelect.value || '1', 10);
        for (let i = 1; i <= 12; i += 1) {
            const monthIndex = ((startMonthNo - 1) + (i - 1)) % 12;
            const yearOffset = Math.floor(((startMonthNo - 1) + (i - 1)) / 12);
            const input = document.getElementById('BP' + i + 'Label');
            if (input) {
                input.value = monthNames[monthIndex] + ' ' + (startYear + yearOffset);
            }
        }
        for (let i = 1; i <= 5; i += 1) {
            const oyStart = startYear + i;
            const oyEnd = String(oyStart + 1).slice(-2);
            const input = document.getElementById('OuterYear' + i + 'Label');
            if (input) {
                input.value = oyStart + '/' + oyEnd;
            }
        }
    };

    generateButton.addEventListener('click', generateLabels);
    resetButton.addEventListener('click', generateLabels);
});
</script>
