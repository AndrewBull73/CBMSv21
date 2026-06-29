<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;
use App\Models\SystemSettingsModel;
use App\Models\FiscalContextModel;
use App\Models\StrategicBudgetingModel;
use App\Models\TrainingProgressModel;
use App\Core\Rbac;
use App\Shared\ScreenTestCatalog;
use App\Shared\TrainingScenarioCatalog;

require_once __DIR__ . '/../../shared/logger.php';
require_once __DIR__ . '/../../shared/lang.php';
require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/testing_features.php';
require_once __DIR__ . '/../../shared/training_features.php';

abstract class BaseController
{
    protected ?\PDO $db = null;
    protected array $acl = [];
    protected bool $requiresContext = false;

    public function __construct()
    {
        app_log("[SESSION DEBUG] BaseController construct", [
            'route' => $_GET['route'] ?? '',
            'session_id' => session_id(),
            'user_id' => (int)SessionHelper::get('auth.user_id', 0),
            'username' => (string)SessionHelper::get('auth.username', ''),
            'roles' => SessionHelper::get('auth.roles', []),
            'perms' => SessionHelper::get('auth.perms', []),
            'fiscalYearID' => (int)SessionHelper::get('FiscalYearID', 0),
            'versionID' => (int)SessionHelper::get('VersionID', 0)
        ], 'debug');

        SessionHelper::ensureSession();

        global $conn;
        $this->db = $conn ?? null;

        $route = $_GET['route'] ?? '';
        $isAuthRoute = str_starts_with($route, 'auth/login')
            || str_starts_with($route, 'auth/logout')
            || str_starts_with($route, 'auth/force')
            || str_starts_with($route, 'auth/refresh');
        $isIframe = !empty($_GET['iframe']);
        $skipJustLoggedInHome = $route === 'home/index'
            && SessionHelper::get('auth.just_logged_in')
            && !(bool)SessionHelper::get('auth.must_change_password', false);

        if (!$isAuthRoute && !$skipJustLoggedInHome) {
            $this->applyLinkedContextFromRequest();

            if ($this->db instanceof \PDO && !$isIframe) {
                $uid = (int)SessionHelper::get('auth.user_id', 0);
                if ($uid > 0) {
                    app_log("[SESSION DEBUG] Calling enforceActiveSession", ['user_id' => $uid, 'session_id' => session_id(), 'route' => $route], 'debug');
                    SessionHelper::enforceActiveSession($this->db);
                }
            }

            $this->enforceAcl();

            $userId = (int)SessionHelper::get('auth.user_id', 0);
            if ($this->requiresContext && $userId > 0) {
                $this->ensureContext();
            }

            if ($userId > 0) {
                $this->sessionHeartbeat();
            }

            $this->autoMaintenance();
        }

        if ($skipJustLoggedInHome) {
            SessionHelper::forget('auth.just_logged_in');
            session_write_close();
        }
    }

    protected function enforceAcl(): void
    {
        $route = $_GET['route'] ?? '';
        $segments = explode('/', $route);
        $action = $segments[1] ?? 'index';

        $rules = $this->acl[$action] ?? ($this->acl['*'] ?? []);
        $requiresAuth = $rules['auth'] ?? true;

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        app_log("enforceAcl", [
            'route' => $route,
            'action' => $action,
            'requiresAuth' => $requiresAuth,
            'userId' => $userId,
            'rolesAny' => $rules['rolesAny'] ?? [],
            'rolesAll' => $rules['rolesAll'] ?? [],
            'permsAny' => $rules['permsAny'] ?? [],
            'permsAll' => $rules['permsAll'] ?? [],
            'session_id' => session_id()
        ], 'debug');

        if ($requiresAuth && $userId <= 0) {
            app_log("enforceAcl: No user ID, redirecting to login", ['route' => $route, 'session_id' => session_id()], 'info');
            $this->flashError(__t('please_login'));
            $returnUrl = $this->buildCurrentReturnUrl();
            session_write_close();
            if (!empty($_GET['iframe'])) {
                $target = 'index.php?route=auth/loginForm';
                if ($returnUrl !== '') {
                    $target .= '&return=' . rawurlencode($returnUrl);
                }
                header('Content-Type: text/html; charset=UTF-8');
                echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirecting...</title></head><body>';
                echo '<script>window.top.location.href=' . json_encode($target, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ';</script>';
                echo '</body></html>';
                exit;
            }
            $location = 'index.php?route=auth/loginForm';
            if ($returnUrl !== '') {
                $location .= '&return=' . rawurlencode($returnUrl);
            }
            header('Location: ' . $location);
            exit;
        }

        if ($userId > 0) {
            $now = time();
            try {
                global $conn;
                $settings = new SystemSettingsModel($conn);
                $idleLimitSec = (int) $settings->get('SESSION_IDLE_TIMEOUT_SEC', '1200');
                $absoluteMin = (int) $settings->get('SESSION_ABSOLUTE_TIMEOUT_MIN', '600');
            } catch (\Throwable $e) {
                $idleLimitSec = 1200;
                $absoluteMin = 600;
                app_log("enforceAcl: Failed to load settings", ['error' => $e->getMessage(), 'session_id' => session_id()], 'warn');
            }

            $absoluteLimitSec = $absoluteMin * 60;
            $loginTime = (int) SessionHelper::get('auth.login_time', 0);
            $lastAct = (int) SessionHelper::get('auth.last_activity', 0);

            if ($loginTime > 0) {
                if (($now - $lastAct) > $idleLimitSec) {
                    app_log("enforceAcl: Session idle timeout", ['userId' => $userId, 'lastAct' => $lastAct, 'now' => $now, 'session_id' => session_id()], 'info');
                    header('Location: index.php?route=auth/logout&reason=idle');
                    exit;
                }
                if (($now - $loginTime) > $absoluteLimitSec) {
                    app_log("enforceAcl: Session absolute timeout", ['userId' => $userId, 'loginTime' => $loginTime, 'now' => $now, 'session_id' => session_id()], 'info');
                    header('Location: index.php?route=auth/logout&reason=absolute');
                    exit;
                }
            }

            $passwordChangeAllowedRoutes = ['auth/changePassword', 'auth/savePassword', 'auth/logout'];
            if ((bool)SessionHelper::get('auth.must_change_password', false) && !in_array($route, $passwordChangeAllowedRoutes, true)) {
                app_log('enforceAcl: password change required', [
                    'userId' => $userId,
                    'route' => $route,
                    'session_id' => session_id(),
                ], 'info');
                header('Location: index.php?route=auth/changePassword');
                exit;
            }

            SessionHelper::set('auth.last_activity', $now);
            try {
                global $conn;
                if ($conn instanceof \PDO) {
                    (new Rbac($conn))->loadForUser($userId);
                }
            } catch (\Throwable $e) {
                app_log('enforceAcl: Failed to refresh RBAC session permissions', [
                    'userId' => $userId,
                    'error' => $e->getMessage(),
                    'session_id' => session_id(),
                ], 'warn');
            }
            session_write_close();
        }

        if ($userId > 0) {
            $rbac = new Rbac($GLOBALS['conn'] ?? null);
            if (!empty($rules['rolesAny']) && !Rbac::hasAnyRole($rules['rolesAny'])) {
                $detail = 'Required one of roles: ' . implode(', ', $rules['rolesAny']);
                app_log("enforceAcl: Role denied", ['userId' => $userId, 'rolesAny' => $rules['rolesAny'], 'session_id' => session_id()], 'warn');
                $this->denyAccess($detail);
            }
            if (!empty($rules['rolesAll'])) {
                $hasAllRoles = true;
                foreach ($rules['rolesAll'] as $role) {
                    if (!Rbac::hasRole((string) $role)) {
                        $hasAllRoles = false;
                        break;
                    }
                }
                if (!$hasAllRoles) {
                    $detail = 'Required all roles: ' . implode(', ', $rules['rolesAll']);
                    app_log("enforceAcl: Role denied", ['userId' => $userId, 'rolesAll' => $rules['rolesAll'], 'session_id' => session_id()], 'warn');
                    $this->denyAccess($detail);
                }
            }
            if (!empty($rules['permsAny']) && !$rbac->canAny($rules['permsAny'])) {
                $detail = 'Missing one of: ' . implode(', ', $rules['permsAny']);
                app_log("enforceAcl: Permission denied", ['userId' => $userId, 'permsAny' => $rules['permsAny'], 'session_id' => session_id()], 'warn');
                $this->denyAccess($detail);
            }
            if (!empty($rules['permsAll']) && !$rbac->canAll($rules['permsAll'])) {
                $detail = 'Missing all of: ' . implode(', ', $rules['permsAll']);
                app_log("enforceAcl: Permission denied", ['userId' => $userId, 'permsAll' => $rules['permsAll'], 'session_id' => session_id()], 'warn');
                $this->denyAccess($detail);
            }
        }
    }

    protected function context(): array
    {
        return [
            'FiscalYearID' => (int) (SessionHelper::get('FiscalYearID') ?? 0),
            'VersionID' => (int) (SessionHelper::get('VersionID') ?? 0),
        ];
    }

    protected function auditEvent(string $action, string $entity, $entityKey = null, array $details = []): void
    {
        if (!$this->db instanceof \PDO) {
            return;
        }

        try {
            require_once __DIR__ . '/../Models/AuditModel.php';
            $audit = new \App\Models\AuditModel($this->db);
            $ok = $audit->insert([
                'UserID' => (int) SessionHelper::get('auth.user_id', 0) ?: null,
                'Username' => (string) SessionHelper::get('auth.username', 'guest'),
                'Action' => $action,
                'Entity' => $entity,
                'EntityKey' => $entityKey !== null ? (string) $entityKey : null,
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
                'Details' => $details,
            ]);

            if (!$ok) {
                app_log('auditEvent insert failed', [
                    'action' => $action,
                    'entity' => $entity,
                    'entityKey' => $entityKey,
                    'auditError' => $audit->getLastError(),
                ], 'error');
            }
        } catch (\Throwable $e) {
            app_log('auditEvent exception', [
                'action' => $action,
                'entity' => $entity,
                'entityKey' => $entityKey,
                'error' => $e->getMessage(),
            ], 'error');
        }
    }

    protected function logHandledException(string $message, \Throwable $e, array $context = []): void
    {
        app_log($message, $context + [
            'error' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 'error');
    }

    protected function assertPostWithCsrf(string $redirectUrl = 'index.php?route=home/index'): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            exit;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    protected function ensureContext(): void
    {
        $fy = (int) (SessionHelper::get('FiscalYearID') ?? 0);
        $ver = (int) (SessionHelper::get('VersionID') ?? 0);

        if ($fy > 0 && $ver > 0 && $this->isValidContext($fy, $ver)) {
            app_log('Fiscal context valid', ['fy' => $fy, 'ver' => $ver, 'session_id' => session_id()], 'debug');
            return;
        }

        global $conn;
        require_once __DIR__ . '/../Models/FiscalContextModel.php';
        require_once __DIR__ . '/../Models/SystemSettingsModel.php';

        $fc = new FiscalContextModel($conn);
        $ss = new SystemSettingsModel($conn);

        $defFy = (int) (
            $ss->get('DEFAULT_FISCAL_YEAR')
            ?? $ss->get('Default_Fiscal_Year')
            ?? 0
        );
        $defVer = (int) (
            $ss->get('DEFAULT_VERSION')
            ?? $ss->get('Default_Version')
            ?? 0
        );
        if ($defFy > 0 && $defVer > 0 && $this->isValidPair($fc, $defFy, $defVer)) {
            SessionHelper::set('FiscalYearID', $defFy);
            SessionHelper::set('VersionID', $defVer);
            app_log('Fiscal context set from system defaults', ['fy' => $defFy, 'ver' => $defVer, 'session_id' => session_id()], 'info');
            session_write_close();
            return;
        }

        $years = $fc->listFiscalYears();
        if (!empty($years)) {
            $bestFy = (int) $years[0]['FiscalYearID'];
            $vList = $fc->listVersions($bestFy);
            if (!empty($vList)) {
                $bestVer = (int) $vList[0]['VersionID'];
                SessionHelper::set('FiscalYearID', $bestFy);
                SessionHelper::set('VersionID', $bestVer);
                app_log('Fiscal context set from latest active FY/Version', ['fy' => $bestFy, 'ver' => $bestVer, 'session_id' => session_id()], 'info');
                session_write_close();
                return;
            }
        }

        app_log('No valid fiscal context available, setting defaults', ['session_id' => session_id()], 'warn');
        SessionHelper::set('FiscalYearID', 0);
        SessionHelper::set('VersionID', 0);
        session_write_close();
    }

    protected function isValidContext(int $fy, int $ver): bool
    {
        global $conn;
        require_once __DIR__ . '/../Models/FiscalContextModel.php';
        $fc = new FiscalContextModel($conn);
        return $this->isValidPair($fc, $fy, $ver);
    }

    private function isValidPair(FiscalContextModel $fc, int $fy, int $ver): bool
    {
        if ($fy <= 0 || $ver <= 0) {
            return false;
        }
        $versions = $fc->listVersions($fy);
        foreach ($versions as $row) {
            if ((int) $row['VersionID'] === $ver) {
                return true;
            }
        }
        return false;
    }

    protected function currentRoute(): string
    {
        return trim((string) ($_GET['route'] ?? 'home/index'));
    }

    protected function ensureScreenTestingEnabled(): void
    {
        if (screen_testing_features_enabled($this->db)) {
            return;
        }

        $this->flashError(__t('screen_tests_features_disabled'));
        header('Location: index.php?route=home/index');
        exit;
    }

    protected function buildRouteTrainingGuide(?string $route = null): ?array
    {
        if (!training_features_enabled($this->db)) {
            return null;
        }

        TrainingScenarioCatalog::setDb($this->db instanceof \PDO ? $this->db : null);

        $scenarioId = $this->resolveRequestedTrainingScenarioId();
        if ($scenarioId === '') {
            return null;
        }

        $state = $this->loadTrainingStateForScenario($scenarioId);
        if ($state === null) {
            return null;
        }

        $scenario = TrainingScenarioCatalog::get((string) ($state['scenario_id'] ?? ''));
        if ($scenario === null) {
            return null;
        }

        $step = TrainingScenarioCatalog::getStep($state);
        $isCompleted = (string) ($state['status'] ?? '') === 'completed';
        $targetRoute = trim((string) ($route ?? $this->currentRoute()));

        if (!$isCompleted) {
            $stepRoute = trim((string) ($step['route'] ?? ''));
            if ($stepRoute !== $targetRoute) {
                return null;
            }
        }

        $sampleKey = (string) ($step['sample_key'] ?? '');
        $sampleValue = $sampleKey !== '' ? (string) ($state['samples'][$sampleKey] ?? '') : '';

        return [
            'scenario' => $scenario,
            'state' => $state,
            'step' => $step,
            'isCompleted' => $isCompleted,
            'sampleValue' => $sampleValue,
            'completeUrl' => 'index.php?route=training/complete',
            'stuckUrl' => 'index.php?route=training/stuck',
            'runnerUrl' => TrainingScenarioCatalog::startRoute((string) ($state['scenario_id'] ?? '')),
            'stopUrl' => 'index.php?route=training/stop',
            'csrf' => csrf_token(),
        ];
    }

    protected function buildTrainingScreenHooks(string $view, string $route, ?array $trainingGuide): array
    {
        $state = is_array($trainingGuide['state'] ?? null) ? $trainingGuide['state'] : [];
        $scenario = is_array($trainingGuide['scenario'] ?? null) ? $trainingGuide['scenario'] : [];

        return [
            'route' => $route,
            'view' => $view,
            'scenarioId' => trim((string) ($state['scenario_id'] ?? '')),
            'currentStep' => (int) ($state['current_step'] ?? 0),
            'status' => trim((string) ($state['status'] ?? '')),
            'screenFamily' => trim((string) ($scenario['screen_family'] ?? '')),
        ];
    }

    private function resolveRequestedTrainingScenarioId(): string
    {
        $scenarioId = trim((string) ($_GET['training_scenario_id'] ?? $_GET['scenario_id'] ?? ''));
        if ($scenarioId !== '') {
            return $scenarioId;
        }

        $requested = trim((string) SessionHelper::get('training.requested_scenario_id', ''));
        if ($requested !== '') {
            return $requested;
        }

        $active = SessionHelper::get('training.active');
        if (is_array($active)) {
            return trim((string) ($active['scenario_id'] ?? ''));
        }

        return '';
    }

    private function loadTrainingStateForScenario(string $scenarioId): ?array
    {
        $scenarioId = trim($scenarioId);
        if ($scenarioId === '' || TrainingScenarioCatalog::get($scenarioId) === null) {
            return null;
        }

        $active = SessionHelper::get('training.active');
        if (is_array($active) && (string) ($active['scenario_id'] ?? '') === $scenarioId) {
            return $active;
        }

        if (!$this->db instanceof \PDO) {
            return null;
        }

        $userId = (int) SessionHelper::get('auth.user_id', 0);
        if ($userId <= 0) {
            return null;
        }

        $model = new TrainingProgressModel($this->db);
        if (!$model->supportsTrainingProgress()) {
            return null;
        }

        $state = $model->loadState($userId, $scenarioId);
        if (!is_array($state)) {
            return null;
        }

        SessionHelper::set('training.active', $state);
        SessionHelper::set('training.requested_scenario_id', $scenarioId);
        return $state;
    }

    private function standardizationSlugify(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = str_replace('&', ' and ', $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function standardizationNormalizeName(string $value): string
    {
        $value = str_replace('[]', '', $value);
        $value = str_replace(['[', ']'], ['-', ''], $value);
        return $value;
    }

    private function standardizationMakeUniqueId(string $base, array &$knownIds, array &$generatedCounters): string
    {
        $normalized = $this->standardizationSlugify($base);
        if ($normalized === '') {
            return '';
        }

        if (!isset($knownIds[$normalized])) {
            $knownIds[$normalized] = true;
            return $normalized;
        }

        $next = (int) ($generatedCounters[$normalized] ?? 2);
        $candidate = $normalized . '-' . $next;
        while (isset($knownIds[$candidate])) {
            $next += 1;
            $candidate = $normalized . '-' . $next;
        }

        $generatedCounters[$normalized] = $next + 1;
        $knownIds[$candidate] = true;
        return $candidate;
    }

    private function standardizationAssignId(
        \DOMElement $element,
        string $base,
        array &$knownIds,
        array &$generatedCounters,
        string $role = ''
    ): string {
        $existing = trim($element->getAttribute('id'));
        if ($existing !== '') {
            $knownIds[$existing] = true;
            if ($role !== '' && !$element->hasAttribute('data-cbms-standard-role')) {
                $element->setAttribute('data-cbms-standard-role', $role);
            }
            return $existing;
        }

        $nextId = $this->standardizationMakeUniqueId($base, $knownIds, $generatedCounters);
        if ($nextId === '') {
            return '';
        }

        $element->setAttribute('id', $nextId);
        $element->setAttribute('data-cbms-generated-id', '1');
        $element->setAttribute('data-training-generated-id', '1');
        if ($role !== '') {
            $element->setAttribute('data-cbms-standard-role', $role);
        }

        $tagName = strtolower($element->tagName);
        if (in_array($tagName, ['input', 'select', 'textarea'], true)) {
            $parent = $element->parentNode;
            if ($parent instanceof \DOMElement) {
                foreach ($parent->getElementsByTagName('label') as $label) {
                    if (!$label instanceof \DOMElement) {
                        continue;
                    }
                    $labelFor = trim($label->getAttribute('for'));
                    if ($labelFor === '') {
                        $label->setAttribute('for', $nextId);
                        break;
                    }
                }
            }
        }

        return $nextId;
    }

    private function standardizationExtractRouteAction(string $rawUrl): string
    {
        $rawUrl = trim($rawUrl);
        if ($rawUrl === '') {
            return '';
        }

        $query = (string) parse_url(html_entity_decode($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_QUERY);
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);
        return $this->standardizationSlugify((string) ($params['route'] ?? ''));
    }

    private function standardizationExtractRecordToken(\DOMElement $element): string
    {
        $ignoredKeys = [
            'route' => true,
            'return' => true,
            'fy' => true,
            'ver' => true,
            'page' => true,
            'link_context' => true,
            'training_scenario_id' => true,
            'scenario_id' => true,
        ];

        $fromUrl = function (string $rawUrl) use ($ignoredKeys): string {
            $query = (string) parse_url(html_entity_decode($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_QUERY);
            if ($query === '') {
                return '';
            }

            parse_str($query, $params);
            foreach ($params as $key => $value) {
                $normalizedKey = strtolower(trim((string) $key));
                if (isset($ignoredKeys[$normalizedKey])) {
                    continue;
                }
                if (!preg_match('/(^id$|_id$|id$)/i', $normalizedKey)) {
                    continue;
                }

                $token = $this->standardizationSlugify(is_array($value) ? implode('-', $value) : (string) $value);
                if ($token !== '' && $token !== '0') {
                    return $token;
                }
            }

            return '';
        };

        if (strtolower($element->tagName) === 'a') {
            return $fromUrl($element->getAttribute('href'));
        }

        if (strtolower($element->tagName) === 'form') {
            foreach ($element->getElementsByTagName('input') as $field) {
                if (!$field instanceof \DOMElement) {
                    continue;
                }
                if (strtolower($field->getAttribute('type')) !== 'hidden') {
                    continue;
                }

                $fieldName = trim($field->getAttribute('name'));
                if (!preg_match('/(^id$|_id$|id$)/i', $fieldName)) {
                    continue;
                }

                $token = $this->standardizationSlugify($field->getAttribute('value'));
                if ($token !== '' && $token !== '0') {
                    return $token;
                }
            }

            return $fromUrl($element->getAttribute('action'));
        }

        $parent = $element->parentNode;
        while ($parent instanceof \DOMElement) {
            if (strtolower($parent->tagName) === 'form') {
                return $this->standardizationExtractRecordToken($parent);
            }
            $parent = $parent->parentNode;
        }

        return '';
    }

    private function standardizationInferActionIntent(\DOMElement $element): string
    {
        $tagName = strtolower($element->tagName);
        $dataBsTarget = trim($element->getAttribute('data-bs-target'));
        $role = strtolower(trim($element->getAttribute('role')));
        if ($dataBsTarget !== '' || $role === 'tab') {
            $target = $this->standardizationSlugify(ltrim($dataBsTarget, '#'));
            return $target !== '' ? $target . '-tab' : 'tab';
        }

        $visibleText = $this->standardizationSlugify((string) $element->textContent);
        $title = $this->standardizationSlugify($element->getAttribute('title'));
        $name = $this->standardizationSlugify($element->getAttribute('name'));
        $value = $this->standardizationSlugify($element->getAttribute('value'));
        $actionUrl = $tagName === 'a'
            ? $element->getAttribute('href')
            : $element->getAttribute('formaction');

        if ($actionUrl === '') {
            $parent = $element->parentNode;
            while ($parent instanceof \DOMElement) {
                if (strtolower($parent->tagName) === 'form') {
                    $actionUrl = $parent->getAttribute('action');
                    break;
                }
                $parent = $parent->parentNode;
            }
        }

        $routeAction = $this->standardizationExtractRouteAction($actionUrl);
        $source = trim(implode(' ', array_filter([$visibleText, $title, $name, $value, $routeAction], static fn($item): bool => $item !== '')));
        if ($source === '') {
            return '';
        }

        $checks = [
            'save-roles-btn' => '/save-roles|save roles/',
            'export-pdf-btn' => '/export-pdf|export pdf|pdf/',
            'export-excel-btn' => '/export-excel|export excel|excel/',
            'upload-btn' => '/upload/',
            'download-btn' => '/download/',
            'view-usage-btn' => '/project-usage|view-usage|open-full-usage|usage/',
            'create-btn' => '/create|new /',
            'add-line-btn' => '/add-line|add line/',
            'add-funding-btn' => '/add-funding|add funding/',
            'add-btn' => '/\badd\b/',
            'filter-btn' => '/filter/',
            'reset-btn' => '/reset|clear/',
            'back-btn' => '/\bback\b/',
            'open-btn' => '/\bopen\b/',
            'edit-btn' => '/\bedit\b/',
            'submit-btn' => '/submit/',
            'forward-btn' => '/forward/',
            'return-btn' => '/return/',
            'approve-btn' => '/approve/',
            'cancel-btn' => '/cancel/',
            'remove-btn' => '/remove/',
            'delete-btn' => '/delete|archive/',
            'resume-btn' => '/resume/',
            'start-btn' => '/start/',
            'view-btn' => '/\bview\b/',
            'save-btn' => '/save/',
        ];

        foreach ($checks as $intent => $pattern) {
            if (preg_match($pattern, $source) === 1) {
                return $intent;
            }
        }

        if ($routeAction !== '') {
            return $routeAction . ($tagName === 'a' ? '-link' : '-btn');
        }

        if ($visibleText !== '') {
            return $visibleText . ($tagName === 'a' ? '-link' : '-btn');
        }

        return $tagName === 'a' ? 'action-link' : 'action-btn';
    }

    private function standardizationShouldSkipTranslationNode(\DOMNode $node): bool
    {
        $current = $node instanceof \DOMElement ? $node : $node->parentNode;
        while ($current instanceof \DOMElement) {
            $tagName = strtolower($current->tagName);
            if (in_array($tagName, ['script', 'style', 'code', 'pre', 'textarea', 'svg', 'noscript'], true)) {
                return true;
            }

            $translateAttr = strtolower(trim($current->getAttribute('translate')));
            if ($translateAttr === 'no' || $current->hasAttribute('data-cbms-no-translate')) {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
    }

    private function standardizationTranslateLiteralPreserveWhitespace(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }

        $leading = preg_match('/^\s+/u', $text, $matches) === 1 ? $matches[0] : '';
        $trailing = preg_match('/\s+$/u', $text, $matches) === 1 ? $matches[0] : '';
        $core = trim($text);
        $translated = \App\Shared\Lang::translateLiteral($core);

        if ($translated === $core) {
            $normalizedCore = trim((string) (preg_replace('/\s+/u', ' ', $core) ?? $core));
            if ($normalizedCore !== '' && $normalizedCore !== $core) {
                $normalizedTranslation = \App\Shared\Lang::translateLiteral($normalizedCore);
                if ($normalizedTranslation !== $normalizedCore) {
                    $translated = $normalizedTranslation;
                }
            }
        }

        if ($translated === $core) {
            return $text;
        }

        return $leading . $translated . $trailing;
    }

    private function standardizationShouldTranslateAttribute(\DOMElement $element, string $attributeName): bool
    {
        $attribute = strtolower($attributeName);
        if (in_array($attribute, ['placeholder', 'title', 'aria-label', 'alt', 'data-bs-title', 'data-bs-original-title'], true)) {
            return true;
        }

        if ($attribute !== 'value') {
            return false;
        }

        $tagName = strtolower($element->tagName);
        if ($tagName !== 'input') {
            return false;
        }

        $inputType = strtolower(trim($element->getAttribute('type')));
        return in_array($inputType, ['button', 'submit', 'reset'], true);
    }

    private function standardizationTranslateDom(\DOMXPath $xpath, \DOMNode $scopeNode): void
    {
        foreach ($xpath->query('.//text()[normalize-space(.) != ""]', $scopeNode) as $textNode) {
            if (!$textNode instanceof \DOMText || $this->standardizationShouldSkipTranslationNode($textNode)) {
                continue;
            }

            $translated = $this->standardizationTranslateLiteralPreserveWhitespace($textNode->nodeValue);
            if ($translated !== $textNode->nodeValue) {
                $textNode->nodeValue = $translated;
            }
        }

        foreach ($xpath->query('.//*[@placeholder or @title or @aria-label or @alt or @data-bs-title or @data-bs-original-title or @value]', $scopeNode) as $node) {
            if (!$node instanceof \DOMElement || $this->standardizationShouldSkipTranslationNode($node)) {
                continue;
            }

            foreach (['placeholder', 'title', 'aria-label', 'alt', 'data-bs-title', 'data-bs-original-title', 'value'] as $attributeName) {
                if (!$node->hasAttribute($attributeName) || !$this->standardizationShouldTranslateAttribute($node, $attributeName)) {
                    continue;
                }

                $originalValue = $node->getAttribute($attributeName);
                $translatedValue = $this->standardizationTranslateLiteralPreserveWhitespace($originalValue);
                if ($translatedValue !== $originalValue) {
                    $node->setAttribute($attributeName, $translatedValue);
                }
            }
        }
    }

    private function standardizeRenderedHtml(string $html, string $view, string $route = ''): string
    {
        if (trim($html) === '' || !class_exists(\DOMDocument::class)) {
            return $html;
        }

        [$html, $protectedBlocks] = $this->standardizationProtectRawTextBlocks($html);

        $routeKey = $this->standardizationSlugify($route !== '' ? $route : $view);
        if ($routeKey === '') {
            $routeKey = 'screen';
        }

        $isFullDocument = stripos($html, '<html') !== false || stripos($html, '<!doctype') !== false;
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrappedHtml = $isFullDocument
            ? $html
            : '<div id="__cbms-standard-root">' . $html . '</div>';

        $previous = libxml_use_internal_errors(true);
        $loadFlags = $isFullDocument
            ? LIBXML_NOERROR | LIBXML_NOWARNING
            : LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING;

        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrappedHtml, $loadFlags);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded !== true) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $scopeNode = $isFullDocument
            ? $dom->documentElement
            : $xpath->query('//*[@id="__cbms-standard-root"]')->item(0);

        if (!$scopeNode instanceof \DOMNode) {
            return $html;
        }

        $knownIds = [];
        foreach ($xpath->query('.//*[@id]', $scopeNode) as $node) {
            if ($node instanceof \DOMElement) {
                $id = trim($node->getAttribute('id'));
                if ($id !== '') {
                    $knownIds[$id] = true;
                }
            }
        }
        $generatedCounters = [];

        foreach ($xpath->query('.//form', $scopeNode) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            if (trim($node->getAttribute('id')) !== '') {
                continue;
            }

            $routeAction = $this->standardizationExtractRouteAction($node->getAttribute('action'));
            if ($routeAction === '') {
                $routeAction = 'form';
            }
            $token = $this->standardizationExtractRecordToken($node);
            $suffix = $token !== '' ? '-' . $token : '';
            $this->standardizationAssignId($node, $routeKey . '-' . $routeAction . '-form' . $suffix, $knownIds, $generatedCounters, 'form');
        }

        foreach ($xpath->query('.//input | .//select | .//textarea', $scopeNode) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            if (trim($node->getAttribute('id')) !== '') {
                continue;
            }
            if (strtolower($node->tagName) === 'input' && strtolower($node->getAttribute('type')) === 'hidden') {
                continue;
            }

            $fieldName = trim($node->getAttribute('name'));
            $placeholder = strtolower(trim($node->getAttribute('placeholder')));
            $isSearchField = in_array(strtolower($fieldName), ['q', 'query', 'search'], true)
                || str_contains($placeholder, 'search');

            if ($isSearchField) {
                $this->standardizationAssignId($node, $routeKey . '-search-input', $knownIds, $generatedCounters, 'field');
                continue;
            }

            if ($fieldName !== '') {
                $this->standardizationAssignId(
                    $node,
                    $routeKey . '-' . $this->standardizationNormalizeName($fieldName),
                    $knownIds,
                    $generatedCounters,
                    'field'
                );
                continue;
            }

            $this->standardizationAssignId($node, $routeKey . '-' . strtolower($node->tagName), $knownIds, $generatedCounters, 'field');
        }

        foreach ($xpath->query('.//table', $scopeNode) as $node) {
            if ($node instanceof \DOMElement && trim($node->getAttribute('id')) === '') {
                $this->standardizationAssignId($node, $routeKey . '-table', $knownIds, $generatedCounters, 'table');
            }
        }

        foreach ($xpath->query('.//button | .//a[@href]', $scopeNode) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            if (trim($node->getAttribute('id')) !== '') {
                continue;
            }

            $tagName = strtolower($node->tagName);
            $className = ' ' . strtolower(trim($node->getAttribute('class'))) . ' ';
            $routeAction = $this->standardizationExtractRouteAction($tagName === 'a' ? $node->getAttribute('href') : $node->getAttribute('formaction'));
            $intent = $this->standardizationInferActionIntent($node);

            if ($intent === '' && $routeAction !== '') {
                $intent = $routeAction . ($tagName === 'a' ? '-link' : '-btn');
            }

            if ($intent === '' && $tagName === 'a' && str_contains($className, ' btn ')) {
                $intent = 'action-link';
            }

            if ($intent === '' && $tagName === 'button') {
                $intent = 'action-btn';
            }

            if ($intent === '') {
                continue;
            }

            $token = $this->standardizationExtractRecordToken($node);
            $suffix = $token !== '' ? '-' . $token : '';
            $this->standardizationAssignId($node, $routeKey . '-' . $intent . $suffix, $knownIds, $generatedCounters, 'action');
        }

        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " alert ") or contains(concat(" ", normalize-space(@class), " "), " modal ") or contains(concat(" ", normalize-space(@class), " "), " card ")]', $scopeNode) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            if (trim($node->getAttribute('id')) !== '') {
                continue;
            }

            $className = ' ' . strtolower(trim($node->getAttribute('class'))) . ' ';
            $roleBase = str_contains($className, ' modal ')
                ? 'modal'
                : (str_contains($className, ' alert ') ? 'alert' : 'card');
            $heading = '';
            foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $headingTag) {
                $headingNode = $node->getElementsByTagName($headingTag)->item(0);
                if ($headingNode instanceof \DOMNode) {
                    $heading = $this->standardizationSlugify((string) $headingNode->textContent);
                    if ($heading !== '') {
                        break;
                    }
                }
            }

            $base = $routeKey . '-' . ($heading !== '' ? $heading . '-' : '') . $roleBase;
            $this->standardizationAssignId($node, $base, $knownIds, $generatedCounters, 'section');
        }

        $this->standardizationTranslateDom($xpath, $scopeNode);

        if ($isFullDocument && $dom->documentElement instanceof \DOMElement && strtolower($dom->documentElement->tagName) === 'html') {
            $dom->documentElement->setAttribute('lang', \App\Shared\Lang::getActiveLang());
        }

        if ($isFullDocument) {
            $output = $dom->saveHTML();
            $output = preg_replace('/^<\?xml.+?\?>/i', '', $output ?? '') ?? $html;
            return $this->standardizationRestoreRawTextBlocks($output, $protectedBlocks);
        }

        $output = '';
        foreach ($scopeNode->childNodes as $childNode) {
            $output .= $dom->saveHTML($childNode);
        }

        $output = preg_replace('/^<\?xml.+?\?>/i', '', $output ?? '') ?? '';
        $output = $output !== '' ? $output : $html;
        return $this->standardizationRestoreRawTextBlocks($output, $protectedBlocks);
    }

    /**
     * DOMDocument can rewrite script/style raw text in ways that leak code into the page.
     * Protect those inner blocks before standardization and restore them afterward.
     *
     * @return array{0:string,1:array<string,string>}
     */
    private function standardizationProtectRawTextBlocks(string $html): array
    {
        $protected = [];
        $patterns = [
            '/(<script\b[^>]*>)(.*?)(<\/script>)/is',
            '/(<style\b[^>]*>)(.*?)(<\/style>)/is',
        ];

        foreach ($patterns as $pattern) {
            $html = preg_replace_callback(
                $pattern,
                static function (array $matches) use (&$protected): string {
                    $token = '__CBMS_RAW_BLOCK_' . count($protected) . '__';
                    $protected[$token] = (string) ($matches[2] ?? '');
                    return (string) ($matches[1] ?? '') . $token . (string) ($matches[3] ?? '');
                },
                $html
            ) ?? $html;
        }

        return [$html, $protected];
    }

    private function standardizationRestoreRawTextBlocks(string $html, array $protected): string
    {
        if ($protected === []) {
            return $html;
        }

        return strtr($html, $protected);
    }

    protected function render(string $view, array $vars = []): void
    {
        $start = microtime(true);
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        $layout = __DIR__ . '/../Views/layouts/main.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($viewFile, ENT_QUOTES, 'UTF-8');
            app_log("Render failed: View not found", ['view' => $viewFile, 'session_id' => session_id()], 'error');
            return;
        }

        $flash = \App\Shared\SessionHelper::get('flash.message', null);
        $route = $this->currentRoute();
        $useMainLayout = $view !== 'auth/login' && is_file($layout);
        $allowInlineFlash = (bool) ($vars['allowInlineFlash'] ?? false);
        // Standardize flash handling: full-page screens render session flash once in the shared layout.
        $inlineFlash = $useMainLayout && !$allowInlineFlash
            ? null
            : ($vars['flash'] ?? $flash);
        $screenTestingEnabled = screen_testing_features_enabled($this->db);
        $trainingEnabled = training_features_enabled($this->db);
        if (!array_key_exists('screenTestingEnabled', $vars)) {
            $vars['screenTestingEnabled'] = $screenTestingEnabled;
        }
        if (!array_key_exists('screenTestLauncher', $vars)) {
            $vars['screenTestLauncher'] = $screenTestingEnabled ? $this->buildScreenTestLauncher($route) : null;
        }
        if (!array_key_exists('trainingEnabled', $vars)) {
            $vars['trainingEnabled'] = $trainingEnabled;
        }
        if (!array_key_exists('trainingGuide', $vars)) {
            $vars['trainingGuide'] = $trainingEnabled ? $this->buildRouteTrainingGuide($route) : null;
        }
        if (!array_key_exists('trainingScreenHooks', $vars)) {
            $vars['trainingScreenHooks'] = $this->buildTrainingScreenHooks(
                $view,
                $route,
                is_array($vars['trainingGuide'] ?? null) ? $vars['trainingGuide'] : null
            );
        }
        $vars = array_merge([
            'flash' => $inlineFlash,
            'layoutFlash' => $flash,
            '_ctx' => $this->context(),
            'userId' => (int) SessionHelper::get('auth.user_id', 0),
            'username' => (string) SessionHelper::get('auth.username', ''),
            'sessionRoles' => SessionHelper::get('auth.roles', []),
            'perms' => SessionHelper::get('auth.perms', [])
        ], $vars);

        if ($flash !== null) {
            register_shutdown_function(function (): void {
                \App\Shared\SessionHelper::forget('flash.message');
                if (isset($_SESSION['flash']['message'])) {
                    unset($_SESSION['flash']['message']);
                }
            });
        }

        error_log('BaseController::render vars: ' . print_r($vars, true));
        ob_start();
        extract($vars, EXTR_OVERWRITE);
        require $viewFile;
        $content = ob_get_clean();

        $output = $content;
        if ($view !== 'auth/login' && $content !== '') {
            if (is_file($layout)) {
                ob_start();
                require $layout;
                $output = ob_get_clean();
            }
        }

        echo $this->standardizeRenderedHtml((string) $output, $view, $route);

        if ($flash !== null) {
            \App\Shared\SessionHelper::forget('flash.message');
            if (isset($_SESSION['flash']['message'])) {
                unset($_SESSION['flash']['message']);
            }
        }

        $durationMs = round((microtime(true) - $start) * 1000, 2);
        $threshold = 500;
        try {
            global $conn;
            if ($conn instanceof \PDO) {
                $settings = new SystemSettingsModel($conn);
                $threshold = (int) $settings->get('SLOW_REQUEST_THRESHOLD_MS', (string) $threshold);
            }
        } catch (\Throwable $e) { }

        $level = ($durationMs >= $threshold) ? 'warn' : 'debug';
        app_log('Render complete', [
            'controller' => static::class,
            'action' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown',
            'view' => $view,
            'time_ms' => $durationMs,
            'threshold' => $threshold,
            'route' => $_GET['route'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'session_id' => session_id(),
            'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
            'fy' => (int) (SessionHelper::get('FiscalYearID') ?? 0),
            'ver' => (int) (SessionHelper::get('VersionID') ?? 0),
        ], $level);
    }

    private function buildScreenTestLauncher(string $route): ?array
    {
        $route = trim($route);
        if ($route === '' || str_starts_with($route, 'auth/') || str_starts_with($route, 'screen-tests/')) {
            return null;
        }

        $scenario = ScreenTestCatalog::firstForTargetRoute($route);
        if (is_array($scenario)) {
            $scenarioId = trim((string) ($scenario['id'] ?? ''));
            if ($scenarioId !== '') {
                return [
                    'url' => 'index.php?' . http_build_query([
                        'route' => 'screen-tests/runner',
                        'scenario_id' => $scenarioId,
                    ]),
                    'scenarioId' => $scenarioId,
                    'scenarioTitle' => trim((string) ($scenario['title'] ?? $scenarioId)),
                    'route' => $route,
                ];
            }
        }

        return [
            'url' => 'index.php?' . http_build_query([
                'route' => 'screen-tests/scenarios',
                'q' => $route,
            ]),
            'scenarioId' => '',
            'scenarioTitle' => '',
            'route' => $route,
        ];
    }

    protected function renderPartial(string $view, array $params = []): void
    {
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($viewFile, ENT_QUOTES, 'UTF-8');
            app_log("RenderPartial failed: View not found", ['view' => $viewFile, 'session_id' => session_id()], 'error');
            return;
        }

        $flash = \App\Shared\SessionHelper::get('flash.message', null);
        $params = array_merge([
            'flash' => $flash,
            '_ctx' => $this->context(),
            'userId' => (int) SessionHelper::get('auth.user_id', 0),
            'username' => (string) SessionHelper::get('auth.username', ''),
            'sessionRoles' => SessionHelper::get('auth.roles', []),
            'perms' => SessionHelper::get('auth.perms', [])
        ], $params);

        error_log('BaseController::renderPartial params: ' . print_r($params, true));
        ob_start();
        extract($params, EXTR_OVERWRITE);
        require $viewFile;
        $content = (string) ob_get_clean();
        echo $this->standardizeRenderedHtml($content, $view, $this->currentRoute());

        if ($flash !== null) {
            \App\Shared\SessionHelper::forget('flash.message');
            if (isset($_SESSION['flash']['message'])) {
                unset($_SESSION['flash']['message']);
            }
        }
    }

    protected function denyAccess(string $detail = ''): void
    {
        $payload = $this->buildAccessDeniedPayload($detail);
        $route = (string)($_GET['route'] ?? '');
        $isIframe = !empty($_GET['iframe']);

        app_log("denyAccess triggered", ['detail' => $detail, 'route' => $route, 'session_id' => session_id()], 'warn');
        http_response_code(403);

        if (!$isIframe && $this->db instanceof \PDO) {
            $this->auditEvent('ACCESS_DENIED', 'ROUTE', $route, [
                'title' => $payload['title'],
                'message' => $payload['text'],
                'requirement' => $payload['requirement'],
                'missingPermissions' => $payload['missingPermissions'],
                'route' => $route,
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                'iframe' => false,
            ]);
        }

        if ($isIframe) {
            session_write_close();
            $this->renderPartial('shared/AccessDeniedNotice', [
                'noticeTitle' => $payload['title'],
                'noticeText' => $payload['text'],
                'noticeRequirement' => $payload['requirement'],
                'noticeMissingPermissions' => $payload['missingPermissions'],
                'noticeVariant' => 'compact',
            ]);
            exit;
        }

        \App\Shared\SessionHelper::set('flash.message', [
            'type' => 'warning',
            'accessDenied' => true,
            'title' => $payload['title'],
            'text' => $payload['text'],
            'detail' => $payload['requirement'],
            'missingPermissions' => $payload['missingPermissions'],
        ]);
        session_write_close();
        header('Location: index.php?route=home/index');
        exit;
    }

    protected function renderAccessDeniedNotice(string $detail = '', int $statusCode = 403, string $variant = 'default'): void
    {
        $payload = $this->buildAccessDeniedPayload($detail);
        http_response_code($statusCode);
        $this->renderPartial('shared/AccessDeniedNotice', [
            'noticeTitle' => $payload['title'],
            'noticeText' => $payload['text'],
            'noticeRequirement' => $payload['requirement'],
            'noticeMissingPermissions' => $payload['missingPermissions'],
            'noticeVariant' => $variant,
        ]);
    }

    protected function buildAccessDeniedPayload(string $detail = ''): array
    {
        $title = 'Access Restricted';
        $route = trim((string) ($_GET['route'] ?? ''));
        $screen = $this->friendlyRouteName($route);
        $text = $screen !== ''
            ? 'Your account does not include the access needed for ' . $screen . '.'
            : 'Your account does not include the access needed for this screen or function.';
        $requirement = '';
        $missingPermissions = [];
        $guidance = 'If this access is required for your work, ask a system administrator to update your assigned roles.';

        if ($detail !== '') {
            if (preg_match('/^Missing one of:\s*(.+)$/i', $detail, $m)) {
                $perms = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
                if ($perms !== []) {
                    $missingPermissions = $perms;
                    $requirement = 'Required access: ' . $this->formatAccessList($perms, 'or');
                }
            } elseif (preg_match('/^Missing all of:\s*(.+)$/i', $detail, $m)) {
                $perms = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
                if ($perms !== []) {
                    $missingPermissions = $perms;
                    $requirement = 'Required access: ' . $this->formatAccessList($perms, 'and');
                }
            } elseif (preg_match('/^Required one of roles:\s*(.+)$/i', $detail, $m)) {
                $roles = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
                if ($roles !== []) {
                    $requirement = 'Required role: ' . $this->formatTextList($roles, 'or');
                }
            } elseif (preg_match('/^Required all roles:\s*(.+)$/i', $detail, $m)) {
                $roles = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
                if ($roles !== []) {
                    $requirement = 'Required roles: ' . $this->formatTextList($roles, 'and');
                }
            } else {
                $requirement = $detail;
            }
        }

        if ($requirement !== '') {
            $requirement .= '. ' . $guidance;
        } else {
            $requirement = $guidance;
        }

        return [
            'title' => $title,
            'text' => $text,
            'requirement' => $requirement,
            'missingPermissions' => array_values(array_unique(array_filter(array_map(
                static fn ($code): string => strtoupper(trim((string) $code)),
                $missingPermissions
            )))),
        ];
    }

    protected function friendlyRouteName(string $route): string
    {
        $route = trim($route);
        if ($route === '') {
            return '';
        }

        $label = $this->findMenuLabelForRoute($route);
        if ($label !== '') {
            return $label;
        }

        $path = preg_replace('/[^A-Za-z0-9\/_-]+/', ' ', $route) ?: $route;
        $parts = array_filter(preg_split('/[\/_-]+/', $path) ?: []);
        $words = array_map(static fn (string $part): string => ucfirst(strtolower($part)), $parts);
        return implode(' ', $words);
    }

    protected function findMenuLabelForRoute(string $route): string
    {
        $menuFile = __DIR__ . '/../../config/menu.php';
        if (!is_file($menuFile)) {
            return '';
        }

        try {
            $items = require $menuFile;
        } catch (\Throwable $e) {
            return '';
        }

        $label = $this->findMenuLabelInItems(is_array($items) ? $items : [], $route);
        return $label !== '' ? $label : '';
    }

    protected function findMenuLabelInItems(array $items, string $route): string
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemRoute = trim((string) ($item['route'] ?? ''));
            if ($itemRoute !== '' && $itemRoute === $route) {
                return trim((string) ($item['label'] ?? ''));
            }
            $active = is_array($item['active'] ?? null) ? $item['active'] : [];
            foreach ($active as $pattern) {
                $pattern = trim((string) $pattern);
                if ($pattern !== '' && $this->routeMatchesPattern($route, $pattern)) {
                    return trim((string) ($item['label'] ?? ''));
                }
            }
            $childLabel = $this->findMenuLabelInItems(is_array($item['children'] ?? null) ? $item['children'] : [], $route);
            if ($childLabel !== '') {
                return $childLabel;
            }
        }

        return '';
    }

    protected function routeMatchesPattern(string $route, string $pattern): bool
    {
        if ($route === $pattern) {
            return true;
        }
        if (!str_contains($pattern, '*')) {
            return false;
        }
        $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
        return (bool) preg_match($regex, $route);
    }

    protected function formatAccessList(array $codes, string $joinWord): string
    {
        $labels = [];
        foreach ($codes as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }
            $labels[] = $this->friendlyPermissionName($code);
        }

        return $this->formatTextList(array_values(array_unique($labels)), $joinWord);
    }

    protected function formatTextList(array $items, string $joinWord): string
    {
        $items = array_values(array_filter(array_map('trim', array_map('strval', $items))));
        $count = count($items);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $items[0];
        }
        if ($count === 2) {
            return $items[0] . ' ' . $joinWord . ' ' . $items[1];
        }

        $last = array_pop($items);
        return implode(', ', $items) . ', ' . $joinWord . ' ' . $last;
    }

    protected function friendlyPermissionName(string $code): string
    {
        $labels = [
            'ADMIN_ALL' => 'Super Administrator access',
            'SYSADMIN' => 'System Administrator access',
            'BASE_CONFIG_VIEW' => 'Base Configuration view access',
            'BASE_CONFIG_EDIT' => 'Base Configuration edit access',
            'FIN_CONFIG_VIEW' => 'Financial Configuration view access',
            'FIN_CONFIG_EDIT' => 'Financial Configuration edit access',
            'CALC_ADMIN' => 'Calculation Administration access',
            'ESTIMATES_VIEW' => 'Budget Planning view access',
            'ESTIMATES_EDIT' => 'Budget Planning edit access',
            'RATES_VIEW' => 'Rates view access',
            'RATES_EDIT' => 'Rates edit access',
            'RATES_CREATE' => 'Rates create access',
            'STRATEGY_VIEW' => 'Budget Strategy view access',
            'STRATEGY_CONFIG_EDIT' => 'Strategy Configuration access',
            'STRATEGY_SETUP_EDIT' => 'Strategy Setup access',
            'STRATEGY_PERFORMANCE_EDIT' => 'Strategy Performance access',
            'STRATEGY_DELIVERY_EDIT' => 'Strategy Delivery access',
            'STRATEGY_GOVERNANCE_EDIT' => 'Strategy Governance access',
            'STRATEGY_FISCAL_EDIT' => 'Strategy Fiscal access',
            'STRATEGY_REPORT_VIEW' => 'Strategy Reports access',
            'STRATEGY_WORKFLOW_EDIT' => 'Strategy Workflow access',
            'STRATEGY_SUBMISSION_PREPARE' => 'Funding Submission preparation access',
            'STRATEGY_SUBMISSION_REVIEW' => 'Funding Submission review access',
            'STRATEGY_SUBMISSION_APPROVE' => 'Funding Submission approval access',
            'STRATEGY_PUBLISH' => 'Funding Submission publish access',
            'STRATEGY_SEGMENT_PUBLISH' => 'Segment Publication access',
            'USERS_VIEW' => 'Users view access',
            'USERS_EDIT' => 'Users edit access',
            'USERS_ADMIN' => 'Users administration access',
            'ROLES_VIEW' => 'Roles view access',
            'ROLES_ADMIN' => 'Roles administration access',
            'AUDIT_VIEW' => 'Audit view access',
            'HEALTH_VIEW' => 'Health monitoring access',
            'SESSION_VIEW' => 'Session view access',
            'SESSION_ADMIN' => 'Session administration access',
            'DIAG_VIEW' => 'Diagnostics access',
            'LOGS_VIEW' => 'Application logs access',
            'ERRORLOG_VIEW' => 'Error log view access',
            'ERRORLOG_ADMIN' => 'Error log administration access',
            'SYSSETTINGS_VIEW' => 'System Settings view access',
            'SYSSETTINGS_EDIT' => 'System Settings edit access',
            'SYSSETTINGS_ADMIN' => 'System Settings administration access',
            'WORKFLOW_VIEW' => 'Workflow Configuration view access',
            'WORKFLOW_EDIT' => 'Workflow Configuration edit access',
            'WORKFLOW_ADMIN' => 'Workflow Configuration administration access',
            'WORKFLOW_OPERATIONS_VIEW' => 'Workflow Operations view access',
            'WORKFLOW_OPERATIONS_EDIT' => 'Workflow Operations edit access',
            'WORKFLOW_OPERATIONS_ADMIN' => 'Workflow Operations administration access',
            'METRICS_VIEW' => 'Metrics view access',
            'DATAOBJECTCODES_VIEW' => 'Data Object Codes view access',
            'DATAOBJECTCODES_EDIT' => 'Data Object Codes edit access',
            'DATAOBJECTCODES_IMPORT' => 'Data Object Codes import access',
            'DATAOBJECTCODES_ACCESS_ADMIN' => 'Data Object Code Access administration',
            'DATAOBJECTCODES_ADMIN' => 'Data Object Codes administration access',
            'SEGMENTS_VIEW' => 'Segments view access',
            'SEGMENTS_EDIT' => 'Segments edit access',
            'SEGMENT_VALUES_IMPORT' => 'Segment Values import access',
            'BUDGET_EXECUTION_VIEW' => 'Budget Execution view access',
            'BUDGET_EXECUTION_EDIT' => 'Budget Execution edit access',
            'BUDGET_EXECUTION_REVIEW' => 'Budget Execution review access',
            'BUDGET_EXECUTION_ADMIN' => 'Budget Execution administration access',
            'ANALYTICS_VIEW' => 'Analytics access',
            'DASHBOARD_VIEW' => 'Dashboard view access',
            'DASHBOARD_ADMIN' => 'Dashboard administration access',
        ];

        if (isset($labels[$code])) {
            return $labels[$code] . ' (' . $code . ')';
        }

        return ucwords(strtolower(str_replace('_', ' ', $code))) . ' (' . $code . ')';
    }

    protected function flash(string $type, string $keyOrText, array $replacements = []): void
    {
        $text = __t($keyOrText, $replacements);
        SessionHelper::set('flash.message', ['type' => $type, 'text' => $text]);
        if ($type === 'danger') {
            $this->logUserFacingSystemError($keyOrText, $text);
        }
        session_write_close();
    }

    protected function logUserFacingSystemError(string $source, string $message): void
    {
        if (!$this->shouldLogUserFacingSystemError($source, $message)) {
            return;
        }

        static $logged = [];
        $route = $this->currentRoute();
        $fingerprint = md5(static::class . '|' . $route . '|' . $source . '|' . $message);
        if (isset($logged[$fingerprint])) {
            return;
        }
        $logged[$fingerprint] = true;

        app_log('User-facing system error', [
            'source' => $source,
            'message' => $message,
            'controller' => static::class,
            'route' => $route,
            'session_id' => session_id(),
            'user_id' => (int) SessionHelper::get('auth.user_id', 0),
            'username' => (string) SessionHelper::get('auth.username', ''),
        ], 'error');
    }

    private function shouldLogUserFacingSystemError(string $source, string $message): bool
    {
        $haystack = strtolower(trim($source . ' ' . $message));
        if ($haystack === '') {
            return false;
        }

        $routineDenialPatterns = [
            '/\baccess restricted\b/',
            '/\baccess denied\b/',
            '/\bdoes not include the access\b/',
            '/\bmissing permission\b/',
            '/\bsecurity check failed\b/',
            '/\bcsrf\b/',
        ];

        foreach ($routineDenialPatterns as $pattern) {
            if (preg_match($pattern, $haystack)) {
                return false;
            }
        }

        $systemFailurePatterns = [
            '/\bfailed\b/',
            '/\bfailure\b/',
            '/\bexception\b/',
            '/\bfatal\b/',
            '/\bdatabase\b/',
            '/\bsql\b/',
            '/\bpdo\b/',
            '/\bmail\b/',
            '/\bsmtp\b/',
            '/\bcould not\b/',
            '/\bunable to\b/',
            '/\binstall(ed)?\b/',
            '/\bschema\b/',
        ];

        foreach ($systemFailurePatterns as $pattern) {
            if (preg_match($pattern, $haystack)) {
                return true;
            }
        }

        $validationPatterns = [
            '/\binvalid\b/',
            '/\brequired\b/',
            '/\bplease select\b/',
            '/\bplease enter\b/',
            '/\balready exists\b/',
            '/\bnot found\b/',
            '/\bnot available\b/',
        ];

        foreach ($validationPatterns as $pattern) {
            if (preg_match($pattern, $haystack)) {
                return false;
            }
        }

        return false;
    }

    protected function flashSuccess(string $keyOrText, array $replacements = []): void
    {
        $this->flash('success', $keyOrText, $replacements);
    }

    protected function flashError(string $keyOrText, array $replacements = []): void
    {
        $this->flash('danger', $keyOrText, $replacements);
    }

    protected function flashInfo(string $keyOrText, array $replacements = []): void
    {
        $this->flash('info', $keyOrText, $replacements);
    }

    protected function sessionHeartbeat(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            \App\Shared\SessionHelper::ensureSession();
        }

        $sid = session_id();
        if (!$sid) return;

        $userId = (int) \App\Shared\SessionHelper::get('auth.user_id', 0);
        $username = (string) \App\Shared\SessionHelper::get('auth.username', '');
        if ($userId <= 0 || $username === '') {
            app_log('sessionHeartbeat: No valid user session', ['session_id' => $sid], 'warn');
            return;
        }

        $cache = $_SESSION['cbmsv21']['settings_cache'] ?? [];
        $now = time();

        if (empty($cache['fetched_at']) || ($now - $cache['fetched_at']) > 300) {
            try {
                global $conn;
                $settings = new \App\Models\SystemSettingsModel($conn);
                $cache['idle'] = (int) $settings->get('SESSION_IDLE_TIMEOUT_SEC', '1200');
                $cache['heartbeat'] = (int) $settings->get('SESSION_HEARTBEAT_THROTTLE_SEC', '30');
                $cache['fetched_at'] = $now;
                $_SESSION['cbmsv21']['settings_cache'] = $cache;
                app_log('sessionHeartbeat: cached settings', ['idle' => $cache['idle'], 'heartbeat' => $cache['heartbeat'], 'session_id' => $sid], 'debug');
            } catch (\Throwable $e) {
                $cache['idle'] = 1200;
                $cache['heartbeat'] = 30;
                app_log('sessionHeartbeat: Failed to load settings', ['error' => $e->getMessage(), 'session_id' => $sid], 'warn');
            }
        }

        $IDLE_TIMEOUT_SEC = $cache['idle'] ?? 1200;
        $HEARTBEAT_THROTTLE_SEC = $cache['heartbeat'] ?? 30;

        $nextTouch = (int) ($_SESSION['cbmsv21']['session']['next_touch_ts'] ?? 0);
        if ($now < $nextTouch) return;

        try {
            global $conn;
            require_once __DIR__ . '/../Models/UserSessionModel.php';
            $model = new \App\Models\UserSessionModel($conn);

            $model->ensure(
                $sid,
                $userId,
                $username,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $IDLE_TIMEOUT_SEC
            );

            $model->touch($sid, $IDLE_TIMEOUT_SEC);

            $_SESSION['cbmsv21']['session']['next_touch_ts'] = $now + $HEARTBEAT_THROTTLE_SEC;
            app_log('sessionHeartbeat successful', ['userId' => $userId, 'sid' => $sid], 'info');
        } catch (\Throwable $e) {
            app_log('sessionHeartbeat failed', ['error' => $e->getMessage(), 'userId' => $userId, 'sid' => $sid], 'error');
        }

        session_write_close();
    }

    protected function autoMaintenance(): void
    {
        $userId = (int) (\App\Shared\SessionHelper::get('auth.user_id') ?? 0);
        if ($userId <= 0) {
            return;
        }

        $now = time();
        $last = (int) ($_SESSION['cbmsv21']['maintenance']['last_run'] ?? 0);
        if ($now - $last < 86400) {
            return;
        }

        $_SESSION['cbmsv21']['maintenance']['last_run'] = $now;

        try {
            global $conn;
            require_once __DIR__ . '/../Models/SystemSettingsModel.php';
            require_once __DIR__ . '/../Models/UserSessionModel.php';

            static $cachedRetentionDays = null;

            if ($cachedRetentionDays === null) {
                $settings = new \App\Models\SystemSettingsModel($conn);
                $cachedRetentionDays = (int) $settings->get('SESSION_RETENTION_DAYS', '30');
                app_log('autoMaintenance: cached SESSION_RETENTION_DAYS', ['value' => $cachedRetentionDays, 'session_id' => session_id()], 'debug');
            }

            $sql = "EXEC dbo.usp_UserSessions_Purge @RetainDays = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$cachedRetentionDays]);

            app_log('autoMaintenance: purge ok', [
                'time' => gmdate('Y-m-d H:i:s') . ' UTC',
                'retentionDays' => $cachedRetentionDays,
                'rows_deleted' => $stmt->rowCount() ?: null,
                'session_id' => session_id()
            ], 'info');
        } catch (\Throwable $e) {
            app_log('autoMaintenance failed', ['error' => $e->getMessage(), 'session_id' => session_id()], 'warn');
        }
    }

    protected function strategicWorkflowState(): array
    {
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            return [
                'WorkflowInstalled' => false,
                'WorkflowStatusCode' => 'DRAFT',
                'WorkflowStatusLabel' => 'Draft',
                'IsEditable' => true,
                'AllowedActions' => [],
                'StatusMessage' => 'Database connection is not available.',
            ];
        }

        $model = new StrategicBudgetingModel($this->db);
        return $model->getStrategicWorkflowState($fy, $ver);
    }

    protected function assertStrategicContextEditable(string $redirectUrl): void
    {
        $state = $this->strategicWorkflowState();
        if (!(bool) ($state['WorkflowInstalled'] ?? false)) {
            return;
        }
        if ((bool) ($state['IsEditable'] ?? false)) {
            return;
        }

        $statusLabel = (string) ($state['WorkflowStatusLabel'] ?? 'read-only');
        $this->flashError('The active strategic version is currently ' . $statusLabel . ' and cannot be edited.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    protected function buildCurrentReturnUrl(): string
    {
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        if ($query !== '') {
            return 'index.php?' . $query;
        }

        $route = trim((string) ($_GET['route'] ?? ''));
        if ($route !== '') {
            return 'index.php?route=' . rawurlencode($route);
        }

        return 'index.php?route=home/index';
    }

    protected function applyLinkedContextFromRequest(): void
    {
        if ((int) SessionHelper::get('auth.user_id', 0) <= 0) {
            return;
        }

        $linkContext = (string) ($_REQUEST['link_context'] ?? '');
        if ($linkContext !== '1') {
            return;
        }

        if (array_key_exists('scope_dataobject_code', $_REQUEST)) {
            $scopeCode = trim((string) ($_REQUEST['scope_dataobject_code'] ?? ''));
            if ($scopeCode !== '') {
                SessionHelper::set('scope.dataobject_code', $scopeCode);
                SessionHelper::set('scope.dataobject_name', trim((string) ($_REQUEST['scope_dataobject_name'] ?? '')));
            } else {
                SessionHelper::forget('scope.dataobject_code');
                SessionHelper::forget('scope.dataobject_name');
            }
        }

        if (isset($_REQUEST['fy']) && is_numeric($_REQUEST['fy'])) {
            SessionHelper::set('FiscalYearID', (int) $_REQUEST['fy']);
        }
        if (isset($_REQUEST['ver']) && is_numeric($_REQUEST['ver'])) {
            SessionHelper::set('VersionID', (int) $_REQUEST['ver']);
        }
    }

    protected function buildLinkedContextParams(array $overrides = []): array
    {
        $params = [
            'link_context' => 1,
            'fy' => (int) SessionHelper::get('FiscalYearID', 0),
            'ver' => (int) SessionHelper::get('VersionID', 0),
            'scope_dataobject_code' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
        ];

        $scopeName = trim((string) SessionHelper::get('scope.dataobject_name', ''));
        if ($params['scope_dataobject_code'] !== '') {
            if ($scopeName !== '') {
                $params['scope_dataobject_name'] = $scopeName;
            }
        }

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
                continue;
            }
            $params[$key] = $value;
        }

        if (($params['scope_dataobject_code'] ?? '') === '') {
            unset($params['scope_dataobject_name']);
        }

        return $params;
    }

    protected function mergeLinkedContextIntoUrl(string $url, array $overrides = []): string
    {
        $url = trim($url);
        if ($url === '') {
            $url = 'index.php?route=home/index';
        }

        if (preg_match('~^https?://~i', $url)) {
            $parts = parse_url($url);
            $path = (string) ($parts['path'] ?? '');
            $query = (string) ($parts['query'] ?? '');
            $fragment = (string) ($parts['fragment'] ?? '');
            $indexPos = stripos($path, 'index.php');
            if ($indexPos === false) {
                $url = 'index.php?route=home/index';
            } else {
                $url = substr($path, $indexPos);
                if ($query !== '') {
                    $url .= '?' . $query;
                }
                if ($fragment !== '') {
                    $url .= '#' . $fragment;
                }
            }
        }

        if (str_starts_with($url, '?')) {
            $url = 'index.php' . $url;
        }

        if (!str_starts_with($url, 'index.php')) {
            $indexPos = stripos($url, 'index.php');
            if ($indexPos !== false) {
                $url = substr($url, $indexPos);
            }
        }

        if (!str_starts_with($url, 'index.php')) {
            return 'index.php?route=home/index';
        }

        if (preg_match('~[\r\n]~', $url)) {
            return 'index.php?route=home/index';
        }

        $fragment = '';
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            $fragment = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }

        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? 'index.php');
        $query = [];
        $rawQuery = (string) ($parts['query'] ?? '');
        if ($rawQuery !== '') {
            parse_str($rawQuery, $query);
        }

        foreach ($this->buildLinkedContextParams($overrides) as $key => $value) {
            $query[$key] = $value;
        }

        return $path . ($query !== [] ? '?' . http_build_query($query) : '') . $fragment;
    }
}
