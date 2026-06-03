<?php
declare(strict_types=1);

/** @var array $filters */
/** @var array $rows */
/** @var array $summary */
/** @var array $options */
/** @var int $currentPage */
/** @var int $pageSize */
/** @var int $totalCount */
/** @var int $totalPages */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatPublishedValue')) {
    function formatPublishedValue(array $row): string
    {
        if ($row['ValueDecimal'] !== null) {
            return number_format((float) $row['ValueDecimal'], 2);
        }

        if ($row['ValueText'] !== null && $row['ValueText'] !== '') {
            return (string) $row['ValueText'];
        }

        if ($row['ValueBit'] !== null) {
            return ((int) $row['ValueBit'] === 1) ? 'True' : 'False';
        }

        return '';
    }
}

$buildUrl = static function (array $overrides = []) use ($filters, $pageSize): string {
    $params = array_merge([
        'route' => 'scenario-results/index',
        'model' => $filters['model'] ?? '',
        'scenario' => $filters['scenario'] ?? '',
        'cost_object' => $filters['cost_object'] ?? '',
        'period' => $filters['period'] ?? '',
        'node' => $filters['node'] ?? '',
        'q' => $filters['q'] ?? '',
        'pageSize' => $pageSize,
    ], $overrides);

    return 'index.php?' . http_build_query($params);
};
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><i class="bi bi-diagram-3 me-2"></i>Scenario Results</strong>
      <div class="small text-muted">Published scenario outputs from the new calculation engine.</div>
    </div>
    <a href="index.php?route=scenario-results/api&amp;<?= h(http_build_query([
        'model' => $filters['model'] ?? '',
        'scenario' => $filters['scenario'] ?? '',
        'cost_object' => $filters['cost_object'] ?? '',
        'period' => $filters['period'] ?? '',
        'node' => $filters['node'] ?? '',
        'q' => $filters['q'] ?? '',
        'page' => $currentPage,
        'pageSize' => $pageSize,
    ])) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-braces me-1"></i>JSON
    </a>
  </div>

  <div class="card-body">
    <form method="get" action="index.php" class="row g-2 mb-4">
      <input type="hidden" name="route" value="scenario-results/index">

      <div class="col-md-2">
        <label class="form-label">Model</label>
        <select name="model" class="form-select">
          <option value="">All models</option>
          <?php foreach (($options['models'] ?? []) as $option): ?>
            <option value="<?= h((string) $option['ValueCode']) ?>" <?= (($filters['model'] ?? '') === (string) $option['ValueCode']) ? 'selected' : '' ?>>
              <?= h((string) $option['ValueCode']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Scenario</label>
        <select name="scenario" class="form-select">
          <option value="">All scenarios</option>
          <?php foreach (($options['scenarios'] ?? []) as $option): ?>
            <option value="<?= h((string) $option['ValueCode']) ?>" <?= (($filters['scenario'] ?? '') === (string) $option['ValueCode']) ? 'selected' : '' ?>>
              <?= h((string) $option['ValueCode']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Cost object</label>
        <select name="cost_object" class="form-select">
          <option value="">All cost objects</option>
          <?php foreach (($options['costObjects'] ?? []) as $option): ?>
            <option value="<?= h((string) $option['ValueCode']) ?>" <?= (($filters['cost_object'] ?? '') === (string) $option['ValueCode']) ? 'selected' : '' ?>>
              <?= h((string) $option['ValueCode']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Period</label>
        <select name="period" class="form-select">
          <option value="">All periods</option>
          <?php foreach (($options['periods'] ?? []) as $option): ?>
            <option value="<?= h((string) $option['ValueCode']) ?>" <?= (($filters['period'] ?? '') === (string) $option['ValueCode']) ? 'selected' : '' ?>>
              <?= h((string) $option['ValueCode']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Node</label>
        <select name="node" class="form-select">
          <option value="">All nodes</option>
          <?php foreach (($options['nodes'] ?? []) as $option): ?>
            <option value="<?= h((string) $option['ValueCode']) ?>" <?= (($filters['node'] ?? '') === (string) $option['ValueCode']) ? 'selected' : '' ?>>
              <?= h((string) $option['ValueCode']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Page size</label>
        <select name="pageSize" class="form-select">
          <?php foreach ([25, 50, 100, 250] as $size): ?>
            <option value="<?= $size ?>" <?= $pageSize === $size ? 'selected' : '' ?>><?= $size ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-8">
        <label class="form-label">Search</label>
        <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control" placeholder="Model, scenario, cost object, period, or node">
      </div>

      <div class="col-md-4 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-search me-1"></i>Filter
        </button>
        <a href="index.php?route=scenario-results/index" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
        </a>
      </div>
    </form>

    <div class="row g-3 mb-4">
      <div class="col-md-2">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Rows</div>
          <div class="fs-5 fw-semibold"><?= number_format((int) ($summary['ResultRowCount'] ?? 0)) ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Models</div>
          <div class="fs-5 fw-semibold"><?= number_format((int) ($summary['ModelCount'] ?? 0)) ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Scenarios</div>
          <div class="fs-5 fw-semibold"><?= number_format((int) ($summary['ScenarioCount'] ?? 0)) ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Cost objects</div>
          <div class="fs-5 fw-semibold"><?= number_format((int) ($summary['CostObjectCount'] ?? 0)) ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Periods</div>
          <div class="fs-5 fw-semibold"><?= number_format((int) ($summary['PeriodCount'] ?? 0)) ?></div>
        </div>
      </div>
      <div class="col-md-2">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Latest publish</div>
          <div class="small fw-semibold">
            <?= !empty($summary['LatestPublishedDate']) ? h((string) $summary['LatestPublishedDate']) : 'N/A' ?>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="small text-muted">
        Showing <?= number_format(count($rows)) ?> of <?= number_format($totalCount) ?> published result rows.
      </div>
      <div class="small text-muted">
        Page <?= number_format($currentPage) ?> of <?= number_format($totalPages) ?>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Model</th>
            <th>Scenario</th>
            <th>Period</th>
            <th>Cost object</th>
            <th>Node</th>
            <th>Type</th>
            <th class="text-end">Value</th>
            <th>Published</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-4">No published scenario results matched the current filters.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h((string) $row['ModelCode']) ?></div>
                <div class="small text-muted"><?= h((string) $row['ModelName']) ?></div>
              </td>
              <td>
                <div class="fw-semibold"><?= h((string) $row['ScenarioCode']) ?></div>
                <div class="small text-muted"><?= h((string) $row['ScenarioName']) ?></div>
              </td>
              <td><?= h((string) $row['PeriodCode']) ?></td>
              <td>
                <div class="fw-semibold"><?= h((string) $row['CostObjectCode']) ?></div>
                <div class="small text-muted"><?= h((string) $row['CostObjectName']) ?></div>
              </td>
              <td>
                <div class="fw-semibold"><?= h((string) $row['NodeCode']) ?></div>
                <div class="small text-muted"><?= h((string) $row['NodeName']) ?></div>
              </td>
              <td>
                <span class="badge text-bg-light"><?= h((string) $row['NodeTypeCode']) ?></span>
              </td>
              <td class="text-end fw-semibold"><?= h(formatPublishedValue($row)) ?></td>
              <td>
                <div class="small"><?= h((string) $row['PublishedDate']) ?></div>
                <div class="text-muted small">Run <?= h((string) $row['SourceCalcRunID']) ?></div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="mt-3" aria-label="Scenario results pagination">
        <ul class="pagination justify-content-center">
          <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h($buildUrl(['page' => max(1, $currentPage - 1)])) ?>">Prev</a>
          </li>
          <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="<?= h($buildUrl(['page' => $i])) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h($buildUrl(['page' => min($totalPages, $currentPage + 1)])) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>
