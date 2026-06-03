<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$summary = is_array($summary ?? null) ? $summary : [];
$groups = is_array($groups ?? null) ? $groups : [];

$statusBadge = static function (string $status): string {
    return match ($status) {
        'configured' => 'text-bg-success',
        'alias_only' => 'text-bg-warning',
        default => 'text-bg-secondary',
    };
};

$statusLabel = static function (string $status): string {
    return match ($status) {
        'configured' => 'Configured',
        'alias_only' => 'Legacy Alias In Use',
        default => 'Missing',
    };
};
?>

<div class="container mt-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h2 class="mb-1"><i class="bi bi-diagram-3 me-2"></i>System Settings Usage Map</h2>
      <div class="text-muted small">This screen shows what each system setting affects, whether the canonical key is configured, and where older alias keys are still being relied on.</div>
    </div>
    <div class="d-flex gap-2">
      <a href="index.php?route=system-settings/list" class="btn btn-outline-primary btn-sm">System Settings</a>
      <a href="index.php?route=base-config/readiness" class="btn btn-outline-primary btn-sm">Base Configuration Readiness</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Defined Settings</div>
          <div class="fs-4 fw-semibold"><?= (int) ($summary['total'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Canonical Keys Configured</div>
          <div class="fs-4 fw-semibold text-success"><?= (int) ($summary['configured'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Legacy Alias In Use</div>
          <div class="fs-4 fw-semibold text-warning"><?= (int) ($summary['alias_only'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body py-3">
          <div class="text-muted small">Missing Definitions</div>
          <div class="fs-4 fw-semibold"><?= (int) ($summary['missing'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-info border-0 shadow-sm py-2 mb-3 small">
    A setting marked <strong>Legacy Alias In Use</strong> means the application can still resolve it, but the old key name is still present in the database. That is a good signal for follow-up cleanup.
  </div>

  <div class="accordion" id="settingsUsageAccordion">
    <?php $groupIndex = 0; foreach ($groups as $category => $items): $groupIndex++; ?>
      <div class="accordion-item shadow-sm mb-3 border-0">
        <h2 class="accordion-header" id="usageHeading<?= $groupIndex ?>">
          <button class="accordion-button <?= $groupIndex === 1 ? '' : 'collapsed' ?> py-2" type="button" data-bs-toggle="collapse" data-bs-target="#usageCollapse<?= $groupIndex ?>" aria-expanded="<?= $groupIndex === 1 ? 'true' : 'false' ?>" aria-controls="usageCollapse<?= $groupIndex ?>">
            <span class="fw-semibold me-2"><?= h((string) $category) ?></span>
            <span class="badge rounded-pill text-bg-light border"><?= count($items) ?> setting<?= count($items) === 1 ? '' : 's' ?></span>
          </button>
        </h2>
        <div id="usageCollapse<?= $groupIndex ?>" class="accordion-collapse collapse <?= $groupIndex === 1 ? 'show' : '' ?>" aria-labelledby="usageHeading<?= $groupIndex ?>" data-bs-parent="#settingsUsageAccordion">
          <div class="accordion-body p-0">
            <div class="table-responsive">
              <table class="table table-admin table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Setting</th>
                    <th>Status</th>
                    <th>Current Value</th>
                    <th>Purpose</th>
                    <th>Used By</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                  <?php
                  $aliases = is_array($item['aliases'] ?? null) ? $item['aliases'] : [];
                  $usedBy = is_array($item['used_by'] ?? null) ? $item['used_by'] : [];
                  $matchedKey = trim((string) ($item['matched_key'] ?? ''));
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($item['key'] ?? '')) ?></div>
                      <?php if ($aliases !== []): ?>
                        <div class="small text-muted">Legacy aliases: <?= h(implode(', ', $aliases)) ?></div>
                      <?php endif; ?>
                      <?php if ($matchedKey !== '' && $matchedKey !== (string) ($item['key'] ?? '')): ?>
                        <div class="small text-warning">Currently resolved from: <?= h($matchedKey) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge <?= $statusBadge((string) ($item['status'] ?? 'missing')) ?>">
                        <?= h($statusLabel((string) ($item['status'] ?? 'missing'))) ?>
                      </span>
                    </td>
                    <td>
                      <div><?= h((string) ($item['value'] ?? '')) ?></div>
                      <?php if (!empty($item['type'])): ?>
                        <div class="small text-muted"><?= h((string) $item['type']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($item['description'] ?? '')) ?></td>
                    <td>
                      <?php if ($usedBy === []): ?>
                        <span class="text-muted small">No usage note recorded.</span>
                      <?php else: ?>
                        <?php foreach ($usedBy as $index => $usage): ?>
                          <div class="<?= $index > 0 ? 'mt-1' : '' ?> small"><?= h((string) $usage) ?></div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
