<?php declare(strict_types=1); ?>
<?php
$perms = is_array($perms ?? null) ? $perms : [];
$canViewWorkflowTasks = in_array('WORKFLOW_OPERATIONS_VIEW', $perms, true)
    || in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true)
    || in_array('WORKFLOW_OPERATIONS_ADMIN', $perms, true)
    || in_array('ADMIN_ALL', $perms, true)
    || in_array('SYSADMIN', $perms, true);
$workflowIframeParams = [
    'route' => 'workflow/list',
    'mine' => 1,
    'status' => 'open',
    'iframe' => 1,
    'link_context' => 1,
    'fy' => (int) \App\Shared\SessionHelper::get('FiscalYearID', 0),
    'ver' => (int) \App\Shared\SessionHelper::get('VersionID', 0),
    'scope_dataobject_code' => (string) \App\Shared\SessionHelper::get('scope.dataobject_code', ''),
];
$workflowScopeName = trim((string) \App\Shared\SessionHelper::get('scope.dataobject_name', ''));
if ($workflowIframeParams['scope_dataobject_code'] !== '' && $workflowScopeName !== '') {
    $workflowIframeParams['scope_dataobject_name'] = $workflowScopeName;
}
$workflowIframeUrl = 'index.php?' . http_build_query($workflowIframeParams);
?>
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h1 class="h4 mb-3">Central Budget Management System - <?= htmlspecialchars((string) ($clientName ?? 'Government of Lesotho'), ENT_QUOTES, 'UTF-8') ?></h1>
        <div id="systemMessages" class="mb-1" role="status" aria-live="polite" aria-atomic="true">
            <div class="text-muted">Loading system messages...</div>
        </div>
    </div>
</div>

<!-- My Open Tasks (Home) -->
<?php if ($canViewWorkflowTasks): ?>
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-check2-square me-2"></i> <?= __t('my_open_tasks') ?></span>
        <a class="btn btn-sm btn-outline-secondary"
           href="index.php?route=workflow/list&mine=1&status=open"
           title="<?= __t('view_all') ?>">
            <?= __t('view_all') ?>
        </a>
    </div>
    <div class="card-body p-0">
        <iframe
            src="<?= htmlspecialchars($workflowIframeUrl, ENT_QUOTES, 'UTF-8') ?>"
            title="My Open Tasks"
            style="width:100%; border:0; height:420px;"
            onload="try{this.style.height=this.contentWindow.document.body.scrollHeight+'px'}catch(e){}">
        </iframe>
    </div>
</div>
<?php endif; ?>

<script src="assets/js/system_messages.js"></script>
