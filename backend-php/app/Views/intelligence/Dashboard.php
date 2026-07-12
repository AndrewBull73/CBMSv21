<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$summary = is_array($summary ?? null) ? $summary : [];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-cpu me-2"></i>Intelligence Engine</h3>
        <div class="small text-muted mt-1">Analytics, forecasting, scenarios, ML, risk scoring, and AI platform governance.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/health"><i class="bi bi-heart-pulse me-1"></i>Health</a>
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/config"><i class="bi bi-sliders me-1"></i>Config</a>
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/runs"><i class="bi bi-clock-history me-1"></i>Runs</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> and <code>backend-php/config/sql/seed_intelligence_platform_rbac_permissions.sql</code>.</div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Engine Runs 7 Days</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['run_count_7d'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Forecasts</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['forecast_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Scenarios</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['scenario_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Python Analytics Runs 7 Days</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['analytics_run_count_7d'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Open Analytics Findings</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['analytics_finding_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">High/Critical Analytics Findings</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['critical_analytics_finding_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Open Insights</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['insight_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">High/Critical Insights</div><div class="fs-4 fw-semibold"><?= h((string) (int) ($summary['critical_insight_count'] ?? 0)) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Engine URL</div><div class="fw-semibold text-truncate"><?= h((string) $engineUrl) ?></div></div></div>
        </div>
        <div class="row g-3">
          <div class="col-lg-4"><a class="d-block border rounded p-3 bg-white text-decoration-none" href="index.php?route=intelligence/health"><div class="fw-semibold">Engine Health Check</div><div class="small text-muted">Verify REST connectivity to the Python service.</div></a></div>
          <div class="col-lg-4"><a class="d-block border rounded p-3 bg-white text-decoration-none" href="index.php?route=intelligence/ml-models"><div class="fw-semibold">ML Model Register</div><div class="small text-muted">Track models, approvals, training status, and predictions.</div></a></div>
          <div class="col-lg-4"><a class="d-block border rounded p-3 bg-white text-decoration-none" href="index.php?route=ai-dataset/index"><div class="fw-semibold">Dataset Analysis</div><div class="small text-muted">Run controlled analysis against approved datasets.</div></a></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
