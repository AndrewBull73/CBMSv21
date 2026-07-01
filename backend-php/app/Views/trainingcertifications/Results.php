<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => '', 'certification_code' => '', 'result' => ''];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$certificationOptions = is_array($certificationOptions ?? null) ? $certificationOptions : [];
$tableInstalled = (bool) ($tableInstalled ?? false);
$createTableScript = (string) ($createTableScript ?? '');
$formatTimestamp = static function ($value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    try {
        $utc = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        return $utc->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return $raw;
    }
};
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-table me-2"></i>Certification Results</h3>
        <div class="small text-muted mt-1">Review submitted certification attempts across users and modules.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=training-certifications/modules" class="btn btn-sm btn-outline-secondary">Certifications</a>
        <a href="index.php?route=training-certifications/admin" class="btn btn-sm btn-outline-secondary">Catalogue</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning">Run <code><?= h($createTableScript) ?></code> to install certification tables.</div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="training-certifications/results">
        <div class="col-md-3">
          <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control form-control-sm" placeholder="Search user or certification">
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
          <select name="certification_code" class="form-select form-select-sm">
            <option value="">All certifications</option>
            <?php foreach ($certificationOptions as $option): ?>
              <?php $code = (string) ($option['CertificationCode'] ?? ''); ?>
              <option value="<?= h($code) ?>" <?= ((string) ($filters['certification_code'] ?? '') === $code) ? 'selected' : '' ?>>
                <?= h((string) ($option['CertificationTitle'] ?? $code)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="result" class="form-select form-select-sm">
            <option value="">All results</option>
            <option value="passed" <?= ((string) ($filters['result'] ?? '') === 'passed') ? 'selected' : '' ?>>Passed</option>
            <option value="failed" <?= ((string) ($filters['result'] ?? '') === 'failed') ? 'selected' : '' ?>>Failed</option>
          </select>
        </div>
        <div class="col-md-1 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">Filter</button>
        </div>
        <div class="col-md-12">
          <a href="index.php?route=training-certifications/results" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th>Certification</th>
              <th>Module</th>
              <th>Score</th>
              <th>Pass mark</th>
              <th>Attempt</th>
              <th>Submitted</th>
              <th>Result</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="8" class="text-center text-muted py-3">No certification results found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                $displayName = trim((string) ($row['DisplayName'] ?? ''));
                $username = trim((string) ($row['Username'] ?? ''));
                $userLabel = $displayName !== '' ? $displayName : $username;
                $passed = (int) ($row['PassedFlag'] ?? 0) === 1;
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h($userLabel) ?></div>
                    <div class="small text-muted"><?= h($username) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['Email'] ?? '')) ?></div>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['CertificationTitle'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['CertificationCode'] ?? '')) ?></div>
                  </td>
                  <td><?= h((string) ($row['ModuleName'] ?? '')) ?></td>
                  <td><?= h((string) ($row['ScorePercent'] ?? '0')) ?>% (<?= h((string) ($row['CorrectCount'] ?? '0')) ?>/<?= h((string) ($row['QuestionCount'] ?? '0')) ?>)</td>
                  <td><?= h((string) ($row['PassPercent'] ?? '80')) ?>%</td>
                  <td><?= h((string) ($row['AttemptNo'] ?? '1')) ?></td>
                  <td><?= h($formatTimestamp($row['SubmittedAt'] ?? '')) ?></td>
                  <td><span class="badge text-bg-<?= $passed ? 'success' : 'danger' ?>"><?= $passed ? 'Passed' : 'Failed' ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
