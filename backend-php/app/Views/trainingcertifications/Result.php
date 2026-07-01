<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$attempt = is_array($attempt ?? null) ? $attempt : null;
$answers = is_array($answers ?? null) ? $answers : [];
$tableInstalled = (bool) ($tableInstalled ?? false);
$passed = is_array($attempt) && (int) ($attempt['PassedFlag'] ?? 0) === 1;
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-award me-2"></i>Certification Result</h3>
        <?php if (is_array($attempt)): ?>
          <div class="small text-muted mt-1"><?= h((string) ($attempt['CertificationTitle'] ?? '')) ?></div>
        <?php endif; ?>
      </div>
      <a href="index.php?route=training-certifications/modules" class="btn btn-sm btn-outline-secondary">Certifications</a>
    </div>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning">Certification tables are not installed.</div>
      <?php elseif (!is_array($attempt)): ?>
        <div class="alert alert-warning">Certification result was not found.</div>
      <?php else: ?>
        <div class="alert <?= $passed ? 'alert-success' : 'alert-danger' ?>">
          <div class="fw-semibold"><?= $passed ? 'Certification passed.' : 'Certification not passed.' ?></div>
          <div>
            Score <?= h((string) ($attempt['ScorePercent'] ?? '0')) ?>%;
            required <?= h((string) ($attempt['PassPercent'] ?? '80')) ?>%.
            <?= h((string) ($attempt['CorrectCount'] ?? '0')) ?> of <?= h((string) ($attempt['QuestionCount'] ?? '0')) ?> correct.
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th class="text-end">No</th>
                <th>Question</th>
                <th>Your answer</th>
                <th>Correct answer</th>
                <th>Result</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($answers as $answer): ?>
                <?php
                $selected = (string) ($answer['SelectedOptionKey'] ?? '');
                $correct = (string) ($answer['CorrectOptionKey'] ?? '');
                $optionLabels = [];
                foreach (is_array($answer['Options'] ?? null) ? $answer['Options'] : [] as $option) {
                    $optionLabels[(string) ($option['key'] ?? '')] = (string) ($option['label'] ?? '');
                }
                ?>
                <tr>
                  <td class="text-end"><?= h((string) ((int) ($answer['QuestionNo'] ?? 0))) ?></td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($answer['QuestionText'] ?? '')) ?></div>
                    <?php if (trim((string) ($answer['Explanation'] ?? '')) !== ''): ?>
                      <div class="small text-muted"><?= h((string) ($answer['Explanation'] ?? '')) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><span class="fw-semibold"><?= h($selected) ?></span> <?= h((string) ($optionLabels[$selected] ?? '')) ?></td>
                  <td><span class="fw-semibold"><?= h($correct) ?></span> <?= h((string) ($optionLabels[$correct] ?? '')) ?></td>
                  <td>
                    <span class="badge text-bg-<?= ((int) ($answer['CorrectFlag'] ?? 0) === 1) ? 'success' : 'danger' ?>">
                      <?= ((int) ($answer['CorrectFlag'] ?? 0) === 1) ? 'Correct' : 'Incorrect' ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
