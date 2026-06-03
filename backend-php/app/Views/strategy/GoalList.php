<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = h(csrf_token());
$records = is_array($records ?? null) ? $records : [];
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$activeCount = 0;
$pillarNames = [];
$goalTypes = [];

foreach ($records as $row) {
    if ((int) ($row['ActiveFlag'] ?? 0) === 1) {
        $activeCount++;
    }
    $pillarName = trim((string) ($row['StrategicPillarName'] ?? ''));
    if ($pillarName !== '') {
        $pillarNames[$pillarName] = true;
    }
    $goalType = trim((string) ($row['GoalTypeCode'] ?? ''));
    if ($goalType !== '') {
        $goalTypes[$goalType] = true;
    }
}

$screenHeader = [
    'title' => 'Goals',
    'icon' => 'bi-signpost-split',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Goals in register</div>
              <div class="fs-4 fw-semibold"><?= count($records) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active goals</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Strategic pillars linked</div>
              <div class="fs-4 fw-semibold"><?= count($pillarNames) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Goal types in use</div>
              <div class="fs-4 fw-semibold"><?= count($goalTypes) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this register to maintain the strategic goals that sit beneath each pillar. Check that goal types and pillar alignment are complete before expanding objective detail underneath them.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Search and Actions</h5>
          <a href="index.php?route=strategy-performance/goal-form" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create Goal
          </a>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="strategy-performance/goals">
            <div class="col-md-10">
              <input
                type="text"
                name="q"
                value="<?= h((string) ($q ?? '')) ?>"
                class="form-control"
                placeholder="Search goals"
              >
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Goal Register</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Code</th>
                  <th>Goal</th>
                  <th>Type</th>
                  <th>Strategic Pillar</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($records === []): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-3">No goals found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($records as $row): ?>
                  <?php $isActive = (int) ($row['ActiveFlag'] ?? 0) === 1; ?>
                  <tr>
                    <td class="fw-semibold"><?= h((string) ($row['GoalCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['GoalName'] ?? '')) ?></td>
                    <td><?= h((string) ($row['GoalTypeCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['StrategicPillarName'] ?? '')) ?></td>
                    <td>
                      <span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?>">
                        <?= $isActive ? 'Active' : 'Archived' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <a
                          href="index.php?route=strategy-performance/goal-form&id=<?= (int) ($row['GoalID'] ?? 0) ?>"
                          class="btn btn-outline-primary btn-sm"
                        >
                          Edit
                        </a>
                        <?php if ($isActive): ?>
                          <form method="post" action="index.php?route=strategy-performance/delete-goal" onsubmit="return confirm('Archive this goal?');">
                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= (int) ($row['GoalID'] ?? 0) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Archive</button>
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
  </div>
</div>
