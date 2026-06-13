<?php
declare(strict_types=1);
$layoutRoute = (string) ($_GET['route'] ?? 'home/index');
$isStrategyRouteLayout = str_starts_with($layoutRoute, 'strategy-');
$sharedUiRoutePrefixes = [
    'home/',
    'strategy/',
    'strategy-',
    'integration-admin/',
    'reports/',
    'report-admin/',
    'screen-tests/',
    'screen-tests-admin/',
    'base-config/',
    'fiscal-years/',
    'versions/',
    'version-types/',
    'currencies/',
    'currency-rates/',
    'dataobject-types/',
    'financial-config/',
    'segments/',
    'segment-values/',
    'workflow-engine/',
    'workflow-task-types/',
    'workflow-task-statuses/',
    'workflow-assignments/',
    'users/',
    'roles/',
    'audit/',
    'diagnostics/',
    'workflow/',
    'training/',
    'training-admin/',
    'system-settings/',
    'session/',
    'sessions/',
    'dataobjectcodes/',
    'dataobjectworkflow/',
    'systemmessages/',
    'metrics/',
    'execution/',
];
$useSharedModuleUi = false;
foreach ($sharedUiRoutePrefixes as $routePrefix) {
    if (str_starts_with($layoutRoute, $routePrefix)) {
        $useSharedModuleUi = true;
        break;
    }
}
$trainingEnabled = (bool) ($trainingEnabled ?? false);
$screenTestingEnabled = (bool) ($screenTestingEnabled ?? false);
$screenTestLauncher = is_array($screenTestLauncher ?? null) ? $screenTestLauncher : null;
$trainingGuide = is_array($trainingGuide ?? null) ? $trainingGuide : null;
$trainingState = is_array($trainingGuide['state'] ?? null) ? $trainingGuide['state'] : [];
$trainingScenario = is_array($trainingGuide['scenario'] ?? null) ? $trainingGuide['scenario'] : [];
$trainingStep = is_array($trainingGuide['step'] ?? null) ? $trainingGuide['step'] : [];
$trainingScreenHooks = is_array($trainingScreenHooks ?? null) ? $trainingScreenHooks : [];
$activeTrainingScenarioId = trim((string) ($trainingState['scenario_id'] ?? ''));
$activeTrainingStepNumber = (int) ($trainingState['current_step'] ?? 0);
$activeTrainingStatus = trim((string) ($trainingState['status'] ?? ''));
$activeTrainingScreenFamily = trim((string) ($trainingScenario['screen_family'] ?? ($trainingScreenHooks['screenFamily'] ?? '')));
$screenHookRoute = trim((string) ($trainingScreenHooks['route'] ?? $layoutRoute));
$screenHookView = trim((string) ($trainingScreenHooks['view'] ?? ''));
$pageTitleKey = trim((string) ($titleKey ?? ''));
$pageTitle = $pageTitleKey !== ''
    ? __t($pageTitleKey)
    : __t((string) ($title ?? 'CBMSv2.1'));
?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        .list-group-item .chev { transition: transform .15s ease-in-out; }
        .list-group-item[aria-expanded="true"] .chev { transform: rotate(90deg); }
        .offcanvas .list-group-item { border: 0; }
        .offcanvas .list-group-item,
        .offcanvas .list-group-item-action,
        .offcanvas .collapse .list-group-item {
            font-size: .92rem;
        }
        .offcanvas .collapse .list-group-item {
            padding-top: .55rem;
            padding-bottom: .55rem;
        }
        .modal-xxl { --bs-modal-width: 900px; }
        .navbar .btn, .navbar .dropdown-toggle { white-space: nowrap; }
        .btn-group-sm .btn, .btn.btn-sm { padding: .25rem .5rem; font-size: .875rem; line-height: 1.2; }
        .table-admin > :not(caption) > * > * {
            padding-top: .5rem;
            padding-bottom: .5rem;
            vertical-align: middle;
        }
        .table-admin .btn-group-sm .btn {
            padding: .125rem .375rem;
            font-size: .875rem;
            line-height: 1.2;
        }
        .table-admin .btn i {
            font-size: 1em;
            line-height: 1;
            vertical-align: -0.125em;
        }
        .table-admin .badge {
            font-size: .75rem;
        }
        .strategy-quick-nav-group + .strategy-quick-nav-group {
            border-top: 1px solid var(--bs-border-color);
            padding-top: .75rem;
        }
        .strategy-ui .container.mt-4,
        .strategy-ui .container-fluid.py-3 {
            font-size: .95rem;
        }
        .strategy-ui .container.mt-4 > .card.shadow-sm,
        .strategy-ui .container-fluid.py-3 > .card.shadow-sm,
        .strategy-ui .container-fluid.py-3 > .row .card.shadow-sm,
        .strategy-ui .container.mt-4 > .row .card.shadow-sm {
            background: #fff;
            border: 1px solid #e4ebf2;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05) !important;
        }
        .strategy-ui .card-header {
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
            border-bottom: 1px solid #e4ebf2;
            padding: 1.05rem 1.15rem;
        }
        .strategy-ui .card-header h3,
        .strategy-ui .card-header h5 {
            font-weight: 700;
            letter-spacing: -.01em;
            margin-bottom: 0;
        }
        .strategy-ui .card-header h3 {
            font-size: 1.15rem;
        }
        .strategy-ui .card-header h5 {
            font-size: 1rem;
        }
        .strategy-ui .card-body {
            padding: 1.05rem 1.15rem;
        }
        .strategy-ui .table-responsive .table {
            margin-bottom: 0;
        }
        .strategy-ui .table thead th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6f7f90;
            border-bottom-width: 1px;
        }
        .strategy-ui .table td,
        .strategy-ui .table th {
            padding: .85rem 1rem;
            font-size: .89rem;
            vertical-align: top;
        }
        .strategy-ui .table.align-middle td,
        .strategy-ui .table.align-middle th,
        .strategy-ui .table-admin td,
        .strategy-ui .table-admin th {
            vertical-align: middle;
        }
        .strategy-ui .alert {
            border-radius: .9rem;
            border-width: 1px;
        }
        .app-flash .alert-heading {
            font-size: .95rem;
            font-weight: 700;
            margin-bottom: .2rem;
        }
        .app-flash-detail {
            font-size: .82rem;
            opacity: .9;
            margin-top: .35rem;
        }
        .strategy-ui .form-control,
        .strategy-ui .form-select {
            font-size: .875rem;
        }
        .strategy-ui .form-label {
            font-size: .84rem;
            font-weight: 600;
            color: #415161;
            margin-bottom: .4rem;
        }
        .strategy-ui .form-text {
            font-size: .76rem;
        }
        .strategy-ui .btn {
            font-size: .875rem;
            line-height: 1.2;
            padding: .25rem .5rem;
            border-radius: .7rem;
        }
        .strategy-ui .btn.btn-sm,
        .strategy-ui .btn-group-sm .btn {
            border-radius: .65rem;
        }
        .strategy-ui .badge {
            font-weight: 600;
        }
        .strategy-ui .modal-content {
            border: 1px solid #e4ebf2;
            border-radius: 1rem;
            box-shadow: 0 .75rem 2rem rgba(43, 63, 87, 0.12);
        }
        .strategy-ui .modal-header,
        .strategy-ui .modal-footer {
            padding: 1rem 1.15rem;
        }
        .strategy-ui .modal-body {
            padding: 1rem 1.15rem;
        }
    </style>
</head>
<body
    class="bg-light<?= $useSharedModuleUi ? ' strategy-ui' : '' ?>"
    data-cbms-route="<?= htmlspecialchars($layoutRoute, ENT_QUOTES, 'UTF-8') ?>"
    data-screen-testing-enabled="<?= $screenTestingEnabled ? '1' : '0' ?>"
    data-training-enabled="<?= $trainingEnabled ? '1' : '0' ?>"
    data-training-scenario-id="<?= htmlspecialchars($activeTrainingScenarioId, ENT_QUOTES, 'UTF-8') ?>"
    data-training-step="<?= (int) $activeTrainingStepNumber ?>"
>
<?php
    use App\Shared\Lang;
    use App\Shared\SessionHelper;

    require_once __DIR__ . '/../../../shared/workflow_helpers.php';

    $isLoggedIn = (bool) SessionHelper::get('auth.user_id');

    if ($isLoggedIn):
        if (!isset($conn) || !($conn instanceof \PDO)):
            require __DIR__ . '/../../../config/db.php';
        endif;
        require_once __DIR__ . '/../../../app/Models/FiscalContextModel.php';
        require_once __DIR__ . '/../../../shared/csrf.php';

        $ctxModel = new \App\Models\FiscalContextModel($conn);
        $fiscalYears = $ctxModel->listFiscalYears();
        $currentFY = (int)(SessionHelper::get('FiscalYearID') ?? 0);
        $currentVer = (int)(SessionHelper::get('VersionID') ?? 0);
        $routeVersionTypeId = null;
        if (str_starts_with($layoutRoute, 'execution/')) {
            $routeVersionTypeId = 2;
        }
        $versionsList = $currentFY ? $ctxModel->listVersions($currentFY, $routeVersionTypeId) : [];
        $_csrfToken = csrf_token();

        $fyLabel = 'FY';
        if (!empty($fiscalYears)):
            foreach ($fiscalYears as $fy):
                if ((int)$fy['FiscalYearID'] === $currentFY):
                    $fyLabel = (string)($fy['YearLabel'] ?? $currentFY);
                    break;
                endif;
            endforeach;
        endif;

        $verLabel = 'Version';
        if (!empty($versionsList)):
            foreach ($versionsList as $v):
                if ((int)$v['VersionID'] === $currentVer):
                    $verLabel = (string)($v['VersionLabel'] ?? $currentVer);
                    break;
                endif;
            endforeach;
        endif;
    endif;

    $menuFile = __DIR__ . '/../../../config/menu.php';
    $menu = (is_file($menuFile) && is_array($tmp = require $menuFile)) ? $tmp : [];
    require_once __DIR__ . '/../../../shared/nav_tree.php';
    $menuJumpIndex = menu_jump_build_index($menu);
    $menuJumpEntries = is_array($menuJumpIndex['entries'] ?? null) ? $menuJumpIndex['entries'] : [];

    $currentRoute = $layoutRoute;
    $activeLang = Lang::getActiveLang();
    $availableLangs = Lang::availableLanguageLabels();

    if (!function_exists('envFlag')):
        function envFlag(string $key, bool $default = false): bool {
            $val = getenv($key);
            if ($val === false) return $default;
            $val = strtolower(trim((string)$val));
            return in_array($val, ['1','true','yes','on'], true);
        }
    endif;

    $scopeCode = (string)(SessionHelper::get('scope.dataobject_code') ?? '');
    $scopeName = (string)(SessionHelper::get('scope.dataobject_name') ?? '');
    $scopeLabel = $scopeCode !== ''
        ? trim(($scopeName !== '' ? $scopeName : $scopeCode) . ($scopeName && $scopeCode ? " ($scopeCode)" : ''))
        : __t('not_set');

    $scopeWorkflowStatus = '';
    if ($isLoggedIn && $scopeCode !== '' && (int)($currentFY ?? 0) > 0 && isset($conn) && $conn instanceof \PDO) {
        try {
            $scopeStatusStmt = $conn->prepare("
                SELECT TOP 1 COALESCE(Status, '')
                FROM dbo.tblDataObjectWorkflowStatus
                WHERE FiscalYearID = :fy
                  AND DataObjectCode = :code
                ORDER BY CASE WHEN VersionID = :ver THEN 0 ELSE 1 END,
                         DateUpdated DESC,
                         WorkflowStatusID DESC
            ");
            $scopeStatusStmt->execute([
                ':fy' => (int) $currentFY,
                ':ver' => (int) $currentVer,
                ':code' => $scopeCode,
            ]);
            $scopeWorkflowStatus = trim((string) ($scopeStatusStmt->fetchColumn() ?: ''));
        } catch (\Throwable $e) {
            $scopeWorkflowStatus = '';
        }
    }

    $scopeStatusCode = strtoupper($scopeWorkflowStatus);
    $scopeStatusLabel = match ($scopeStatusCode) {
        'OPEN' => __t('workflow_status_open'),
        'IN PROGRESS' => __t('workflow_status_in_progress'),
        'COMPLETED' => __t('workflow_status_completed'),
        'APPROVED' => __t('workflow_status_approved'),
        'REJECTED' => __t('workflow_status_rejected'),
        'CLOSED' => __t('workflow_status_closed'),
        default => __t('workflow_status_not_set'),
    };
    $scopeStatusIconClass = match (strtoupper($scopeWorkflowStatus)) {
        'OPEN' => 'bi-unlock text-success',
        'IN PROGRESS' => 'bi-hourglass-split text-primary',
        'COMPLETED' => 'bi-check-circle text-success',
        'APPROVED' => 'bi-hand-thumbs-up text-success',
        'REJECTED' => 'bi-x-circle text-danger',
        'CLOSED' => 'bi-lock text-danger',
        default => 'bi-question-circle text-warning',
    };
    $scopeStatusButtonClass = match (strtoupper($scopeWorkflowStatus)) {
        'OPEN' => 'btn-outline-success',
        'IN PROGRESS' => 'btn-outline-primary',
        'COMPLETED', 'APPROVED' => 'btn-outline-success',
        'REJECTED' => 'btn-outline-danger',
        'CLOSED' => 'btn-outline-danger',
        default => 'btn-outline-warning',
    };

    $pickerParams = [
        'route' => 'dataobjects/picker',
        'iframe' => 1,
        'link_context' => 1,
        'fy' => (int) ($currentFY ?? 0),
        'ver' => (int) ($currentVer ?? 0),
        'scope_dataobject_code' => $scopeCode,
    ];
    if ($scopeCode !== '') {
        $pickerParams['selected'] = $scopeCode;
        if ($scopeName !== '') {
            $pickerParams['scope_dataobject_name'] = $scopeName;
        }
    }
    $pickerUrl = 'index.php?' . http_build_query($pickerParams);

    $rawReturnUrl = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php?route=home/index');
    $returnUrl = urlencode($rawReturnUrl);
    $jumpReturnUrl = (string)($_SERVER['REQUEST_URI'] ?? 'index.php?route=home/index');
    $clearScopeUrl = 'index.php?' . http_build_query([
        'route' => 'dataobjects/select',
        'clear' => 1,
        'link_context' => 1,
        'fy' => (int) ($currentFY ?? 0),
        'ver' => (int) ($currentVer ?? 0),
        'scope_dataobject_code' => '',
        'return' => $rawReturnUrl,
    ]);

    app_log("main.php: Rendering layout", [
        'userId' => (int) SessionHelper::get('auth.user_id', 0),
        'username' => (string) SessionHelper::get('auth.username', ''),
        'roles' => SessionHelper::get('auth.roles', []),
        'perms' => SessionHelper::get('auth.perms', []),
        'session_id' => session_id(),
        'route' => $currentRoute
    ], 'debug');
?>
<nav class="navbar navbar-dark bg-dark">
    <div class="container-fluid">
        <div class="d-flex align-items-center w-100">
            <a class="navbar-brand" href="index.php?route=home/index">CBMSv2.1</a>
            <div class="d-flex align-items-center gap-2 ms-2 me-auto flex-wrap">
                <a class="btn btn-outline-light btn-sm" id="homeNavBtn" href="index.php?route=home/index" title="<?= __t('menu_home') ?>">
                    <i class="bi bi-house-door me-1"></i><?= __t('menu_home') ?>
                </a>
                <button class="btn btn-outline-light btn-sm" type="button" id="appMenuToggleBtn"
                        data-bs-toggle="offcanvas" data-bs-target="#appMenu" aria-controls="appMenu">
                    <i class="bi bi-list me-1"></i> <?= __t('menu') ?>
                </button>
                <?php if ($isLoggedIn): ?>
                    <button
                        class="btn btn-outline-light btn-sm"
                        type="button"
                        id="openWindowBtn"
                        title="<?= htmlspecialchars(__t('open_new_window_help'), ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <i class="bi bi-window-plus me-1"></i><?= __t('open_new_window') ?>
                    </button>
                <?php endif; ?>
                <?php if ($isLoggedIn): ?>
                    <form class="d-flex align-items-center gap-2" method="get" action="index.php">
                        <input type="hidden" name="route" value="menu-jump/go">
                        <input type="hidden" name="return" value="<?= htmlspecialchars($jumpReturnUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="input-group input-group-sm" style="width: 14rem;">
                            <span class="input-group-text bg-dark text-light border-secondary" title="<?= htmlspecialchars(__t('screen_code_or_route'), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-signpost-split"></i>
                            </span>
                            <input
                                class="form-control form-control-sm bg-dark text-light border-secondary"
                                type="text"
                                id="menuJumpInput"
                                name="code"
                                list="menuJumpCodes"
                                placeholder="<?= htmlspecialchars(__t('go_to_code'), ENT_QUOTES, 'UTF-8') ?>"
                                title="<?= htmlspecialchars(__t('screen_code_or_route_help'), ENT_QUOTES, 'UTF-8') ?>"
                                autocomplete="off"
                            >
                        </div>
                        <button class="btn btn-outline-light btn-sm" type="submit" id="menuJumpGoBtn" title="<?= htmlspecialchars(__t('go_to_screen'), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi bi-arrow-right-circle"></i>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($isLoggedIn): ?>
                    <form id="fiscalContextForm" class="d-flex align-items-center gap-2" method="post" action="index.php?route=context/set">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="return" id="contextReturnUrl" value="<?= htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? 'index.php?route=home/index'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="FiscalYearID" id="FiscalYearID_hidden" value="<?= (int)$currentFY ?>">
                        <input type="hidden" name="VersionID" id="VersionID_hidden" value="<?= (int)$currentVer ?>">
                        <div class="dropdown">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="fyDropdownBtn"
                                    data-bs-toggle="dropdown" aria-expanded="false" title="<?= __t('fiscal_year') ?>">
                                <i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($fyLabel, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="fyDropdownBtn">
                                <?php if (!empty($fiscalYears)): ?>
                                    <?php foreach ($fiscalYears as $fy): ?>
                                        <?php $fyId = (int)$fy['FiscalYearID']; ?>
                                        <li>
                                            <a class="dropdown-item <?= $fyId === $currentFY ? 'active' : '' ?>"
                                               href="#" data-fy-id="<?= $fyId ?>">
                                               <?= htmlspecialchars((string)($fy['YearLabel'] ?? $fyId), ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><span class="dropdown-item disabled"><?= __t('no_results') ?></span></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="verDropdownBtn"
                                    data-bs-toggle="dropdown" aria-expanded="false" title="<?= __t('version') ?>">
                                <i class="bi bi-layers me-1"></i><?= htmlspecialchars($verLabel, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark" id="verDropdownMenu" aria-labelledby="verDropdownBtn">
                                <?php if (empty($versionsList)): ?>
                                    <li><span class="dropdown-item disabled"><?= __t('no_results') ?></span></li>
                                <?php else: ?>
                                    <?php foreach ($versionsList as $v): ?>
                                        <?php $vid = (int)$v['VersionID']; ?>
                                        <li>
                                            <a class="dropdown-item <?= $vid === $currentVer ? 'active' : '' ?>" href="#"
                                               data-version-id="<?= $vid ?>">
                                               <?= htmlspecialchars((string)($v['VersionLabel'] ?? $vid), ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </form>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-info btn-sm" type="button" id="dataScopeBtn"
                                data-bs-toggle="modal" data-bs-target="#dataObjectPickerModal"
                                title="<?= __t('select_data_scope') ?>">
                            <i class="bi bi-diagram-3 me-1"></i>
                            <span class="badge bg-info text-dark"><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                        <?php if ($scopeCode !== ''): ?>
                            <a class="btn btn-sm btn-outline-light" id="clearScopeBtn" href="<?= $clearScopeUrl ?>">
                                <i class="bi bi-x-circle me-1"></i><?= __t('clear') ?>
                            </a>
                        <?php endif; ?>
                        <button class="btn <?= htmlspecialchars($scopeStatusButtonClass, ENT_QUOTES, 'UTF-8') ?> btn-sm d-flex align-items-center"
                                id="scopeStatusBtn"
                                type="button"
                                title="<?= htmlspecialchars(__t('data_scope_workflow_status'), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi bi-diagram-3 me-1"></i>
                            <i class="bi <?= htmlspecialchars($scopeStatusIconClass, ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                            <span><?= htmlspecialchars($scopeStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" id="langMenu"
                            data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars(__t('language'), ENT_QUOTES, 'UTF-8') ?>">
                        🌐 <?= htmlspecialchars($availableLangs[$activeLang] ?? strtoupper($activeLang), ENT_QUOTES) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end" aria-labelledby="langMenu">
                        <?php foreach ($availableLangs as $code => $label): ?>
                            <li>
                                <a class="dropdown-item <?= $code === $activeLang ? 'active' : '' ?>"
                                   href="index.php?route=lang/switch&lang=<?= urlencode($code) ?>">
                                   <?= htmlspecialchars($label, ENT_QUOTES) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (SessionHelper::get('auth.user_id')): ?>
                    <span class="navbar-text text-light d-none d-sm-inline">
                        <?= __t('user') ?>:
                        <?= htmlspecialchars((string)SessionHelper::get('auth.username'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <button class="btn btn-outline-light btn-sm" 
                            type="button"
                            id="helpBtn"
                            data-route="<?= htmlspecialchars($currentRoute, ENT_QUOTES) ?>">
                        <i class="bi bi-question-circle"></i> <?= __t('help') ?>
                    </button>
                    <?php if ($screenTestingEnabled && $screenTestLauncher !== null): ?>
                        <?php
                            $screenTestTitle = trim((string) ($screenTestLauncher['scenarioTitle'] ?? ''));
                            $screenTestHint = $screenTestTitle !== ''
                                ? __t('screen_tests_open_current_script', ['title' => $screenTestTitle])
                                : __t('screen_tests_find_for_current_screen', ['route' => $currentRoute]);
                        ?>
                        <a
                            class="btn btn-outline-light btn-sm"
                            id="screenTestBtn"
                            href="<?= htmlspecialchars((string) ($screenTestLauncher['url'] ?? 'index.php?route=screen-tests/scenarios'), ENT_QUOTES, 'UTF-8') ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            title="<?= htmlspecialchars($screenTestHint, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <i class="bi bi-clipboard-check"></i> <?= __t('screen_tests_title') ?>
                        </a>
                    <?php endif; ?>
                    <a class="btn btn-outline-light btn-sm" id="accountNavBtn" href="index.php?route=auth/account"><?= __t('account') ?></a>
                    <a class="btn btn-outline-light btn-sm" id="logoutNavBtn" href="index.php?route=auth/logout"><?= __t('logout') ?></a>
                <?php else: ?>
                    <span class="navbar-text text-light d-none d-sm-inline"><?= __t('guest') ?></span>
                    <a class="btn btn-light btn-sm" href="index.php?route=auth/loginForm"><?= __t('login') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<?php if ($isLoggedIn && $menuJumpEntries !== []): ?>
    <datalist id="menuJumpCodes">
        <?php foreach ($menuJumpEntries as $entry): ?>
            <?php
                $code = trim((string) ($entry['code'] ?? ''));
                $route = trim((string) ($entry['route'] ?? ''));
                $label = trim((string) ($entry['label'] ?? $route));
                $value = $code !== '' ? $code : $route;
                $hint = $code !== '' ? $code . ' - ' . $label . ' (' . $route . ')' : $label . ' (' . $route . ')';
            ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" label="<?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?>"></option>
        <?php endforeach; ?>
    </datalist>
<?php endif; ?>
<div class="offcanvas offcanvas-start" tabindex="-1" id="appMenu" aria-labelledby="appMenuLabel" data-bs-scroll="true">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="appMenuLabel"><?= __t('navigation') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= htmlspecialchars(__t('close'), ENT_QUOTES, 'UTF-8') ?>"></button>
    </div>
    <div class="offcanvas-body p-0">
        <?php if (envFlag('APP_DEBUG', false)): ?>
            <div class="alert alert-warning m-2 p-2">
                <strong>DEBUG MENU</strong><br>
                <?= 'Menu items loaded: ' . count($menu) ?><br>
                <?= 'Current route: ' . htmlspecialchars($currentRoute, ENT_QUOTES) ?><br>
                <?= 'Roles in session: ' . htmlspecialchars(json_encode(SessionHelper::get('auth.roles', [])), ENT_QUOTES) ?><br>
                <?= 'Perms in session: ' . htmlspecialchars(json_encode(SessionHelper::get('auth.perms', [])), ENT_QUOTES) ?>
            </div>
        <?php endif; ?>
        <div class="list-group list-group-flush">
            <?= render_offcanvas_level($menu, $currentRoute, 0) ?>
        </div>
    </div>
</div>
<main
    id="appMain"
    class="container my-3"
    data-screen-route="<?= htmlspecialchars($screenHookRoute, ENT_QUOTES, 'UTF-8') ?>"
    data-screen-view="<?= htmlspecialchars($screenHookView, ENT_QUOTES, 'UTF-8') ?>"
    data-screen-family="<?= htmlspecialchars($activeTrainingScreenFamily, ENT_QUOTES, 'UTF-8') ?>"
>
    <?php
        $flashMessage = $layoutFlash ?? SessionHelper::get('flash.message', null);
        $sharedGuidanceRoutePrefixes = [
            'strategy/',
            'strategy-',
            'integration-admin/',
            'reports/',
            'report-admin/',
            'base-config/',
            'fiscal-years/',
            'versions/',
            'version-types/',
            'currencies/',
            'currency-rates/',
            'dataobject-types/',
            'financial-config/',
            'segments/',
            'segment-values/',
            'dataobjectcodes/',
            'workflow-engine/',
            'workflow-task-types/',
            'workflow-task-statuses/',
            'workflow-assignments/',
            'system-settings/',
            'users/',
            'roles/',
            'training/',
            'training-admin/',
            'access-matrix/',
            'sessions/',
            'execution/',
            'screen-tests/',
            'transaction-type-segment-config/',
            'ceilings/',
            'scenario-admin/',
            'transaction-calc-diagnostics/',
            'full-recalculation/',
        ];
        $showSharedRouteGuidance = false;
        foreach ($sharedGuidanceRoutePrefixes as $routePrefix) {
            if (str_starts_with($currentRoute, $routePrefix)) {
                $showSharedRouteGuidance = true;
                break;
            }
        }
        if (is_array($flashMessage) && !empty($flashMessage['text'])):
            SessionHelper::forget('flash.message');
            unset($_SESSION['flash']['message'], $_SESSION['flash.message']);
            if (isset($_SESSION['flash']) && empty($_SESSION['flash'])) unset($_SESSION['flash']);
            $type = $flashMessage['type'] ?? 'info';
            $allowed = ['success','danger','warning','info'];
            if (!in_array($type, $allowed, true)) $type = 'info';
            $autoDismiss = in_array($type, ['success','info'], true);
    ?>
        <div class="app-flash alert alert-<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show <?= $autoDismiss ? 'auto-dismiss' : '' ?>" role="alert">
            <?php if (!empty($flashMessage['title'])): ?>
                <div class="alert-heading"><?= htmlspecialchars((string)$flashMessage['title'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div><?= htmlspecialchars((string)$flashMessage['text'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php if (!empty($flashMessage['detail'])): ?>
                <div class="app-flash-detail"><?= htmlspecialchars((string)$flashMessage['detail'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($currentRoute === 'auth/loginForm'): ?>
        <?= $content ?? '' ?>
    <?php else: ?>
        <?php if (SessionHelper::get('auth.user_id')): ?>
            <?php if ($showSharedRouteGuidance): ?>
                <?php require __DIR__ . '/../strategy/_RouteHelp.php'; ?>
                <?php require __DIR__ . '/../strategy/_QuickNav.php'; ?>
            <?php endif; ?>
            <?php if ($trainingEnabled && $trainingGuide !== null): ?>
                <?php require __DIR__ . '/../training/_TrainingBanner.php'; ?>
            <?php endif; ?>
            <div
                id="screenContent"
                data-screen-route="<?= htmlspecialchars($screenHookRoute, ENT_QUOTES, 'UTF-8') ?>"
                data-screen-view="<?= htmlspecialchars($screenHookView, ENT_QUOTES, 'UTF-8') ?>"
                data-screen-family="<?= htmlspecialchars($activeTrainingScreenFamily, ENT_QUOTES, 'UTF-8') ?>"
            >
                <?= $content ?? '' ?>
            </div>
            <?php if ($trainingEnabled && $trainingGuide !== null): ?>
                <?php require __DIR__ . '/../training/_TrainingOverlay.php'; ?>
            <?php endif; ?>
        <?php else: ?>
            <p>Please log in to view this content.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../training/_TrainingAutoHooks.php'; ?>
<div class="modal fade" id="dataObjectPickerModal" tabindex="-1" aria-hidden="true" aria-labelledby="dataObjectPickerLabel">
    <div class="modal-dialog modal-dialog-centered modal-xxl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="dataObjectPickerLabel" class="modal-title">
                    <i class="bi bi-diagram-3 me-2"></i><?= __t('select_data_scope') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __t('close') ?>"></button>
            </div>
            <div class="modal-body p-0">
                <iframe
                    id="dataObjectPickerFrame"
                    src="<?= htmlspecialchars($pickerUrl, ENT_QUOTES, 'UTF-8') ?>"
                    title="DataObject Picker"
                    style="width:100%;height:520px;border:0;"
                    loading="lazy"
                ></iframe>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline-secondary" href="<?= $clearScopeUrl ?>">
                    <i class="bi bi-x-circle me-1"></i><?= __t('clear_scope') ?>
                </a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    <?= __t('done') ?>
                </button>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
window.CBMSWindowContext = {
    enabled: <?= $isLoggedIn ? 'true' : 'false' ?>,
    fy: <?= (int) ($currentFY ?? 0) ?>,
    ver: <?= (int) ($currentVer ?? 0) ?>,
    scopeCode: <?= json_encode((string) ($scopeCode ?? ''), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    scopeName: <?= json_encode((string) ($scopeName ?? ''), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
};

window.CBMSTrainingContext = {
    enabled: <?= ($trainingEnabled && $activeTrainingScenarioId !== '') ? 'true' : 'false' ?>,
    scenarioId: <?= json_encode($activeTrainingScenarioId, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    currentStep: <?= (int) $activeTrainingStepNumber ?>,
    status: <?= json_encode($activeTrainingStatus, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
    screenFamily: <?= json_encode($activeTrainingScreenFamily, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
};

(function WindowScopedContext() {
    const config = window.CBMSWindowContext || {};
    if (!config.enabled) {
        return;
    }

    const isInternalAppUrl = (url) => {
        return url.origin === window.location.origin
            && (url.pathname.endsWith('/index.php') || url.searchParams.has('route'));
    };

    const applyContextToUrl = (rawUrl) => {
        if (!rawUrl || rawUrl.startsWith('#') || rawUrl.startsWith('javascript:') || rawUrl.startsWith('mailto:') || rawUrl.startsWith('tel:')) {
            return rawUrl;
        }

        let url;
        try {
            url = new URL(rawUrl, window.location.href);
        } catch (error) {
            return rawUrl;
        }

        if (!isInternalAppUrl(url)) {
            return rawUrl;
        }

        url.searchParams.set('link_context', '1');
        if (Number(config.fy || 0) > 0) {
            url.searchParams.set('fy', String(config.fy));
        }
        if (Number(config.ver || 0) > 0) {
            url.searchParams.set('ver', String(config.ver));
        }
        url.searchParams.set('scope_dataobject_code', String(config.scopeCode || ''));
        if (String(config.scopeCode || '').trim() !== '' && String(config.scopeName || '').trim() !== '') {
            url.searchParams.set('scope_dataobject_name', String(config.scopeName));
        } else {
            url.searchParams.delete('scope_dataobject_name');
        }

        return url.pathname + (url.search ? url.search : '') + (url.hash ? url.hash : '');
    };

    const upsertHidden = (form, name, value) => {
        let input = form.querySelector(`input[name="${name}"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    };

    const decorateForm = (form) => {
        const actionAttr = form.getAttribute('action') || window.location.href;
        let actionUrl;
        try {
            actionUrl = new URL(actionAttr, window.location.href);
        } catch (error) {
            return;
        }

        if (!isInternalAppUrl(actionUrl)) {
            return;
        }

        upsertHidden(form, 'link_context', '1');
        upsertHidden(form, 'fy', String(config.fy || 0));
        upsertHidden(form, 'ver', String(config.ver || 0));

        upsertHidden(form, 'scope_dataobject_code', String(config.scopeCode || ''));
        if (String(config.scopeCode || '').trim() !== '') {
            upsertHidden(form, 'scope_dataobject_name', String(config.scopeName || ''));
        } else {
            const scopeNameInput = form.querySelector('input[name="scope_dataobject_name"]');
            if (scopeNameInput) { scopeNameInput.remove(); }
        }

        const returnInput = form.querySelector('input[name="return"]');
        if (returnInput) {
            const currentReturn = String(returnInput.value || window.location.href);
            returnInput.value = applyContextToUrl(currentReturn);
        }
    };

    const decorateAnchors = () => {
        document.querySelectorAll('a[href]').forEach((anchor) => {
            const href = anchor.getAttribute('href') || '';
            const decorated = applyContextToUrl(href);
            if (decorated && decorated !== href) {
                anchor.setAttribute('href', decorated);
            }
        });
    };

    const decorateIframe = (iframe) => {
        const src = iframe.getAttribute('src') || '';
        const decorated = applyContextToUrl(src);
        if (decorated && decorated !== src) {
            iframe.setAttribute('src', decorated);
        }
    };

    const decorateIframes = () => {
        document.querySelectorAll('iframe[src]').forEach((iframe) => {
            decorateIframe(iframe);
        });
    };

    const decorateForms = () => {
        document.querySelectorAll('form').forEach((form) => {
            decorateForm(form);
        });
    };

    const decorateNode = (node) => {
        if (!(node instanceof Element)) {
            return;
        }
        if (node.matches('a[href]')) {
            const href = node.getAttribute('href') || '';
            const decorated = applyContextToUrl(href);
            if (decorated && decorated !== href) {
                node.setAttribute('href', decorated);
            }
        }
        if (node.matches('form')) {
            decorateForm(node);
        }
        if (node.matches('iframe[src]')) {
            decorateIframe(node);
        }
        if (typeof node.querySelectorAll === 'function') {
            node.querySelectorAll('a[href]').forEach((anchor) => {
                const href = anchor.getAttribute('href') || '';
                const decorated = applyContextToUrl(href);
                if (decorated && decorated !== href) {
                    anchor.setAttribute('href', decorated);
                }
            });
            node.querySelectorAll('form').forEach((form) => decorateForm(form));
            node.querySelectorAll('iframe[src]').forEach((iframe) => decorateIframe(iframe));
        }
    };

    if (typeof window.fetch === 'function') {
        const originalFetch = window.fetch.bind(window);
        window.fetch = function(input, init) {
            if (typeof input === 'string') {
                return originalFetch(applyContextToUrl(input), init);
            }
            if (input instanceof URL) {
                return originalFetch(applyContextToUrl(input.toString()), init);
            }
            return originalFetch(input, init);
        };
    }

    if (typeof XMLHttpRequest !== 'undefined' && XMLHttpRequest.prototype && typeof XMLHttpRequest.prototype.open === 'function') {
        const originalXhrOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
            const nextUrl = typeof url === 'string' ? applyContextToUrl(url) : url;
            return originalXhrOpen.call(this, method, nextUrl, async, user, password);
        };
    }

    const wrapHistoryMethod = (methodName) => {
        const original = window.history[methodName];
        if (typeof original !== 'function') {
            return;
        }
        window.history[methodName] = function(state, title, url) {
            let nextUrl = url;
            if (typeof url === 'string') {
                nextUrl = applyContextToUrl(url);
            } else if (url instanceof URL) {
                nextUrl = applyContextToUrl(url.toString());
            }
            return original.call(window.history, state, title, nextUrl);
        };
    };
    wrapHistoryMethod('pushState');
    wrapHistoryMethod('replaceState');

    const currentUrlWithContext = applyContextToUrl(window.location.href);
    if (currentUrlWithContext && currentUrlWithContext !== (window.location.pathname + window.location.search + window.location.hash)) {
        window.history.replaceState({}, document.title, currentUrlWithContext);
    }

    document.addEventListener('DOMContentLoaded', () => {
        decorateAnchors();
        decorateForms();
        decorateIframes();
        const contextReturn = document.getElementById('contextReturnUrl');
        if (contextReturn) {
            contextReturn.value = applyContextToUrl(window.location.href);
        }
    });

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[href]');
        if (!anchor) {
            return;
        }
        const href = anchor.getAttribute('href') || '';
        const decorated = applyContextToUrl(href);
        if (decorated && decorated !== href) {
            anchor.setAttribute('href', decorated);
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (form) {
            decorateForm(form);
        }
    }, true);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => decorateNode(node));
        });
    });

    if (document.documentElement) {
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();

(function TrainingScenarioPropagation() {
    const config = window.CBMSTrainingContext || {};
    if (!config.enabled || !String(config.scenarioId || '').trim()) {
        return;
    }

    const isInternalAppUrl = (url) => {
        return url.origin === window.location.origin
            && (url.pathname.endsWith('/index.php') || url.searchParams.has('route'));
    };

    const applyTrainingToUrl = (rawUrl) => {
        if (!rawUrl || rawUrl.startsWith('#') || rawUrl.startsWith('javascript:') || rawUrl.startsWith('mailto:') || rawUrl.startsWith('tel:')) {
            return rawUrl;
        }

        let url;
        try {
            url = new URL(rawUrl, window.location.href);
        } catch (error) {
            return rawUrl;
        }

        if (!isInternalAppUrl(url)) {
            return rawUrl;
        }

        if (!url.searchParams.has('training_scenario_id') && !url.searchParams.has('scenario_id')) {
            url.searchParams.set('training_scenario_id', String(config.scenarioId || ''));
        }

        return url.pathname + (url.search ? url.search : '') + (url.hash ? url.hash : '');
    };

    const upsertHidden = (form, name, value) => {
        let input = form.querySelector(`input[name="${name}"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    };

    const decorateAnchor = (anchor) => {
        const href = anchor.getAttribute('href') || '';
        const decorated = applyTrainingToUrl(href);
        if (decorated && decorated !== href) {
            anchor.setAttribute('href', decorated);
        }
    };

    const decorateForm = (form) => {
        const actionAttr = form.getAttribute('action') || window.location.href;
        let actionUrl;
        try {
            actionUrl = new URL(actionAttr, window.location.href);
        } catch (error) {
            return;
        }

        if (!isInternalAppUrl(actionUrl)) {
            return;
        }

        upsertHidden(form, 'training_scenario_id', String(config.scenarioId || ''));
    };

    const decoratePage = () => {
        document.querySelectorAll('a[href]').forEach((anchor) => decorateAnchor(anchor));
        document.querySelectorAll('form').forEach((form) => decorateForm(form));
    };

    const currentUrlWithTraining = applyTrainingToUrl(window.location.href);
    if (currentUrlWithTraining && currentUrlWithTraining !== (window.location.pathname + window.location.search + window.location.hash)) {
        window.history.replaceState({}, document.title, currentUrlWithTraining);
    }

    document.addEventListener('DOMContentLoaded', decoratePage);

    document.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[href]');
        if (anchor) {
            decorateAnchor(anchor);
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (form) {
            decorateForm(form);
        }
    }, true);
})();

window.addEventListener('error', function(e) { console.log('[WF] JS error:', e.message); });

document.addEventListener("DOMContentLoaded", () => {
    const DELAY_MS = 5000;
    document.querySelectorAll('.alert.auto-dismiss').forEach((el) => {
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const instance = bootstrap.Alert.getOrCreateInstance(el);
                instance.close();
            } else {
                el.classList.remove('show');
                setTimeout(() => el.remove(), 200);
            }
        }, DELAY_MS);
    });

});

(function () {
    const frame = document.getElementById('dataObjectPickerFrame');
    if (!frame) return;

    window.addEventListener('message', function (e) {
        try {
            const data = e.data || {};
            if (data && data.type === 'dataobject:selected' && data.code) {
                const scoped = window.CBMSWindowContext || {};
                const ret = encodeURIComponent(window.location.pathname + window.location.search + window.location.hash);
                const url = 'index.php?route=dataobjects/select'
                    + '&code=' + encodeURIComponent(data.code)
                    + (data.name ? '&name=' + encodeURIComponent(data.name) : '')
                    + '&link_context=1'
                    + '&fy=' + encodeURIComponent(String(scoped.fy || 0))
                    + '&ver=' + encodeURIComponent(String(scoped.ver || 0))
                    + '&scope_dataobject_code=' + encodeURIComponent(String(data.code || ''))
                    + (data.name ? '&scope_dataobject_name=' + encodeURIComponent(String(data.name)) : '')
                    + '&return=' + ret;
                window.location.href = url;
            }
        } catch (err) { }
    }, false);
})();
</script>
<?php if ($isLoggedIn): ?>
    <script>
    (function FiscalContextUI() {
        const form = document.getElementById('fiscalContextForm');
        const fyHidden = document.getElementById('FiscalYearID_hidden');
        const verHidden = document.getElementById('VersionID_hidden');
        const fyBtn = document.getElementById('fyDropdownBtn');
        const verBtn = document.getElementById('verDropdownBtn');
        const verMenu = document.getElementById('verDropdownMenu');

        if (!form || !fyHidden || !verHidden || !fyBtn || !verBtn || !verMenu) return;

        function rebuildVersionMenu(list, activeId) {
            verMenu.innerHTML = '';
            if (!list || list.length === 0) {
                verMenu.innerHTML = '<li><span class="dropdown-item disabled"><?= __t('no_results') ?></span></li>';
                return;
            }
            list.forEach(v => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item' + (String(v.VersionID) === String(activeId) ? ' active' : '');
                a.dataset.versionId = v.VersionID;
                a.textContent = v.VersionLabel || v.VersionID;
                li.appendChild(a);
                verMenu.appendChild(li);
            });
        }

        document.addEventListener('click', async (e) => {
            const a = e.target.closest('a[data-fy-id]');
            if (!a) return;
            e.preventDefault();

            const fyId = a.dataset.fyId;
            fyHidden.value = fyId;
            fyBtn.innerHTML = '<i class="bi bi-calendar3 me-1"></i>' + a.textContent;

            verMenu.innerHTML = '<li><span class="dropdown-item disabled"><?= __t('loading') ?></span></li>';
            verHidden.value = '';
            verBtn.innerHTML = '<i class="bi bi-layers me-1"></i><?= __t('version') ?>';

            try {
                let url = 'index.php?route=context/listVersions&FiscalYearID=' + encodeURIComponent(fyId);
                <?php if ($routeVersionTypeId !== null): ?>
                url += '&VersionTypeID=<?= (int) $routeVersionTypeId ?>';
                <?php endif; ?>
                const res = await fetch(url, { credentials: 'same-origin' });
                const data = await res.json();

                if (!data || data.length === 0) {
                    verMenu.innerHTML = '<li><span class="dropdown-item disabled"><?= __t('no_results') ?></span></li>';
                    return;
                }

                const def = data.find(v => String(v.IsDefault) === '1' || v.IsDefault === 1 || v.IsDefault === true) || data[0];
                verHidden.value = def.VersionID;
                verBtn.innerHTML = '<i class="bi bi-layers me-1"></i>' + (def.VersionLabel || def.VersionID);

                rebuildVersionMenu(data, def.VersionID);
                form.submit();
            } catch {
                verMenu.innerHTML = '<li><span class="dropdown-item disabled"><?= __t('error') ?></span></li>';
            }
        });

        document.addEventListener('click', (e) => {
            const a = e.target.closest('a[data-version-id]');
            if (!a) return;
            e.preventDefault();

            const verId = a.dataset.versionId;
            verHidden.value = verId;
            verBtn.innerHTML = '<i class="bi bi-layers me-1"></i>' + a.textContent;

            form.submit();
        });
    })();
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const helpBtn = document.getElementById("helpBtn");
        if (!helpBtn) return;

        helpBtn.addEventListener("click", async () => {
            const route = helpBtn.getAttribute("data-route");
            const bodyEl = document.getElementById("helpModalBody");
            const titleEl = document.getElementById("helpModalLabel");
            const helpLabel = <?= json_encode(__t('help')) ?>;
            const helpLoadFailedLabel = <?= json_encode(__t('help_load_failed')) ?>;

            bodyEl.innerHTML = `<div class="text-center my-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading…</span>
                </div>
            </div>`;

            try {
                const res = await fetch("index.php?route=help/show&screen=" + encodeURIComponent(route), { credentials: "same-origin" });
                const html = await res.text();
                bodyEl.innerHTML = html;
                if (titleEl) {
                    setTimeout(() => {
                        titleEl.textContent = '';
                        titleEl.insertAdjacentHTML('beforeend', `<i class="bi bi-question-circle me-2"></i>${helpLabel} - ${route}`);
                    }, 0);
                }
                if (titleEl) {
                    titleEl.innerHTML = `<i class="bi bi-question-circle me-2"></i> Help – ${route}`;
                }
            } catch (err) {
                console.error("Failed to load help", err);
                bodyEl.innerHTML = `<p class="text-danger">Failed to load help content.</p>`;
                const errorEl = bodyEl.querySelector(".text-danger");
                if (errorEl) {
                    errorEl.textContent = helpLoadFailedLabel;
                }
            }

            const modalEl = document.getElementById("helpModal");
            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
                    backdrop: "static",
                    keyboard: true
                });
                modal.show();
            }
        });
    });

    function printHelpContent() {
        const bodyEl = document.getElementById("helpModalBody");
        if (!bodyEl) return;

        const printWindow = window.open('', '_blank', 'width=900,height=650');
        if (!printWindow) return;

        const printHtml = [
            '<html>',
            '<head>',
            '<title>Help</title>',
            '<link href="assets/css/bootstrap.min.css" rel="stylesheet">',
            '<style>body { font-family: Arial, sans-serif; padding: 20px; }</style>',
            '</head>',
            '<body>',
            bodyEl.innerHTML,
            '</body>',
            '</html>'
        ].join('');

        printWindow.document.write(printHtml);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const openWindowBtn = document.getElementById("openWindowBtn");
        if (!openWindowBtn) return;

        openWindowBtn.addEventListener("click", () => {
            const newWindow = window.open(
                window.location.href,
                "_blank",
                "noopener,resizable=yes,scrollbars=yes,width=1400,height=900"
            );
            if (newWindow && typeof newWindow.focus === "function") {
                newWindow.focus();
            }
        });
    });
    </script>
<?php else: ?>
    <script>
    console.log("User not logged in, skipping FiscalContextUI");
    </script>
<?php endif; ?>
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true" aria-labelledby="helpModalLabel">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="helpModalLabel" class="modal-title">
                    <i class="bi bi-question-circle me-2"></i><?= __t('help') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __t('close') ?>"></button>
            </div>
            <div class="modal-body" id="helpModalBody">
                <div class="text-center text-muted">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading…</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i><?= __t('close') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="printHelpContent()">
                    <i class="bi bi-printer me-1"></i><?= __t('print') ?>
                </button>
            </div>
        </div>
    </div>
</div>
</body>
</html>
