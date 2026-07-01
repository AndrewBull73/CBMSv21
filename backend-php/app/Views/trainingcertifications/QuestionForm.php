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
$certificationOptions = is_array($certificationOptions ?? null) ? $certificationOptions : [];
$certificationCode = (string) ($certificationCode ?? ($record['CertificationCode'] ?? ''));
$tableInstalled = (bool) ($tableInstalled ?? false);
$optionsText = '';
foreach (is_array($record['Options'] ?? null) ? $record['Options'] : [] as $option) {
    $optionsText .= (string) ($option['key'] ?? '') . '=' . (string) ($option['label'] ?? '') . PHP_EOL;
}
if ($optionsText === '') {
    $optionsText = "A=\nB=\nC=\nD=";
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="mb-0"><i class="bi bi-list-check me-2"></i><?= $record ? 'Edit Question' : 'Create Question' ?></h3>
      <a href="index.php?route=training-certifications/questions&certification_code=<?= urlencode($certificationCode) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning">Certification tables are not installed.</div>
      <?php else: ?>
        <form method="post" action="index.php?route=training-certifications/save-question" class="row g-3">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="OldQuestionNo" value="<?= h((string) ($record['QuestionNo'] ?? '0')) ?>">
          <div class="col-md-8">
            <label class="form-label">Certification</label>
            <select id="TrainingCertificationQuestionCertificationCode" name="CertificationCode" class="form-select" required>
              <?php foreach ($certificationOptions as $option): ?>
                <?php $optionCode = (string) ($option['CertificationCode'] ?? ''); ?>
                <option value="<?= h($optionCode) ?>" <?= $optionCode === $certificationCode ? 'selected' : '' ?>>
                  <?= h((string) ($option['CertificationTitle'] ?? $optionCode)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Question no</label>
            <input id="TrainingCertificationQuestionNo" type="number" min="1" name="QuestionNo" value="<?= h((string) ($record['QuestionNo'] ?? '1')) ?>" class="form-control" required>
          </div>
          <div class="col-md-2">
            <label class="form-label">Sort order</label>
            <input id="TrainingCertificationQuestionSortOrder" type="number" min="0" name="SortOrder" value="<?= h((string) ($record['SortOrder'] ?? ($record['QuestionNo'] ?? '1'))) ?>" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label">Question text</label>
            <textarea id="TrainingCertificationQuestionText" name="QuestionText" rows="3" class="form-control" required><?= h((string) ($record['QuestionText'] ?? '')) ?></textarea>
          </div>
          <div class="col-md-8">
            <label class="form-label">Options</label>
            <textarea id="TrainingCertificationQuestionOptions" name="Options" rows="6" class="form-control" required><?= h(rtrim($optionsText)) ?></textarea>
            <div class="form-text">Enter one option per line as key=value, for example A=First answer.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Correct option key</label>
            <input id="TrainingCertificationCorrectOptionKey" type="text" name="CorrectOptionKey" value="<?= h((string) ($record['CorrectOptionKey'] ?? '')) ?>" class="form-control" required>
            <label class="form-label mt-3">Explanation</label>
            <textarea id="TrainingCertificationQuestionExplanation" name="Explanation" rows="3" class="form-control"><?= h((string) ($record['Explanation'] ?? '')) ?></textarea>
            <div class="form-check mt-3">
              <input type="checkbox" class="form-check-input" id="ActiveFlag" name="ActiveFlag" value="1" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label for="ActiveFlag" class="form-check-label">Active</label>
            </div>
          </div>
          <div class="col-12">
            <button id="training-certification-question-save-btn" type="submit" class="btn btn-primary">Save Question</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
