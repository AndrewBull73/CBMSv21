<?php
declare(strict_types=1);
/** @var string $title */
/** @var array $rows */
/** @var array $summary */
/** @var string $error */
/** @var int $limit */
/** @var int $refresh */
/** @var bool $currentOnly */
/** @var string $txTypeFilter */
/** @var string $mode */
/** @var int $ctxFy */
/** @var int $ctxVer */
/** @var string $ctxDataObject */

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fmt_num')) {
    function fmt_num($n): string { return number_format((float) $n, 2); }
}
require_once __DIR__ . '/../../../shared/csrf.php';

$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$screenHeader = [
    'title' => $title,
    'icon' => 'bi-safe2',
];
?>
<?php if ($refresh > 0): ?>
    <meta http-equiv="refresh" content="<?= (int) $refresh ?>">
<?php endif; ?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong>Fiscal Year <?= (int) $ctxFy ?></strong>
        <span class="mx-1">/</span>
        <strong>Version <?= (int) $ctxVer ?></strong>
        <?php if ($ctxDataObject !== ''): ?>
          <span class="mx-1">/</span>
          <strong>DataObject <?= h($ctxDataObject) ?></strong>
        <?php endif; ?>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use the current context filters to review balance coverage before reloading ceiling definitions or balances. This screen is intended for operational monitoring, refresh control, and quick breach spotting.
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Keys returned</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['keys_returned'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Total balance</div>
              <div class="fs-4 fw-semibold"><?= fmt_num($summary['total_balance'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Total ceiling</div>
              <div class="fs-4 fw-semibold"><?= fmt_num($summary['total_ceiling'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Breaches</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['breach_count'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Balance Controls</h5>
          <div class="d-flex flex-wrap gap-2">
            <?php
              $toggleRoute = ($mode ?? 'definitions') === 'keys' ? 'ceilings/balances' : 'ceilings/balances-keys';
              $toggleLabel = ($mode ?? 'definitions') === 'keys' ? 'View Definitions' : 'View Keys';
            ?>
            <a class="btn btn-sm btn-outline-secondary" href="index.php?route=<?= h($toggleRoute) ?>&limit=<?= (int) $limit ?>&refresh=<?= (int) $refresh ?>&current=<?= $currentOnly ? '1' : '0' ?>&mode=<?= h(($mode ?? 'definitions') === 'keys' ? 'definitions' : 'keys') ?>&tt=<?= urlencode($txTypeFilter ?? '') ?>">
              <i class="bi bi-arrow-left-right me-1"></i><?= h($toggleLabel) ?>
            </a>
            <form class="m-0" method="post" action="index.php?route=ceilings/reload-balances&limit=<?= (int) $limit ?>&refresh=<?= (int) $refresh ?>&current=<?= $currentOnly ? '1' : '0' ?>">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="limit" value="<?= (int) $limit ?>">
              <input type="hidden" name="refresh" value="<?= (int) $refresh ?>">
              <input type="hidden" name="current" value="<?= $currentOnly ? '1' : '0' ?>">
              <input type="hidden" name="tt" value="<?= h($txTypeFilter ?? '') ?>">
              <input type="hidden" name="mode" value="<?= h($mode ?? 'definitions') ?>">
              <button type="submit" class="btn btn-sm btn-danger">
                <i class="bi bi-database-fill-gear me-1"></i>Reload Ceiling Balances
              </button>
            </form>
            <form class="m-0" method="post" action="index.php?route=ceilings/reload&limit=<?= (int) $limit ?>&refresh=<?= (int) $refresh ?>&current=<?= $currentOnly ? '1' : '0' ?>">
              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="limit" value="<?= (int) $limit ?>">
              <input type="hidden" name="refresh" value="<?= (int) $refresh ?>">
              <input type="hidden" name="current" value="<?= $currentOnly ? '1' : '0' ?>">
              <input type="hidden" name="tt" value="<?= h($txTypeFilter ?? '') ?>">
              <input type="hidden" name="mode" value="<?= h($mode ?? 'definitions') ?>">
              <button type="submit" class="btn btn-sm btn-warning">
                <i class="bi bi-arrow-repeat me-1"></i>Reload Ceilings
              </button>
            </form>
          </div>
        </div>
        <div class="card-body">
          <form class="row g-2 align-items-end" method="get" action="index.php">
            <input type="hidden" name="route" value="<?= h(($mode ?? 'definitions') === 'keys' ? 'ceilings/balances-keys' : 'ceilings/balances') ?>">
            <input type="hidden" name="mode" value="<?= h($mode ?? 'definitions') ?>">
            <input type="hidden" name="current" value="1">
            <div class="col-md-2">
              <label class="form-label">Limit</label>
              <input type="number" name="limit" class="form-control form-control-sm" min="1" max="5000" value="<?= (int) $limit ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Refresh</label>
              <input type="number" name="refresh" class="form-control form-control-sm" min="0" max="120" value="<?= (int) $refresh ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Tx Type</label>
              <input type="text" name="tt" class="form-control form-control-sm" value="<?= h($txTypeFilter ?? '') ?>" placeholder="e.g. 11">
            </div>
            <div class="col-md-4">
              <div class="form-check form-switch pt-4">
                <input class="form-check-input" type="checkbox" role="switch" id="currentOnly" name="current_display" value="1" checked disabled>
                <label class="form-check-label small" for="currentOnly">Current FY/Version only (always on)</label>
              </div>
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-funnel me-1"></i>Apply
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Balance Register</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-hover table-admin table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>DefinitionID</th>
                  <th>FY</th>
                  <th>Version</th>
                  <th>DataObject</th>
                  <th>Budget Class</th>
                  <th class="text-end">Balance</th>
                  <th class="text-end">Ceiling</th>
                  <th class="text-end">Budget</th>
                  <th>Last Tx</th>
                  <th>TTL</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="10" class="text-center text-muted py-3">No ceiling balances found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $r): ?>
                    <?php $neg = ((float) $r['remaining'] < 0); ?>
                    <tr>
                      <td><?= (int) $r['ceiling_definition_id'] ?></td>
                      <td><?= (int) $r['fiscal_year_id'] ?></td>
                      <td><?= (int) $r['version_id'] ?></td>
                      <td><?= h((string) $r['data_object_code']) ?></td>
                      <td><?= h((string) $r['transaction_type_code']) ?></td>
                      <td class="text-end"><?= fmt_num($r['balance_total']) ?></td>
                      <td class="text-end"><?= fmt_num($r['ceiling_total']) ?></td>
                      <td class="text-end <?= $neg ? 'text-danger fw-bold' : 'text-success' ?>"><?= fmt_num($r['remaining']) ?></td>
                      <td><?= $r['last_tx'] === null ? '-' : (int) $r['last_tx'] ?></td>
                      <td><?= (int) $r['ttl'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
