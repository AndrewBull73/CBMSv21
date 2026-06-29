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
$hasEntries = $entries !== [];
$hasLogFiles = $files !== [];
$contextSummary = $selectedFile !== '' ? $selectedFile : 'No log selected';
$appTimezone = date_default_timezone_get();
$appTime = date('Y-m-d H:i:s P');

$levelBadge = static function (string $level): string {
    return match (strtoupper($level)) {
        'ERROR' => 'text-bg-danger',
        'WARN' => 'text-bg-warning',
        'INFO' => 'text-bg-success',
        'DEBUG' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
};

$screenHeader = [
    'title' => 'Application Log',
    'icon' => 'bi-file-earmark-text',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($contextSummary) ?></strong>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Displayed</div>
              <div class="fs-4 fw-semibold"><?= h(number_format((int) ($summary['displayed'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Errors</div>
              <div class="fs-4 fw-semibold text-danger"><?= h(number_format((int) ($summary['errors'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Warnings</div>
              <div class="fs-4 fw-semibold text-warning"><?= h(number_format((int) ($summary['warns'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Log Files</div>
              <div class="fs-4 fw-semibold"><?= h(number_format(count($files))) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div id="application-log-runbook" class="alert alert-info border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">Application Log Runbook</div>
        <div class="mb-2">Use this screen to review recent application events, errors, warnings, and request details when investigating application behaviour.</div>
        <div class="small text-muted mb-2">Log timestamps are application-server time, not database time. Current application time is <?= h($appTime) ?> (<?= h($appTimezone) ?>).</div>
        <div class="small text-muted mb-2">Choose the log file and filters first, then open row details when context JSON or the raw line is needed.</div>
        <div class="small">Use Open Raw Log when you need the complete file outside the filtered table view.</div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Log Controls</h5>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2 align-items-end" id="applicationLogFilterForm">
            <input type="hidden" name="route" value="application-log/index">

            <div class="col-lg-4">
              <label class="form-label" for="applicationLogFileFilter">Log File</label>
              <select id="applicationLogFileFilter" class="form-select" name="file">
                <?php if (!$hasLogFiles): ?>
                  <option value="">No log files found</option>
                <?php else: ?>
                  <?php foreach ($files as $file): ?>
                    <?php
                      $fileName = trim((string) ($file['name'] ?? ''));
                      $fileSize = (int) ($file['size'] ?? 0);
                      $fileModified = (int) ($file['modified'] ?? 0);
                      $fileLabel = $fileName;
                      if ($fileModified > 0) {
                          $fileLabel .= ' - ' . date('Y-m-d H:i:s', $fileModified);
                      }
                      if ($fileSize > 0) {
                          $fileLabel .= ' - ' . number_format($fileSize) . ' bytes';
                      }
                    ?>
                    <option value="<?= h($fileName) ?>" <?= $selectedFile === $fileName ? 'selected' : '' ?>>
                      <?= h($fileLabel) ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label" for="applicationLogLevelFilter">Level</label>
              <select id="applicationLogLevelFilter" class="form-select" name="level">
                <option value="" <?= $selectedLevel === '' ? 'selected' : '' ?>>All</option>
                <?php foreach (['ERROR', 'WARN', 'INFO', 'DEBUG'] as $level): ?>
                  <option value="<?= h($level) ?>" <?= $selectedLevel === $level ? 'selected' : '' ?>><?= h($level) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label" for="applicationLogLinesFilter">Rows</label>
              <select id="applicationLogLinesFilter" class="form-select" name="lines">
                <?php foreach ([50, 100, 200, 300, 500] as $lineCount): ?>
                  <option value="<?= $lineCount ?>" <?= $selectedLines === $lineCount ? 'selected' : '' ?>><?= $lineCount ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-lg-4">
              <label class="form-label" for="applicationLogSearchFilter">Search</label>
              <input id="applicationLogSearchFilter" class="form-control" type="text" name="q" value="<?= h($selectedSearch) ?>" placeholder="controller, route, request id, exception">
            </div>

            <div class="col-12 d-flex flex-wrap gap-2">
              <button id="applicationLogApplyFilterBtn" type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Filter
              </button>
              <a class="btn btn-outline-secondary btn-sm" href="index.php?route=application-log/index">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
              </a>
              <?php if ($selectedFile !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="index.php?route=application-log/download&amp;file=<?= urlencode($selectedFile) ?>" target="_blank" rel="noopener">
                  <i class="bi bi-box-arrow-up-right me-1"></i>Open Raw Log
                </a>
              <?php endif; ?>
            </div>
          </form>

          <?php if ($selectedPath !== ''): ?>
            <div class="small text-muted mt-3">
              Source:
              <span class="text-break"><?= h($selectedPath) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Log Entries</h5>
        </div>
        <div class="card-body">
          <?php if (!$hasEntries): ?>
            <div class="text-center text-muted py-3">No matching log entries were found for the selected file and filters.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0" id="applicationLogTable">
                <thead class="table-light">
                  <tr>
                    <th>Level</th>
                    <th>Timestamp</th>
                    <th>Message</th>
                    <th>Context</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($entries as $index => $entry): ?>
                    <?php
                      $timestamp = trim((string) ($entry['timestamp'] ?? ''));
                      $level = strtoupper(trim((string) ($entry['level'] ?? '')));
                      $message = trim((string) ($entry['message'] ?? ''));
                      $raw = trim((string) ($entry['raw'] ?? ''));
                      $context = is_array($entry['context'] ?? null) ? $entry['context'] : null;
                      $collapseId = 'application-log-entry-' . $index;
                    ?>
                    <tr>
                      <td><span class="badge <?= h($levelBadge($level)) ?>"><?= h($level !== '' ? $level : 'LINE') ?></span></td>
                      <td class="text-nowrap"><?= h($timestamp !== '' ? $timestamp : 'Unknown time') ?></td>
                      <td class="text-break"><?= h($message !== '' ? $message : $raw) ?></td>
                      <td>
                        <span class="badge text-bg-<?= $context !== null ? 'secondary' : 'light' ?>">
                          <?= $context !== null ? 'Yes' : 'No' ?>
                        </span>
                      </td>
                      <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= h($collapseId) ?>" aria-expanded="false" aria-controls="<?= h($collapseId) ?>">
                          Details
                        </button>
                      </td>
                    </tr>
                    <tr class="collapse" id="<?= h($collapseId) ?>">
                      <td colspan="5">
                        <div class="border rounded p-3 bg-light">
                          <?php if ($context !== null): ?>
                            <div class="mb-3">
                              <div class="small text-muted mb-2">Context</div>
                              <pre class="bg-white border rounded p-3 small mb-0"><code><?= h((string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                            </div>
                          <?php endif; ?>
                          <div>
                            <div class="small text-muted mb-2">Raw Line</div>
                            <pre class="bg-dark text-light border rounded p-3 small mb-0 text-wrap"><code><?= h($raw) ?></code></pre>
                          </div>
                        </div>
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
  </div>
</div>
