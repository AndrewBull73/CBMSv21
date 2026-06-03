<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => ''];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$storagePath = (string) ($storagePath ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-journal-text me-2"></i>Test Script Catalogue</h3>
        <div class="small text-muted mt-1">Maintain editable test script wording, steps, expected outcomes, and custom scripts without changing PHP source code.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=screen-tests/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-play-circle me-1"></i>Tester View</a>
        <a href="index.php?route=screen-tests-admin/scenario-form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Script</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="alert alert-info">
        Editable overrides are stored at <code><?= h($storagePath) ?></code>. Built-in scripts remain as the fallback catalogue and can be reset to default from this screen.
      </div>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="screen-tests-admin/scenarios">
        <div class="col-md-6">
          <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control form-control-sm" placeholder="Search script id, title, module, screen family, audience, or route">
        </div>
        <div class="col-md-3">
          <select name="module" class="form-select form-select-sm">
            <option value="">All modules</option>
            <?php foreach ($moduleOptions as $moduleOption): ?>
              <option value="<?= h((string) $moduleOption) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleOption) ? 'selected' : '' ?>>
                <?= h((string) $moduleOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">Filter</button>
          <a href="index.php?route=screen-tests-admin/scenarios" class="btn btn-sm btn-outline-secondary flex-fill">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Script</th>
              <th>Module</th>
              <th>Target Route</th>
              <th>Steps</th>
              <th>Source</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No test scripts matched the current filters.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                $scenarioId = (string) ($row['id'] ?? '');
                $sourceState = (string) ($row['source_state'] ?? 'custom');
                $sourceLabel = match ($sourceState) {
                    'built_in' => 'Built-in',
                    'override' => 'Built-in + Override',
                    default => 'Custom',
                };
                $sourceClass = match ($sourceState) {
                    'built_in' => 'text-bg-light border',
                    'override' => 'text-bg-warning',
                    default => 'text-bg-success',
                };
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['title'] ?? $scenarioId)) ?></div>
                    <div class="small text-muted"><?= h($scenarioId) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['screen_family'] ?? '')) ?></div>
                  </td>
                  <td><?= h((string) ($row['module'] ?? '')) ?></td>
                  <td><code><?= h((string) ($row['target_route'] ?? '')) ?></code></td>
                  <td><?= (int) ($row['step_count'] ?? 0) ?></td>
                  <td><span class="badge <?= h($sourceClass) ?>"><?= h($sourceLabel) ?></span></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                      <a href="index.php?route=screen-tests-admin/scenario-form&scenario_id=<?= urlencode($scenarioId) ?>" class="btn btn-sm btn-outline-secondary" title="Edit script">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <?php if ($sourceState === 'override'): ?>
                        <form method="post" action="index.php?route=screen-tests-admin/reset-script" onsubmit="return confirm('Reset this script back to its built-in default wording?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-warning" title="Reset to default">
                            <i class="bi bi-arrow-counterclockwise"></i>
                          </button>
                        </form>
                      <?php elseif ($sourceState === 'custom'): ?>
                        <form method="post" action="index.php?route=screen-tests-admin/delete-script" onsubmit="return confirm('Delete this custom test script?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete custom script">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      <?php endif; ?>
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
