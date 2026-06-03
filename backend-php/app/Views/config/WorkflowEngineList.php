<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$tableInstalled = !empty($tableInstalled);
$ctx = is_array($_ctx ?? null) ? $_ctx : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ($ctx['FiscalYearID'] ?? '')));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ($ctx['VersionID'] ?? '')));
$workflowCount = count($rows);
$activeCount = count(array_filter($rows, static fn(array $row): bool => (int) ($row['ActiveFlag'] ?? 0) === 1));
$inactiveCount = max(0, $workflowCount - $activeCount);
$openInstanceCount = array_reduce(
    $rows,
    static fn(int $carry, array $row): int => $carry + (int) ($row['OpenInstanceCount'] ?? 0),
    0
);
$screenHeader = [
    'titleKey' => 'workflow_engine_title',
    'title' => 'Workflow Engine',
    'icon' => 'bi-diagram-3',
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
        <?= h(__t('current_context')) ?>:
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-0">
          <strong><?= h(__t('workflow_engine_tables_missing_title')) ?></strong> <?= h(__t('workflow_engine_tables_missing_help')) ?> <code>create_workflow_engine_foundation_v1.sql</code>.
        </div>
      <?php else: ?>
        <div class="alert alert-info border-0 shadow-sm mb-4">
          <?= h(__t('workflow_engine_intro')) ?>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small"><?= h(__t('workflow_areas')) ?></div>
                <div class="fs-4 fw-semibold"><?= h((string) $workflowCount) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small"><?= h(__t('active_definitions')) ?></div>
                <div class="fs-4 fw-semibold"><?= h((string) $activeCount) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small"><?= h(__t('inactive_definitions')) ?></div>
                <div class="fs-4 fw-semibold"><?= h((string) $inactiveCount) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small"><?= h(__t('open_workflow_items')) ?></div>
                <div class="fs-4 fw-semibold"><?= h((string) $openInstanceCount) ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0"><?= h(__t('filter_definitions')) ?></h5>
          </div>
          <div class="card-body">
            <form method="get" action="index.php" class="row g-3" id="workflow-engine-filter-form">
              <input type="hidden" name="route" value="workflow-engine/list">
              <div class="col-md-4">
                <label class="form-label" for="workflowEngineActiveFilter"><?= h(__t('status')) ?></label>
                <select name="active" id="workflowEngineActiveFilter" class="form-select">
                  <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>><?= h(__t('active_only')) ?></option>
                  <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>><?= h(__t('inactive_only')) ?></option>
                  <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>><?= h(__t('all')) ?></option>
                </select>
              </div>
              <div class="col-md-2 d-grid">
                <label class="form-label">&nbsp;</label>
                <button type="submit" id="workflow-engine-filter-btn" class="btn btn-outline-primary"><?= h(__t('apply_filter')) ?></button>
              </div>
            </form>
          </div>
        </div>

        <div class="card shadow-sm mb-0">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= h(__t('workflow_definitions')) ?></h5>
            <a href="index.php?route=workflow-engine/form" id="workflow-engine-create-btn" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i><?= h(__t('create_workflow')) ?></a>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0" id="workflow-engine-definition-table">
                <thead class="table-light">
                  <tr>
                    <th><?= h(__t('workflow')) ?></th>
                    <th><?= h(__t('module')) ?></th>
                    <th><?= h(__t('record_table')) ?></th>
                    <th class="text-center"><?= h(__t('hierarchy')) ?></th>
                    <th class="text-end"><?= h(__t('stages')) ?></th>
                    <th class="text-end"><?= h(__t('actions')) ?></th>
                    <th class="text-end"><?= h(__t('open_items')) ?></th>
                    <th><?= h(__t('status')) ?></th>
                    <th class="text-end"><?= h(__t('actions')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($rows === []): ?>
                    <tr><td colspan="9" class="text-center text-muted py-3"><?= h(__t('workflow_engine_no_definitions_found')) ?></td></tr>
                  <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                      <?php $rowKey = strtolower((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($row['WorkflowAreaCode'] ?? 'workflow'))); ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($row['WorkflowAreaName'] ?? '')) ?></div>
                          <div class="small text-muted"><?= h((string) ($row['WorkflowAreaCode'] ?? '')) ?></div>
                        </td>
                        <td><?= h((string) ($row['ModuleCode'] ?? '')) ?></td>
                        <td><?= h((string) ($row['RecordTableName'] ?? '')) ?></td>
                        <td class="text-center"><?= ((int) ($row['RouteByDataObjectHierarchy'] ?? 0) === 1) ? h(__t('yes')) : h(__t('no')) ?></td>
                        <td class="text-end"><?= h((string) ($row['ActiveStageCount'] ?? $row['StageCount'] ?? 0)) ?></td>
                        <td class="text-end"><?= h((string) ($row['ActiveActionCount'] ?? $row['ActionCount'] ?? 0)) ?></td>
                        <td class="text-end"><?= h((string) ($row['OpenInstanceCount'] ?? 0)) ?></td>
                        <td>
                          <span class="badge <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? h(__t('active')) : h(__t('inactive')) ?>
                          </span>
                        </td>
                        <td class="text-end">
                          <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-primary" id="workflow-engine-open-btn-<?= h($rowKey) ?>" href="index.php?route=workflow-engine/form&workflow_area_code=<?= urlencode((string) ($row['WorkflowAreaCode'] ?? '')) ?>"><?= h(__t('open')) ?></a>
                            <a class="btn btn-outline-primary" id="workflow-engine-inquiry-btn-<?= h($rowKey) ?>" href="index.php?route=workflow-engine/inquiry&workflow_area_code=<?= urlencode((string) ($row['WorkflowAreaCode'] ?? '')) ?>"><?= h(__t('inquiry')) ?></a>
                            <a class="btn btn-outline-primary" id="workflow-engine-diagnostics-btn-<?= h($rowKey) ?>" href="index.php?route=workflow-engine/diagnostics&workflow_area_code=<?= urlencode((string) ($row['WorkflowAreaCode'] ?? '')) ?>"><?= h(__t('diagnostics')) ?></a>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
