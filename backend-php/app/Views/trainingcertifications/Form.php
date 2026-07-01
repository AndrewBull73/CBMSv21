<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../../../shared/csrf.php';

$record = is_array($record ?? null) ? $record : [];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$tableInstalled = (bool) ($tableInstalled ?? false);
$code = (string) ($record['CertificationCode'] ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="mb-0"><i class="bi bi-award me-2"></i><?= $code !== '' ? 'Edit Certification' : 'Create Certification' ?></h3>
      <a href="index.php?route=training-certifications/admin" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning">Certification tables are not installed.</div>
      <?php else: ?>
        <form method="post" action="index.php?route=training-certifications/save" class="row g-3">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <div class="col-md-4">
            <label class="form-label">Certification code</label>
            <input id="TrainingCertificationCode" type="text" name="CertificationCode" value="<?= h($code) ?>" class="form-control" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Title</label>
            <input id="TrainingCertificationTitle" type="text" name="CertificationTitle" value="<?= h((string) ($record['CertificationTitle'] ?? '')) ?>" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Module</label>
            <input id="TrainingCertificationModuleName" list="certificationModuleOptions" type="text" name="ModuleName" value="<?= h((string) ($record['ModuleName'] ?? '')) ?>" class="form-control" required>
            <datalist id="certificationModuleOptions">
              <?php foreach ($moduleOptions as $moduleOption): ?>
                <option value="<?= h((string) $moduleOption) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-md-3">
            <label class="form-label">Pass percent</label>
            <input id="TrainingCertificationPassPercent" type="number" min="0" max="100" step="0.01" name="PassPercent" value="<?= h((string) ($record['PassPercent'] ?? '80')) ?>" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Sort order</label>
            <input id="TrainingCertificationSortOrder" type="number" min="0" name="SortOrder" value="<?= h((string) ($record['SortOrder'] ?? '0')) ?>" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea id="TrainingCertificationDescription" name="Description" rows="4" class="form-control"><?= h((string) ($record['Description'] ?? '')) ?></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="ActiveFlag" name="ActiveFlag" value="1" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label for="ActiveFlag" class="form-check-label">Active</label>
            </div>
          </div>
          <div class="col-12 d-flex gap-2">
            <button id="training-certification-save-btn" type="submit" class="btn btn-primary">Save</button>
            <?php if ($code !== ''): ?>
              <a href="index.php?route=training-certifications/questions&certification_code=<?= urlencode($code) ?>" class="btn btn-outline-primary">Questions</a>
            <?php endif; ?>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
