<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$tableInstalled = !empty($tableInstalled);
$activeCount = 0;
$memberCount = 0;
foreach ($rows as $row) {
    if ((int)($row['Active'] ?? 0) === 1) {
        $activeCount++;
    }
    $memberCount += (int)($row['ActiveMemberCount'] ?? 0);
}
$screenHeader = [
    'title' => 'Workflow User Groups',
    'icon' => 'bi-people',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (is_array($flash ?? null) && !empty($flash['text'])): ?>
        <div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= $flash['text'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm">
          Workflow user groups have not been installed yet. Run
          <code>backend-php/config/sql/create_workflow_user_groups.sql</code>
          before maintaining groups.
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Groups</div>
              <div class="fs-4 fw-semibold"><?= count($rows) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active Groups</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active Members</div>
              <div class="fs-4 fw-semibold"><?= $memberCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Task Use</div>
              <div class="fs-4 fw-semibold">Recipient expansion</div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Workflow user groups let task creators select an audience group while CBMS still creates one trackable task for each active member.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Filters</h5></div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="workflow-user-groups/list">
            <div class="col-md-7">
              <input class="form-control" type="text" name="q" value="<?= h((string)($filters['q'] ?? '')) ?>" placeholder="Search group name or description">
            </div>
            <div class="col-md-3">
              <select name="active" class="form-select">
                <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All statuses</option>
                <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active only</option>
                <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
              </select>
            </div>
            <div class="col-md-1 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary">Filter</button></div>
            <div class="col-md-1 d-grid"><a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-user-groups/list">Reset</a></div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Group Register</h5>
          <a href="index.php?route=workflow-user-groups/form" class="btn btn-sm btn-primary <?= !$tableInstalled ? 'disabled' : '' ?>">
            <i class="bi bi-plus-circle me-1"></i>Create Group
          </a>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Group</th>
                  <th>Description</th>
                  <th class="text-end">Members</th>
                  <th>Status</th>
                  <th>Updated</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">No workflow user groups found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td class="fw-semibold"><?= h((string)($row['GroupName'] ?? '')) ?></td>
                      <td><?= h((string)($row['Description'] ?? '')) ?></td>
                      <td class="text-end">
                        <?= (int)($row['ActiveMemberCount'] ?? 0) ?>
                        <span class="text-muted small">active</span>
                      </td>
                      <td>
                        <span class="badge text-bg-<?= ((int)($row['Active'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                          <?= ((int)($row['Active'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                        </span>
                      </td>
                      <td class="small text-muted">
                        <?= h((string)($row['UpdatedAt'] ?? $row['CreatedAt'] ?? '')) ?>
                        <?php if (!empty($row['UpdatedByName'])): ?>
                          <br>by <?= h((string)$row['UpdatedByName']) ?>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="index.php?route=workflow-user-groups/form&id=<?= (int)($row['WorkflowUserGroupID'] ?? 0) ?>">Edit</a>
                      </td>
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
