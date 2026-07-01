<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../../../shared/csrf.php';

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => ''];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$tableInstalled = (bool) ($tableInstalled ?? false);
$canManageTraining = (bool) ($canManageTraining ?? false);
$createTableScript = (string) ($createTableScript ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-award me-2"></i>Training Certifications</h3>
        <div class="small text-muted mt-1">Complete module certification tests and review your latest outcome.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=training/dashboard" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="index.php?route=training/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-check me-1"></i>Training</a>
        <?php if ($canManageTraining): ?>
          <a href="index.php?route=training-certifications/results" class="btn btn-sm btn-outline-secondary"><i class="bi bi-table me-1"></i>Results</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning">
          Run <code><?= h($createTableScript) ?></code> to install certification tables.
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="training-certifications/modules">
        <div class="col-md-5">
          <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control form-control-sm" placeholder="Search certification or module">
        </div>
        <div class="col-md-4">
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
          <a href="index.php?route=training-certifications/modules" class="btn btn-sm btn-outline-secondary flex-fill">Reset</a>
        </div>
      </form>

      <div class="row g-3">
        <?php if ($rows === []): ?>
          <div class="col-12 text-center text-muted py-4">No certifications found.</div>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
            $code = (string) ($row['CertificationCode'] ?? '');
            $questionCount = (int) ($row['QuestionCount'] ?? 0);
            $latestStatus = strtolower((string) ($row['LatestStatus'] ?? ''));
            $latestPassed = (int) ($row['LatestPassedFlag'] ?? 0) === 1;
            $latestScore = $row['LatestScorePercent'] ?? null;
            ?>
            <div class="col-lg-6">
              <div class="card h-100 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-light border"><?= h((string) ($row['ModuleName'] ?? '')) ?></span>
                    <span class="badge <?= $latestPassed ? 'text-bg-success' : ($latestStatus === 'submitted' ? 'text-bg-danger' : 'text-bg-secondary') ?>">
                      <?= $latestStatus === 'submitted' ? ($latestPassed ? 'Certified' : 'Not certified') : 'Not attempted' ?>
                    </span>
                  </div>
                  <div class="small text-muted"><?= h((string) ((float) ($row['PassPercent'] ?? 80))) ?>% pass</div>
                </div>
                <div class="card-body">
                  <div class="fw-semibold mb-1"><?= h((string) ($row['CertificationTitle'] ?? $code)) ?></div>
                  <div class="small text-muted mb-3"><?= h((string) ($row['Description'] ?? '')) ?></div>
                  <div class="row g-2 small mb-3">
                    <div class="col-4">
                      <div class="text-muted">Questions</div>
                      <div class="fw-semibold"><?= h((string) $questionCount) ?></div>
                    </div>
                    <div class="col-4">
                      <div class="text-muted">Latest score</div>
                      <div class="fw-semibold"><?= $latestScore !== null ? h((string) $latestScore) . '%' : '-' ?></div>
                    </div>
                    <div class="col-4">
                      <div class="text-muted">Attempt</div>
                      <div class="fw-semibold"><?= h((string) ((int) ($row['LatestAttemptNo'] ?? 0))) ?></div>
                    </div>
                  </div>
                  <form method="post" action="index.php?route=training-certifications/start">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="certification_code" value="<?= h($code) ?>">
                    <button type="submit" class="btn btn-sm btn-primary" <?= $questionCount <= 0 ? 'disabled' : '' ?>>
                      <i class="bi bi-play-circle me-1"></i>Start certification
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
