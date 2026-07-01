<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

require_once __DIR__ . '/../../../shared/csrf.php';

$attempt = is_array($attempt ?? null) ? $attempt : null;
$questions = is_array($questions ?? null) ? $questions : [];
$tableInstalled = (bool) ($tableInstalled ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-award me-2"></i>Certification Test</h3>
        <?php if (is_array($attempt)): ?>
          <div class="small text-muted mt-1"><?= h((string) ($attempt['CertificationTitle'] ?? '')) ?> - <?= h((string) ($attempt['ModuleName'] ?? '')) ?></div>
        <?php endif; ?>
      </div>
      <a href="index.php?route=training-certifications/modules" class="btn btn-sm btn-outline-secondary">Exit</a>
    </div>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning">Certification tables are not installed.</div>
      <?php elseif (!is_array($attempt)): ?>
        <div class="alert alert-warning">Certification attempt was not found.</div>
      <?php else: ?>
        <div class="alert alert-info py-2">
          Pass mark: <?= h((string) ((float) ($attempt['PassPercent'] ?? 80))) ?>%. Answer every question before submitting.
        </div>
        <form method="post" action="index.php?route=training-certifications/submit">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="attempt_id" value="<?= h((string) ($attempt['TrainingCertificationAttemptID'] ?? 0)) ?>">
          <?php foreach ($questions as $questionIndex => $question): ?>
            <?php $questionId = (int) ($question['TrainingCertificationQuestionID'] ?? 0); ?>
            <fieldset class="border rounded p-3 mb-3">
              <legend class="float-none w-auto px-2 fs-6 mb-0">Question <?= h((string) ($questionIndex + 1)) ?></legend>
              <div class="fw-semibold mb-3"><?= h((string) ($question['QuestionText'] ?? '')) ?></div>
              <?php foreach (is_array($question['Options'] ?? null) ? $question['Options'] : [] as $option): ?>
                <?php $optionKey = (string) ($option['key'] ?? ''); ?>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="answers[<?= h((string) $questionId) ?>]" id="answer<?= h((string) $questionId) ?>_<?= h($optionKey) ?>" value="<?= h($optionKey) ?>" required>
                  <label class="form-check-label" for="answer<?= h((string) $questionId) ?>_<?= h($optionKey) ?>">
                    <span class="fw-semibold"><?= h($optionKey) ?>.</span> <?= h((string) ($option['label'] ?? '')) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </fieldset>
          <?php endforeach; ?>
          <button type="submit" class="btn btn-primary">Submit Certification</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
