<?php
declare(strict_types=1);
/** @var array $results */
/** @var bool $simulateDelay (optional) */

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
// Safe accessors
$dbStatus       = $results['db']         ?? null;               // true/false/string(error)
$logTest        = $results['log_test']   ?? null;               // true/false/string(error)
$settings       = is_array($results['settings'] ?? null) ? $results['settings'] : [];
$slowMs         = (string)($settings['SLOW_REQUEST_THRESHOLD_MS'] ?? '(not set)');
$alertsEnabled  = strtoupper((string)($settings['SLOW_REQUEST_ALERTS_ENABLED'] ?? '0'));
$alertsOn       = in_array($alertsEnabled, ['1','TRUE','YES','ON'], true);

// Optional: build a JSON link if your controller supports it (leave as a simple GET param)
$jsonHref = 'index.php?route=diagnostics/index&format=json';
$refreshHref = 'index.php?route=diagnostics/index';
?>
<div class="container mt-4">

  <!-- Primary card -->
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <i class="bi bi-heart-pulse me-2"></i>
        <strong><?= __t('diagnostics_title') ?></strong>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="<?= h($jsonHref) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-braces-asterisk me-1"></i><?= __t('view_json') ?>
        </a>
        <a href="<?= h($refreshHref) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-clockwise me-1"></i><?= __t('refresh') ?>
        </a>
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <tbody>
            <!-- Database -->
            <tr>
              <th class="w-25"><?= __t('diagnostics_database') ?></th>
              <td>
                <?php if ($dbStatus === true): ?>
                  <span class="badge bg-success"><?= __t('ok') ?></span>
                <?php elseif ($dbStatus === false): ?>
                  <span class="badge bg-danger"><?= __t('fail') ?></span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark"><?= __t('error') ?></span>
                  <span class="text-muted ms-2"><?= h((string)$dbStatus) ?></span>
                <?php endif; ?>
              </td>
            </tr>

            <!-- Log Test -->
            <tr>
              <th><?= __t('diagnostics_log_test') ?></th>
              <td>
                <?php if ($logTest === true): ?>
                  <span class="badge bg-success me-2"><?= __t('ok') ?></span>
                  <span class="text-muted"><?= __t('log_entries_written') ?></span>
                <?php elseif ($logTest === false): ?>
                  <span class="badge bg-danger"><?= __t('fail') ?></span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark"><?= __t('error') ?></span>
                  <span class="text-muted ms-2"><?= h((string)$logTest) ?></span>
                <?php endif; ?>
              </td>
            </tr>

            <!-- Mail Test -->
            <tr>
              <th><?= __t('diagnostics_mail_test') ?></th>
              <td>
                <form method="post" action="index.php?route=diagnostics/sendTestEmail" class="d-inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-envelope me-1"></i><?= __t('send_test_email') ?>
                  </button>
                </form>
              </td>
            </tr>

            <!-- Force DB Error -->
            <tr>
              <th><?= __t('diagnostics_force_db_error') ?></th>
              <td>
                <a href="index.php?route=diagnostics/forceDbError" class="btn btn-sm btn-danger">
                  <i class="bi bi-bug me-1"></i><?= __t('trigger_db_error') ?>
                </a>
              </td>
            </tr>

            <!-- Slow Request Settings -->
            <tr>
              <th><?= __t('slow_request_threshold') ?></th>
              <td>
                <span class="me-2"><?= h($slowMs) ?> ms</span>
              </td>
            </tr>
            <tr>
              <th><?= __t('slow_request_alerts_enabled') ?></th>
              <td>
                <?php if ($alertsOn): ?>
                  <span class="badge bg-success"><?= __t('enabled') ?></span>
                <?php else: ?>
                  <span class="badge bg-secondary"><?= __t('disabled') ?></span>
                <?php endif; ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Bottom bar (consistent hint/footer) -->
      <hr class="mt-4 mb-2">
      <div class="d-flex justify-content-between align-items-center px-3 pb-3">
        <p class="text-muted small mb-0">
          <?= __t('form_save_hint') ?>
        </p>
        <div class="d-flex gap-2">
          <a href="<?= h($refreshHref) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-clockwise me-1"></i><?= __t('refresh') ?>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- System Settings card -->
  <div class="card shadow-sm mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <i class="bi bi-sliders me-2"></i>
        <strong><?= __t('system_settings') ?></strong>
      </div>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="w-35"><?= __t('key') ?></th>
              <th><?= __t('value') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($settings)): ?>
              <tr>
                <td colspan="2" class="text-center text-muted py-3"><?= __t('no_records_found') ?></td>
              </tr>
            <?php else: ?>
              <?php foreach ($settings as $k => $v): ?>
                <?php $upper = strtoupper((string)$v); ?>
                <tr>
                  <td><?= h((string)$k) ?></td>
                  <td>
                    <?php if (in_array($upper, ['1','TRUE','YES','ON'], true)): ?>
                      <span class="badge bg-success"><?= __t('ok') ?></span>
                    <?php elseif (in_array($upper, ['0','FALSE','NO','OFF'], true)): ?>
                      <span class="badge bg-secondary"><?= __t('fail') ?></span>
                    <?php else: ?>
                      <?= h((string)$v) ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Bottom bar (consistent hint/footer) -->
      <hr class="mt-4 mb-2">
      <div class="d-flex justify-content-between align-items-center px-3 pb-3">
        <p class="text-muted small mb-0">
          <?= __t('form_save_hint') ?>
        </p>
        <div class="d-flex gap-2">
          <a href="<?= h($refreshHref) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-clockwise me-1"></i><?= __t('refresh') ?>
          </a>
        </div>
      </div>
    </div>
  </div>

</div>
