<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$filters = is_array($filters ?? null) ? $filters : [];
$hasRows = $rows !== [];
$selectedStatus = strtolower(trim((string) ($filters['status'] ?? '')));
$selectedSearch = trim((string) ($filters['q'] ?? ''));
$selectedLimit = (int) ($filters['limit'] ?? 100);

$statusBadge = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'sent' => 'text-bg-success',
        'failed' => 'text-bg-danger',
        'processing' => 'text-bg-warning',
        'pending' => 'text-bg-secondary',
        'cancelled' => 'text-bg-dark',
        default => 'text-bg-light',
    };
};

$formatDate = static function ($value): string {
    $text = trim((string) $value);
    if ($text === '') {
        return '-';
    }
    $ts = strtotime($text);
    return $ts ? date('Y-m-d H:i:s', $ts) : $text;
};

$bodyPreview = static function (array $row): string {
    $body = (string) ($row['BodyText'] ?? '');
    if (trim($body) === '') {
        $body = strip_tags((string) ($row['BodyHtml'] ?? ''));
    }
    $body = preg_replace('/\s+/', ' ', trim($body)) ?? '';
    if (strlen($body) > 240) {
        return substr($body, 0, 237) . '...';
    }
    return $body;
};

$screenHeader = [
    'title' => 'Email Queue',
    'icon' => 'bi-envelope-paper',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong>Email delivery queue</strong>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Queue Rows</div>
              <div class="fs-4 fw-semibold"><?= h(number_format((int) ($summary['total'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Pending</div>
              <div class="fs-4 fw-semibold"><?= h(number_format((int) ($summary['pending'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Sent</div>
              <div class="fs-4 fw-semibold"><?= h(number_format((int) ($summary['sent'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Failed</div>
              <div class="fs-4 fw-semibold text-danger"><?= h(number_format((int) ($summary['failed'] ?? 0))) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div id="email-queue-runbook" class="alert alert-info border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">Email Queue Runbook</div>
        <div class="mb-2">Use this screen to confirm application email delivery, inspect failed or pending messages, and prepare selected messages for resend when a user or workflow needs another notification.</div>
        <div class="small text-muted mb-2">Queue Selected for Resend only marks the selected emails as pending. Send Queued Emails is the action that attempts delivery for pending messages that are due now.</div>
        <div class="small">Remove Selected excludes messages from sending. Restore Selected returns removed or accidentally queued messages to their previous queue status where that status is available.</div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Queue Controls</h5>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2 align-items-end" id="emailQueueFilterForm">
            <input type="hidden" name="route" value="emailqueue/index">

            <div class="col-md-3">
              <label class="form-label" for="emailQueueStatusFilter">Status</label>
              <select id="emailQueueStatusFilter" class="form-select" name="status">
                <option value="" <?= $selectedStatus === '' ? 'selected' : '' ?>>All</option>
                <?php foreach (['pending', 'processing', 'sent', 'failed', 'cancelled'] as $status): ?>
                  <option value="<?= h($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label" for="emailQueueRowsFilter">Rows</label>
              <select id="emailQueueRowsFilter" class="form-select" name="limit">
                <?php foreach ([25, 50, 100, 200, 500] as $limit): ?>
                  <option value="<?= $limit ?>" <?= $selectedLimit === $limit ? 'selected' : '' ?>><?= $limit ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-5">
              <label class="form-label" for="emailQueueSearchFilter">Search</label>
              <input id="emailQueueSearchFilter" class="form-control" type="text" name="q" value="<?= h($selectedSearch) ?>" placeholder="recipient, subject, message id, error">
            </div>

            <div class="col-md-1 d-grid">
              <button id="emailQueueApplyFilterBtn" type="submit" class="btn btn-primary btn-sm">
                Filter
              </button>
            </div>
            <div class="col-md-1 d-grid">
              <a class="btn btn-outline-secondary btn-sm" href="index.php?route=emailqueue/index">
                Reset
              </a>
            </div>
          </form>

          <hr class="my-3">

          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="index.php?route=emailqueue/index">
              <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </a>
            <button id="emailQueueSendQueuedBtn" class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#processDueModal">
              <i class="bi bi-send-check me-1"></i>Send Queued Emails
            </button>
            <button id="emailQueueBulkResendBtn" type="submit" form="emailQueueBulkForm" class="btn btn-sm btn-outline-primary" <?= $hasRows ? '' : 'disabled' ?>>
              <i class="bi bi-arrow-repeat me-1"></i>Queue Selected for Resend
            </button>
            <button id="emailQueueBulkRemoveBtn" type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#removeQueueModal" <?= $hasRows ? '' : 'disabled' ?>>
              <i class="bi bi-x-circle me-1"></i>Remove Selected
            </button>
            <button id="emailQueueBulkRestoreBtn" type="submit" form="emailQueueBulkForm" class="btn btn-sm btn-outline-secondary" formaction="index.php?route=emailqueue/restore" <?= $hasRows ? '' : 'disabled' ?>>
              <i class="bi bi-arrow-counterclockwise me-1"></i>Restore Selected
            </button>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Queue Entries</h5>
        </div>
        <div class="card-body">
          <?php if ($rows === []): ?>
            <div class="text-center text-muted py-3">No email queue rows match the current filters.</div>
          <?php else: ?>
            <form method="post" action="index.php?route=emailqueue/resend" id="emailQueueBulkForm">
              <?= csrf_field() ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="emailQueueTable">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center">
                        <input class="form-check-input" type="checkbox" id="emailQueueSelectAll" title="Select all">
                      </th>
                      <th>Email ID</th>
                      <th>Status</th>
                      <th>To</th>
                      <th>Subject</th>
                      <th>Send At</th>
                      <th>Last Attempt</th>
                      <th>Attempts</th>
                      <th>Error</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $row): ?>
                      <?php
                        $emailId = (int) ($row['EmailID'] ?? 0);
                        $status = strtolower(trim((string) ($row['Status'] ?? 'pending')));
                        $collapseId = 'email-queue-row-' . $emailId;
                        $preview = $bodyPreview($row);
                      ?>
                      <tr>
                        <td class="text-center">
                          <?php if ($emailId > 0): ?>
                            <input class="form-check-input email-queue-select" type="checkbox" name="email_ids[]" value="<?= h((string) $emailId) ?>">
                          <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= h((string) $emailId) ?></td>
                        <td><span class="badge <?= h($statusBadge($status)) ?>"><?= h($status !== '' ? ucfirst($status) : 'Unknown') ?></span></td>
                        <td class="text-break"><?= h((string) ($row['ToAddress'] ?? '')) ?></td>
                        <td class="text-break">
                          <button class="btn btn-link btn-sm p-0 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#<?= h($collapseId) ?>" aria-expanded="false" aria-controls="<?= h($collapseId) ?>">
                            <?= h((string) ($row['Subject'] ?? '')) ?>
                          </button>
                          <?php if ((int) ($row['MessageID'] ?? 0) > 0): ?>
                            <div class="small text-muted">Message ID <?= h((string) (int) $row['MessageID']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= h($formatDate($row['ScheduledAt'] ?? '')) ?></td>
                        <td class="text-nowrap"><?= h($formatDate($row['LastAttemptAt'] ?? '')) ?></td>
                        <td class="text-end"><?= h((string) (int) ($row['Attempts'] ?? 0)) ?></td>
                        <td class="text-break text-danger small"><?= h((string) ($row['ErrorMsg'] ?? '')) ?></td>
                        <td class="text-end text-nowrap">
                          <?php if ($emailId > 0): ?>
                            <button type="submit" name="email_id" value="<?= h((string) $emailId) ?>" class="btn btn-sm btn-outline-primary">
                              <i class="bi bi-arrow-repeat me-1"></i>Queue for Resend
                            </button>
                          <?php else: ?>
                            <span class="text-muted small">-</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                      <tr class="collapse" id="<?= h($collapseId) ?>">
                        <td colspan="10">
                          <div class="border rounded p-3 bg-light">
                            <div class="small text-muted mb-2">Message Preview</div>
                            <div class="small text-break"><?= h($preview !== '' ? $preview : 'No message body was stored for this queue row.') ?></div>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="processDueModal" tabindex="-1" aria-labelledby="processDueModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" action="index.php?route=emailqueue/process" class="modal-content" id="sendQueuedEmailsForm">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="processDueModalLabel">Send Queued Emails</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        This will send pending emails that are due now and update their queue status.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="sendQueuedEmailsButton">
          <span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true" id="sendQueuedEmailsSpinner"></span>
          <i class="bi bi-send-check me-1" id="sendQueuedEmailsIcon"></i>
          <span id="sendQueuedEmailsLabel">Send Queued Emails</span>
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="removeQueueModal" tabindex="-1" aria-labelledby="removeQueueModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeQueueModalLabel">Remove from Queue</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Selected emails will be marked as cancelled and will not be sent by Send Queued Emails.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger" form="emailQueueBulkForm" formaction="index.php?route=emailqueue/remove">
          <i class="bi bi-x-circle me-1"></i>Remove Selected
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var selectAll = document.getElementById('emailQueueSelectAll');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.email-queue-select').forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
      });
    });
  }

  var sendForm = document.getElementById('sendQueuedEmailsForm');
  if (sendForm) {
    sendForm.addEventListener('submit', function () {
      var button = document.getElementById('sendQueuedEmailsButton');
      var spinner = document.getElementById('sendQueuedEmailsSpinner');
      var icon = document.getElementById('sendQueuedEmailsIcon');
      var label = document.getElementById('sendQueuedEmailsLabel');
      if (button) {
        button.disabled = true;
      }
      if (spinner) {
        spinner.classList.remove('d-none');
      }
      if (icon) {
        icon.classList.add('d-none');
      }
      if (label) {
        label.textContent = 'Sending...';
      }
    });
  }
});
</script>
