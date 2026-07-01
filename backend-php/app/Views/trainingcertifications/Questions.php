<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$certification = is_array($certification ?? null) ? $certification : null;
$certificationOptions = is_array($certificationOptions ?? null) ? $certificationOptions : [];
$certificationCode = (string) ($certificationCode ?? '');
$tableInstalled = (bool) ($tableInstalled ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-list-ol me-2"></i>Certification Questions</h3>
        <?php if (is_array($certification)): ?>
          <div class="small text-muted mt-1"><?= h((string) ($certification['CertificationTitle'] ?? $certificationCode)) ?></div>
        <?php endif; ?>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=training-certifications/admin" class="btn btn-sm btn-outline-secondary">Catalogue</a>
        <?php if ($tableInstalled && $certificationCode !== ''): ?>
          <a href="index.php?route=training-certifications/question-form&certification_code=<?= urlencode($certificationCode) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Question</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning">Certification tables are not installed.</div>
      <?php else: ?>
        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="training-certifications/questions">
          <div class="col-md-8">
            <select name="certification_code" class="form-select form-select-sm">
              <?php foreach ($certificationOptions as $option): ?>
                <?php $optionCode = (string) ($option['CertificationCode'] ?? ''); ?>
                <option value="<?= h($optionCode) ?>" <?= $optionCode === $certificationCode ? 'selected' : '' ?>>
                  <?= h((string) ($option['CertificationTitle'] ?? $optionCode)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Open</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th class="text-end">No</th>
                <th>Question</th>
                <th>Options</th>
                <th>Correct</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No questions found.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td class="text-end"><?= h((string) ((int) ($row['QuestionNo'] ?? 0))) ?></td>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['QuestionText'] ?? '')) ?></div>
                      <?php if (trim((string) ($row['Explanation'] ?? '')) !== ''): ?>
                        <div class="small text-muted"><?= h((string) ($row['Explanation'] ?? '')) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) count(is_array($row['Options'] ?? null) ? $row['Options'] : [])) ?></td>
                    <td><span class="badge text-bg-light border"><?= h((string) ($row['CorrectOptionKey'] ?? '')) ?></span></td>
                    <td><span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>"><?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-end">
                      <a href="index.php?route=training-certifications/question-form&certification_code=<?= urlencode((string) ($row['CertificationCode'] ?? '')) ?>&question_no=<?= (int) ($row['QuestionNo'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
