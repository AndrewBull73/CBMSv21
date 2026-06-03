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
$records = is_array($records ?? null) ? $records : [];
$context = is_array($context ?? null) ? $context : [];
$periodLabels = is_array($periodLabels ?? null) ? $periodLabels : [];
$bpLabels = is_array($periodLabels['BP'] ?? null) ? $periodLabels['BP'] : [];
$id = (int) ($record['PhasingProfileID'] ?? 0);
$yearLabel = (string) ($context['YearLabel'] ?? '');
$versionLabel = (string) ($context['VersionLabel'] ?? '');
$activeCount = 0;
foreach ($records as $row) {
    if ((int) ($row['ActiveFlag'] ?? 0) === 1) {
        $activeCount++;
    }
}

$screenHeader = [
    'title' => 'Custom Phasing Profiles',
    'icon' => 'bi-sliders',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
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
              <div class="text-muted small">Profiles in register</div>
              <div class="fs-4 fw-semibold"><?= count($records) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active profiles</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Monthly weights</div>
              <div class="fs-4 fw-semibold">12</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Setup status</div>
              <div class="fs-4 fw-semibold"><?= empty($phasingProfileAvailable) ? 'Pending setup' : 'Ready' ?></div>
            </div>
          </div>
        </div>
      </div>

      <?php if (empty($phasingProfileAvailable)): ?>
        <div class="alert alert-warning">Run <code>create_tblSbPhasingProfile.sql</code> before maintaining custom phasing profiles.</div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Define named phasing profiles here, such as front-loaded, even quarterly, seasonal, or grant disbursement profiles. These profiles then appear on the resource envelope entry screen as phasing helper options.
      </div>

      <div class="row g-4">
        <div class="col-xl-7">
          <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <h5 class="mb-0">Profile Register</h5>
              <a href="index.php?route=strategy-config/phasing-profiles" class="btn btn-sm btn-primary">New Profile</a>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Code</th>
                      <th>Name</th>
                      <th class="text-end">Total Weight</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($records === []): ?>
                      <tr><td colspan="5" class="text-center text-muted py-3">No custom phasing profiles have been configured yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($records as $row): ?>
                        <?php $totalWeight = 0.0; for ($i = 1; $i <= 12; $i++) { $totalWeight += (float) ($row['BP' . $i . 'Weight'] ?? 0); } ?>
                        <tr<?= ((int) ($row['PhasingProfileID'] ?? 0) === $id && $id > 0) ? ' class="table-primary"' : '' ?>>
                          <td><?= h((string) ($row['ProfileCode'] ?? '')) ?></td>
                          <td>
                            <span class="fw-semibold"><?= h((string) ($row['ProfileName'] ?? '')) ?></span>
                            <?php if (!empty($row['ProfileDescription'])): ?>
                              <span class="small text-muted ms-2"><?= h((string) $row['ProfileDescription']) ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end"><?= number_format($totalWeight, 2) ?></td>
                          <td>
                            <span class="badge <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'bg-success' : 'bg-secondary' ?>">
                              <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                            </span>
                          </td>
                          <td class="text-end">
                            <div class="d-inline-flex gap-1">
                              <a href="index.php?route=strategy-config/phasing-profiles&id=<?= (int) ($row['PhasingProfileID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
                              <form method="post" action="index.php?route=strategy-config/delete-phasing-profile" onsubmit="return confirm('Archive this custom phasing profile?');">
                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) ($row['PhasingProfileID'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                              </form>
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

        <div class="col-xl-5">
          <div class="card shadow-sm">
            <div class="card-header">
              <h5 class="mb-0"><?= $id > 0 ? 'Edit Custom Phasing Profile' : 'Add Custom Phasing Profile' ?></h5>
            </div>
            <div class="card-body">
              <form method="post" action="index.php?route=strategy-config/save-phasing-profile">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="PhasingProfileID" value="<?= $id ?>">

                <div class="row g-3 mb-3">
                  <div class="col-md-4">
                    <label class="form-label">Code</label>
                    <input type="text" name="ProfileCode" class="form-control" required value="<?= h((string) ($record['ProfileCode'] ?? '')) ?>">
                  </div>
                  <div class="col-md-8">
                    <label class="form-label">Name</label>
                    <input type="text" name="ProfileName" class="form-control" required value="<?= h((string) ($record['ProfileName'] ?? '')) ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Description</label>
                    <input type="text" name="ProfileDescription" class="form-control" value="<?= h((string) ($record['ProfileDescription'] ?? '')) ?>">
                  </div>
                </div>

                <div class="table-responsive mb-3">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Period</th>
                        <th class="text-end">Relative Weight</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php for ($i = 1; $i <= 12; $i++): ?>
                        <tr>
                          <td><?= h((string) ($bpLabels[$i] ?? ('BP' . $i))) ?></td>
                          <td>
                            <input type="number" step="0.01" min="0" name="BP<?= $i ?>Weight" class="form-control text-end" value="<?= h((string) ($record['BP' . $i . 'Weight'] ?? '1')) ?>">
                          </td>
                        </tr>
                      <?php endfor; ?>
                    </tbody>
                  </table>
                </div>

                <div class="alert alert-light border">
                  Use any positive relative weights. The resource envelope phasing helper will normalize them automatically to the current-year amount.
                </div>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="ActiveFlag" id="phasingProfileActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="phasingProfileActiveFlag">Active</label>
                </div>

                <div class="d-flex justify-content-end">
                  <button type="submit" class="btn btn-primary">Save Custom Phasing Profile</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
