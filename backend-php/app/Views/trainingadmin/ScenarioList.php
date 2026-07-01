<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => '', 'active' => '1'];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$tableInstalled = (bool) ($tableInstalled ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-mortarboard me-2"></i>Training Catalogue</h3>
        <div class="small text-muted mt-1">Maintain training scenarios, sample values, step flows, and translation-ready content definitions.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=training/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-play-circle me-1"></i>Launch View</a>
        <?php if ($tableInstalled): ?>
          <a id="training-catalogue-create-scenario-btn" href="index.php?route=training-admin/scenario-form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Scenario</a>
        <?php else: ?>
          <button id="training-catalogue-create-scenario-btn" type="button" class="btn btn-sm btn-primary" disabled><i class="bi bi-plus-circle me-1"></i>Create Scenario</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning alert-dismissible fade show">
          Run <code>create_training_scenario_catalog.sql</code> to install the training catalogue tables.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form id="training-catalogue-filter-form" method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="training-admin/scenarios">
        <div class="col-md-4">
          <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control form-control-sm" placeholder="Search code, title, screen family, module, or audience">
        </div>
        <div class="col-md-3">
          <select name="module" class="form-select form-select-sm" aria-label="Filter by module">
            <option value="">All modules</option>
            <?php foreach ($moduleOptions as $moduleOption): ?>
              <option value="<?= h((string) $moduleOption) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleOption) ? 'selected' : '' ?>>
                <?= h((string) $moduleOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="active" class="form-select form-select-sm">
            <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All statuses</option>
            <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">Filter</button>
          <a href="index.php?route=training-admin/scenarios" class="btn btn-sm btn-outline-secondary flex-fill">Reset</a>
        </div>
      </form>

      <div class="small text-muted mb-3">Open a scenario to maintain its core details, steps, samples, and multilingual content. Use the action buttons to jump straight to steps or translations.</div>

      <div class="table-responsive">
        <table id="training-catalogue-table" class="table table-striped table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th class="text-end">Order</th>
              <th>Scenario</th>
              <th>Module</th>
              <th>Audience</th>
              <th>Runner</th>
              <th>Steps</th>
              <th>Samples</th>
              <th>Translations</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="10" class="text-center text-muted py-3">No training scenarios found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php $scenarioCode = (string) ($row['ScenarioCode'] ?? ''); ?>
                <tr>
                  <td class="text-end">
                    <span class="badge text-bg-light border"><?= h((string) ((int) ($row['SortOrder'] ?? 0))) ?></span>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['ScenarioTitle'] ?? $scenarioCode)) ?></div>
                    <div class="small text-muted"><?= h($scenarioCode) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['ScreenFamily'] ?? '')) ?></div>
                  </td>
                  <td>
                    <div><?= h((string) ($row['ModuleName'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['Difficulty'] ?? '')) ?></div>
                  </td>
                  <td><?= h((string) ($row['Audience'] ?? '')) ?></td>
                  <td><code><?= h((string) ($row['RunnerRoute'] ?? '')) ?></code></td>
                  <td><?= (int) ($row['StepCount'] ?? 0) ?></td>
                  <td><?= (int) ($row['SampleCount'] ?? 0) ?></td>
                  <td><?= (int) ($row['TranslationCount'] ?? 0) ?></td>
                  <td>
                    <span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                      <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                      <a href="index.php?route=training-admin/scenario-form&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-secondary" title="Edit scenario">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <a href="index.php?route=training-admin/steps&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-primary" title="Maintain steps">
                        <i class="bi bi-list-ol"></i>
                      </a>
                      <a href="index.php?route=training-admin/translations&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-success" title="Maintain translations">
                        <i class="bi bi-translate"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
