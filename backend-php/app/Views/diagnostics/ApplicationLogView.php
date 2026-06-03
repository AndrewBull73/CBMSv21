<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$files = is_array($files ?? null) ? $files : [];
$selectedFile = trim((string) ($selectedFile ?? ''));
$selectedPath = trim((string) ($selectedPath ?? ''));
$filters = is_array($filters ?? null) ? $filters : [];
$entries = is_array($entries ?? null) ? $entries : [];
$summary = is_array($summary ?? null) ? $summary : [];
$selectedLevel = strtoupper(trim((string) ($filters['level'] ?? '')));
$selectedSearch = trim((string) ($filters['q'] ?? ''));
$selectedLines = (int) ($filters['lines'] ?? 200);

$levelBadge = static function (string $level): string {
    return match (strtoupper($level)) {
        'ERROR' => 'text-bg-danger',
        'WARN' => 'text-bg-warning',
        'INFO' => 'text-bg-success',
        'DEBUG' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
};
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h3 class="mb-1"><i class="bi bi-file-earmark-text me-2"></i>Application Log</h3>
        <div class="small text-muted">Inspect recent `app-YYYY-MM-DD.log` entries without leaving the application.</div>
      </div>
      <?php if ($selectedFile !== ''): ?>
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=application-log/download&amp;file=<?= urlencode($selectedFile) ?>" target="_blank" rel="noopener">
          <i class="bi bi-box-arrow-up-right me-1"></i>Open Raw Log
        </a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end mb-4">
        <input type="hidden" name="route" value="application-log/index">

        <div class="col-lg-4">
          <label class="form-label">Log File</label>
          <select class="form-select" name="file">
            <?php if ($files === []): ?>
              <option value="">No log files found</option>
            <?php else: ?>
              <?php foreach ($files as $file): ?>
                <?php
                  $fileName = trim((string) ($file['name'] ?? ''));
                  $fileSize = (int) ($file['size'] ?? 0);
                  $fileModified = (int) ($file['modified'] ?? 0);
                ?>
                <option value="<?= h($fileName) ?>" <?= $selectedFile === $fileName ? 'selected' : '' ?>>
                  <?= h($fileName) ?><?= $fileModified > 0 ? ' - ' . h(date('Y-m-d H:i:s', $fileModified)) : '' ?><?= $fileSize > 0 ? ' - ' . h(number_format($fileSize)) . ' bytes' : '' ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Level</label>
          <select class="form-select" name="level">
            <option value="" <?= $selectedLevel === '' ? 'selected' : '' ?>>All</option>
            <option value="ERROR" <?= $selectedLevel === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
            <option value="WARN" <?= $selectedLevel === 'WARN' ? 'selected' : '' ?>>WARN</option>
            <option value="INFO" <?= $selectedLevel === 'INFO' ? 'selected' : '' ?>>INFO</option>
            <option value="DEBUG" <?= $selectedLevel === 'DEBUG' ? 'selected' : '' ?>>DEBUG</option>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Lines</label>
          <select class="form-select" name="lines">
            <?php foreach ([50, 100, 200, 300, 500] as $lineCount): ?>
              <option value="<?= $lineCount ?>" <?= $selectedLines === $lineCount ? 'selected' : '' ?>><?= $lineCount ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-4">
          <label class="form-label">Search</label>
          <input class="form-control" type="text" name="q" value="<?= h($selectedSearch) ?>" placeholder="controller, route, RequestID, exception text">
        </div>

        <div class="col-12 d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-funnel me-1"></i>Apply
          </button>
          <a class="btn btn-outline-secondary btn-sm" href="index.php?route=application-log/index">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
          </a>
        </div>
      </form>

      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Displayed</div>
            <div class="fs-4 fw-semibold"><?= h((string) ($summary['displayed'] ?? 0)) ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Errors</div>
            <div class="fs-4 fw-semibold text-danger"><?= h((string) ($summary['errors'] ?? 0)) ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">Warnings</div>
            <div class="fs-4 fw-semibold text-warning"><?= h((string) ($summary['warns'] ?? 0)) ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted">File</div>
            <div class="small fw-semibold text-break"><?= h($selectedFile !== '' ? $selectedFile : 'None selected') ?></div>
          </div>
        </div>
      </div>

      <?php if ($selectedPath !== ''): ?>
        <div class="small text-muted mb-3">Source: <?= h($selectedPath) ?></div>
      <?php endif; ?>

      <?php if ($entries === []): ?>
        <div class="alert alert-info mb-0">
          No matching log entries were found for the selected file and filters.
        </div>
      <?php else: ?>
        <div class="accordion" id="applicationLogAccordion">
          <?php foreach ($entries as $index => $entry): ?>
            <?php
              $timestamp = trim((string) ($entry['timestamp'] ?? ''));
              $level = strtoupper(trim((string) ($entry['level'] ?? '')));
              $message = trim((string) ($entry['message'] ?? ''));
              $raw = trim((string) ($entry['raw'] ?? ''));
              $context = is_array($entry['context'] ?? null) ? $entry['context'] : null;
              $collapseId = 'application-log-entry-' . $index;
            ?>
            <div class="accordion-item">
              <h2 class="accordion-header" id="<?= h($collapseId) ?>-header">
                <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= h($collapseId) ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="<?= h($collapseId) ?>">
                  <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 text-start w-100 pe-3">
                    <span class="badge <?= h($levelBadge($level)) ?>"><?= h($level !== '' ? $level : 'LINE') ?></span>
                    <span class="small text-muted"><?= h($timestamp !== '' ? $timestamp : 'Unknown time') ?></span>
                    <span class="text-break"><?= h($message !== '' ? $message : $raw) ?></span>
                  </div>
                </button>
              </h2>
              <div id="<?= h($collapseId) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" aria-labelledby="<?= h($collapseId) ?>-header" data-bs-parent="#applicationLogAccordion">
                <div class="accordion-body">
                  <?php if ($context !== null): ?>
                    <div class="mb-3">
                      <div class="small text-muted mb-2">Context</div>
                      <pre class="bg-light border rounded p-3 small mb-0"><code><?= h((string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                    </div>
                  <?php endif; ?>

                  <div>
                    <div class="small text-muted mb-2">Raw Line</div>
                    <pre class="bg-dark text-light border rounded p-3 small mb-0 text-wrap"><code><?= h($raw) ?></code></pre>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
