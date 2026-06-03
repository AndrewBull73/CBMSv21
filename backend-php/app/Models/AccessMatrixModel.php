<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use ReflectionClass;

final class AccessMatrixModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getDashboard(array $filters = []): array
    {
        $routes = $this->loadRoutes();
        $menuIndex = $this->loadMenuRouteIndex();
        $rolePermissionMap = $this->loadRolePermissionMap();
        $rows = [];

        foreach ($routes as $route => $handler) {
            if (!is_string($handler) || !str_contains($handler, '@')) {
                continue;
            }

            [$controllerName, $action] = explode('@', $handler, 2);
            $controllerName = trim($controllerName);
            $action = trim($action);
            $module = $this->routeModule($route);
            $acl = $this->resolveControllerAcl($controllerName, $action);
            $menuMeta = $menuIndex[$route] ?? null;

            $controllerPermsAny = $this->normalizeCodes($acl['permsAny'] ?? []);
            $controllerPermsAll = $this->normalizeCodes($acl['permsAll'] ?? []);
            $menuPerms = $this->normalizeCodes($menuMeta['perms'] ?? []);
            $menuRoles = $this->normalizeRoleNames($menuMeta['roles'] ?? []);
            $authRequired = (bool) ($acl['auth'] ?? true);
            $controllerRoles = $this->rolesSatisfyingControllerRules($controllerPermsAny, $controllerPermsAll, $rolePermissionMap);
            $menuPermissionRoles = $this->rolesSatisfyingAnyPermissions($menuPerms, $rolePermissionMap);
            $accessType = $this->classifyAccess($authRequired, $controllerPermsAny, $controllerPermsAll);
            $notes = $this->buildNotes($authRequired, $controllerPermsAny, $controllerPermsAll, $menuPerms, $menuRoles);

            $row = [
                'route' => $route,
                'screen_label' => (string) ($menuMeta['label'] ?? $this->humanizeRoute($route)),
                'menu_path' => (string) ($menuMeta['path'] ?? ''),
                'module' => $module,
                'functional_area' => $this->functionalArea($route, $menuMeta),
                'controller' => $controllerName,
                'action' => $action,
                'handler' => $handler,
                'auth_required' => $authRequired,
                'controller_perms_any' => $controllerPermsAny,
                'controller_perms_all' => $controllerPermsAll,
                'controller_roles' => $controllerRoles,
                'menu_perms' => $menuPerms,
                'menu_roles' => $menuRoles,
                'menu_permission_roles' => $menuPermissionRoles,
                'access_type' => $accessType,
                'notes' => $notes,
            ];

            if (!$this->matchesFilters($row, $filters)) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['functional_area'], $a['module'], $a['screen_label'], $a['route']] <=> [$b['functional_area'], $b['module'], $b['screen_label'], $b['route']];
        });

        return [
            'summary' => $this->buildSummary($rows),
            'rows' => $rows,
            'modules' => $this->listDistinctValues($rows, 'module'),
            'functional_areas' => $this->listDistinctValues($rows, 'functional_area'),
            'grouped_rows' => $this->groupRowsByArea($rows),
            'permissions' => $this->listDistinctPermissions($rows),
            'roles' => $this->listDistinctRoles($rows),
        ];
    }

    private function loadRoutes(): array
    {
        /** @var array<string, string> $routes */
        $routes = require dirname(__DIR__, 2) . '/config/routes.php';
        return $routes;
    }

    private function loadMenuRouteIndex(): array
    {
        $menu = require dirname(__DIR__, 2) . '/config/menu.php';
        $index = [];
        $this->flattenMenu($menu, [], $index);
        return $index;
    }

    private function flattenMenu(array $items, array $trail, array &$index): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $nextTrail = $trail;
            if ($label !== '') {
                $nextTrail[] = $label;
            }

            $route = trim((string) ($item['route'] ?? ''));
            if ($route !== '') {
                $index[$route] = [
                    'label' => $label !== '' ? $label : $this->humanizeRoute($route),
                    'path' => implode(' / ', $nextTrail),
                    'roles' => is_array($item['roles'] ?? null) ? $item['roles'] : [],
                    'perms' => is_array($item['perms'] ?? null) ? $item['perms'] : [],
                ];
            }

            if (is_array($item['children'] ?? null)) {
                $this->flattenMenu($item['children'], $nextTrail, $index);
            }
        }
    }

    private function resolveControllerAcl(string $controllerName, string $action): array
    {
        $basePath = dirname(__DIR__) . '/Controllers/';
        $controllerFile = $basePath . $controllerName . '.php';
        if (!is_file($controllerFile)) {
            return [];
        }

        require_once $basePath . 'BaseController.php';
        require_once $controllerFile;

        $className = 'App\\Controllers\\' . $controllerName;
        if (!class_exists($className)) {
            return [];
        }

        $reflection = new ReflectionClass($className);
        $defaults = $reflection->getDefaultProperties();
        $acl = is_array($defaults['acl'] ?? null) ? $defaults['acl'] : [];

        if (isset($acl[$action]) && is_array($acl[$action])) {
            return $acl[$action];
        }

        return is_array($acl['*'] ?? null) ? $acl['*'] : [];
    }

    private function loadRolePermissionMap(): array
    {
        $sql = "
            SELECT r.RoleName, p.PermissionCode
            FROM dbo.tblRoles r
            LEFT JOIN dbo.tblRolePermissions rp
                ON rp.RoleID = r.RoleID
            LEFT JOIN dbo.tblPermissions p
                ON p.PermissionID = rp.PermissionID
            WHERE (r.Active = 1 OR r.Active IS NULL)
              AND (p.Active = 1 OR p.Active IS NULL OR p.PermissionID IS NULL)
            ORDER BY r.RoleName, p.PermissionCode
        ";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];

        foreach ($rows as $row) {
            $role = trim((string) ($row['RoleName'] ?? ''));
            $perm = strtoupper(trim((string) ($row['PermissionCode'] ?? '')));
            if ($role === '') {
                continue;
            }
            if (!isset($map[$role])) {
                $map[$role] = [];
            }
            if ($perm !== '') {
                $map[$role][$perm] = true;
            }
        }

        return $map;
    }

    private function rolesSatisfyingControllerRules(array $permsAny, array $permsAll, array $rolePermissionMap): array
    {
        if ($permsAll !== []) {
            $matches = [];
            foreach ($rolePermissionMap as $role => $perms) {
                $ok = true;
                foreach ($permsAll as $perm) {
                    if (!isset($perms[$perm])) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $matches[] = $role;
                }
            }
            sort($matches);
            return $matches;
        }

        return $this->rolesSatisfyingAnyPermissions($permsAny, $rolePermissionMap);
    }

    private function rolesSatisfyingAnyPermissions(array $permissions, array $rolePermissionMap): array
    {
        if ($permissions === []) {
            return [];
        }

        $matches = [];
        foreach ($rolePermissionMap as $role => $perms) {
            foreach ($permissions as $perm) {
                if (isset($perms[$perm])) {
                    $matches[] = $role;
                    break;
                }
            }
        }

        sort($matches);
        return array_values(array_unique($matches));
    }

    private function classifyAccess(bool $authRequired, array $permsAny, array $permsAll): string
    {
        if (!$authRequired) {
            return 'Public';
        }
        if ($permsAny !== [] || $permsAll !== []) {
            return 'Permission Controlled';
        }
        return 'Auth Only';
    }

    private function buildNotes(bool $authRequired, array $controllerPermsAny, array $controllerPermsAll, array $menuPerms, array $menuRoles): array
    {
        $notes = [];
        if ($authRequired && $controllerPermsAny === [] && $controllerPermsAll === []) {
            $notes[] = 'Controller allows any authenticated user.';
        }
        if ($menuRoles !== []) {
            $notes[] = 'Menu still has role-based visibility.';
        }
        if ($menuPerms !== [] && $controllerPermsAny === [] && $controllerPermsAll === []) {
            $notes[] = 'Menu is stricter than controller.';
        }
        if ($menuPerms === [] && ($controllerPermsAny !== [] || $controllerPermsAll !== [])) {
            $notes[] = 'Controller is stricter than menu.';
        }
        return $notes;
    }

    private function matchesFilters(array $row, array $filters): bool
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $module = strtolower(trim((string) ($filters['module'] ?? '')));
        $functionalArea = strtolower(trim((string) ($filters['functional_area'] ?? '')));
        $permission = strtoupper(trim((string) ($filters['permission'] ?? '')));
        $role = strtolower(trim((string) ($filters['role'] ?? '')));
        $accessType = strtolower(trim((string) ($filters['access_type'] ?? '')));

        if ($q !== '') {
            $haystack = strtolower(implode(' ', [
                $row['route'],
                $row['screen_label'],
                $row['menu_path'],
                $row['controller'],
                $row['action'],
            ]));
            if (!str_contains($haystack, strtolower($q))) {
                return false;
            }
        }

        if ($module !== '' && strtolower((string) $row['module']) !== $module) {
            return false;
        }

        if ($functionalArea !== '' && strtolower((string) $row['functional_area']) !== $functionalArea) {
            return false;
        }

        if ($permission !== '') {
            $perms = array_merge($row['controller_perms_any'], $row['controller_perms_all'], $row['menu_perms']);
            if (!in_array($permission, $perms, true)) {
                return false;
            }
        }

        if ($role !== '') {
            $roles = array_merge($row['controller_roles'], $row['menu_roles'], $row['menu_permission_roles']);
            $normalizedRoles = array_map(static fn(string $value): string => strtolower($value), $roles);
            if (!in_array($role, $normalizedRoles, true)) {
                return false;
            }
        }

        if ($accessType !== '' && strtolower((string) $row['access_type']) !== $accessType) {
            return false;
        }

        return true;
    }

    private function buildSummary(array $rows): array
    {
        $summary = [
            'route_count' => count($rows),
            'permission_controlled_count' => 0,
            'auth_only_count' => 0,
            'public_count' => 0,
            'menu_role_count' => 0,
        ];

        foreach ($rows as $row) {
            $type = strtolower((string) ($row['access_type'] ?? ''));
            if ($type === 'permission controlled') {
                $summary['permission_controlled_count']++;
            } elseif ($type === 'auth only') {
                $summary['auth_only_count']++;
            } elseif ($type === 'public') {
                $summary['public_count']++;
            }

            if (($row['menu_roles'] ?? []) !== []) {
                $summary['menu_role_count']++;
            }
        }

        return $summary;
    }

    private function listDistinctValues(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                $values[$value] = true;
            }
        }
        $keys = array_keys($values);
        sort($keys);
        return $keys;
    }

    private function listDistinctPermissions(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            foreach (array_merge($row['controller_perms_any'], $row['controller_perms_all'], $row['menu_perms']) as $permission) {
                $values[$permission] = true;
            }
        }
        $keys = array_keys($values);
        sort($keys);
        return $keys;
    }

    private function listDistinctRoles(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            foreach (array_merge($row['controller_roles'], $row['menu_roles'], $row['menu_permission_roles']) as $role) {
                $values[$role] = true;
            }
        }
        $keys = array_keys($values);
        sort($keys);
        return $keys;
    }

    private function routeModule(string $route): string
    {
        $first = trim((string) strtok($route, '/'));
        if ($first === '') {
            return 'general';
        }
        return $first;
    }

    private function functionalArea(string $route, ?array $menuMeta): string
    {
        $menuPath = trim((string) ($menuMeta['path'] ?? ''));
        $menuLabel = trim((string) ($menuMeta['label'] ?? ''));

        if ($menuPath !== '') {
            if (str_contains($menuPath, 'Configuration / Base Configuration')) {
                return 'Base Configuration';
            }
            if (str_contains($menuPath, 'Configuration / Financial & Calculation Configuration')) {
                return 'Financial & Calculation Configuration';
            }
            if (str_contains($menuPath, 'Configuration / Strategy Configuration')) {
                return 'Strategy Configuration';
            }
            if (str_starts_with($menuPath, 'Administration')) {
                return 'Administration';
            }
            if (str_starts_with($menuPath, 'Strategy / Start & Review')) {
                return 'Strategy Start & Review';
            }
            if (str_starts_with($menuPath, 'Strategy / Configuration')) {
                return 'Strategy Configuration';
            }
            if (str_starts_with($menuPath, 'Strategy / Structure Setup')) {
                return 'Strategy Structure Setup';
            }
            if (str_starts_with($menuPath, 'Strategy / Planning Framework')) {
                return 'Strategy Planning Framework';
            }
            if (str_starts_with($menuPath, 'Strategy / Delivery & Costing')) {
                return 'Strategy Delivery & Costing';
            }
            if (str_starts_with($menuPath, 'Strategy / Governance')) {
                return 'Strategy Governance';
            }
            if (str_starts_with($menuPath, 'Strategy / Reports')) {
                return 'Strategy Reports';
            }
            if (str_starts_with($menuPath, 'Funding Submissions')) {
                return 'Funding Submissions';
            }
            if (str_starts_with($menuPath, 'Fiscal')) {
                return 'Strategy Fiscal Framework';
            }
            if (str_starts_with($menuPath, 'Estimates')) {
                return 'Estimates';
            }
            if (str_starts_with($menuPath, 'Reports')) {
                return 'Reports';
            }
            if (str_starts_with($menuPath, 'Analytics')) {
                return 'Analytics';
            }
            if (str_starts_with($menuPath, 'Workflow')) {
                return 'Workflow';
            }
            if (str_starts_with($menuPath, 'Metrics')) {
                return 'Metrics';
            }
        }

        if ($menuLabel === 'Access Matrix') {
            return 'Administration';
        }

        return match ($this->routeModule($route)) {
            'base-config', 'segments', 'segment-values', 'system-settings' => 'Base Configuration',
            'financial-config', 'transaction-type-segment-config', 'ceilings', 'scenario-admin', 'transaction-calc-diagnostics', 'full-recalculation' => 'Financial & Calculation Configuration',
            'strategy', 'strategy-reports' => 'Strategy Reports',
            'strategy-config' => 'Strategy Configuration',
            'strategy-setup' => 'Strategy Structure Setup',
            'strategy-performance' => 'Strategy Planning Framework',
            'strategy-delivery' => 'Strategy Delivery & Costing',
            'strategy-governance' => 'Strategy Governance',
            'strategy-fiscal' => 'Strategy Fiscal Framework',
            'strategy-submissions' => 'Funding Submissions',
            'strategy-publish' => 'Strategy Configuration',
            'users', 'roles', 'audit', 'diagnostics', 'health', 'session', 'sessions', 'dataobjectcodes', 'systemmessages', 'access-matrix' => 'Administration',
            'workflow' => 'Workflow',
            'analytics', 'scenario-results' => 'Analytics',
            'metrics' => 'Metrics',
            'transaction-input', 'rates' => 'Estimates',
            default => 'Other',
        };
    }

    private function groupRowsByArea(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $area = (string) ($row['functional_area'] ?? 'Other');
            if (!isset($groups[$area])) {
                $groups[$area] = [
                    'functional_area' => $area,
                    'rows' => [],
                    'summary' => [
                        'route_count' => 0,
                        'permission_controlled_count' => 0,
                        'auth_only_count' => 0,
                        'menu_role_count' => 0,
                    ],
                ];
            }

            $groups[$area]['rows'][] = $row;
            $groups[$area]['summary']['route_count']++;
            if ((string) ($row['access_type'] ?? '') === 'Permission Controlled') {
                $groups[$area]['summary']['permission_controlled_count']++;
            }
            if ((string) ($row['access_type'] ?? '') === 'Auth Only') {
                $groups[$area]['summary']['auth_only_count']++;
            }
            if (($row['menu_roles'] ?? []) !== []) {
                $groups[$area]['summary']['menu_role_count']++;
            }
        }

        ksort($groups);
        return array_values($groups);
    }

    private function humanizeRoute(string $route): string
    {
        $tail = trim((string) preg_replace('~^.*/~', '', $route));
        if ($tail === '') {
            $tail = $route;
        }
        return ucwords(str_replace(['-', '_'], ' ', $tail));
    }

    private function normalizeCodes(array $values): array
    {
        $clean = [];
        foreach ($values as $value) {
            $code = strtoupper(trim((string) $value));
            if ($code !== '') {
                $clean[$code] = true;
            }
        }
        return array_keys($clean);
    }

    private function normalizeRoleNames(array $values): array
    {
        $clean = [];
        foreach ($values as $value) {
            $name = trim((string) $value);
            if ($name !== '') {
                $clean[$name] = true;
            }
        }
        return array_keys($clean);
    }
}
