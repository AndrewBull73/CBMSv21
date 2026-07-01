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
$createTableScript = (string) ($createTableScript ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-award me-2"></i>Certification Catalogue</h3>
        <div class="small text-muted mt-1">Maintain module certification tests and pass marks.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=training-certifications/modules" class="btn btn-sm btn-outline-secondary">Launch View</a>
        <a href="index.php?route=training-certifications/results" class="btn btn-sm btn-outline-secondary">Results</a>
        <?php if ($tableInstalled): ?>
          <a id="certification-catalogue-create-btn" href="index.php?route=training-certifications/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Certification</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning">Run <code><?= h($createTableScript) ?></code> to install certification tables.</div>
      <?php endif; ?>

      <form id="certification-catalogue-filter-form" method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="training-certifications/admin">
        <div class="col-md-4">
          <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control form-control-sm" placeholder="Search code, title, module, or description">
        </div>
        <div class="col-md-3">
          <select name="module" class="form-select form-select-sm">
            <option value="">All modules</option>
            <?php foreach ($moduleOptions as $moduleOption): ?>
              <option value="<?= h((string) $moduleOption) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleOption) ? 'selected' : '' ?>><?= h((string) $moduleOption) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="active" class="form-select form-select-sm">
            <option value="" <?= ((string) ($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All statuses</option>
            <option value="1" <?= ((string) ($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= ((string) ($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">Filter</button>
          <a href="index.php?route=training-certifications/admin" class="btn btn-sm btn-outline-secondary flex-fill">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table id="certification-catalogue-table" class="table table-striped table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th class="text-end">Order</th>
              <th>Certification</th>
              <th>Module</th>
              <th>Questions</th>
              <th>Pass mark</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="7" class="text-center text-muted py-3">No certifications found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php $code = (string) ($row['CertificationCode'] ?? ''); ?>
                <tr>
                  <td class="text-end"><span class="badge text-bg-light border"><?= h((string) ((int) ($row['SortOrder'] ?? 0))) ?></span></td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['CertificationTitle'] ?? $code)) ?></div>
                    <div class="small text-muted"><?= h($code) ?></div>
                  </td>
                  <td><?= h((string) ($row['ModuleName'] ?? '')) ?></td>
                  <td><?= h((string) ((int) ($row['QuestionCount'] ?? 0))) ?></td>
                  <td><?= h((string) ((float) ($row['PassPercent'] ?? 80))) ?>%</td>
                  <td><span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>"><?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></span></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <a href="index.php?route=training-certifications/form&certification_code=<?= urlencode($code) ?>" class="btn btn-sm btn-outline-secondary" title="Edit certification"><i class="bi bi-pencil-square"></i></a>
                      <a href="index.php?route=training-certifications/questions&certification_code=<?= urlencode($code) ?>" class="btn btn-sm btn-outline-primary" title="Questions"><i class="bi bi-list-ol"></i></a>
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
