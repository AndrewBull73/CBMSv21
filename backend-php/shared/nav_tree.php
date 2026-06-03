<?php
declare(strict_types=1);

use App\Core\Rbac;
use App\Shared\SessionHelper;

/**
 * Recursively render offcanvas menu items.
 */
function render_offcanvas_level(array $items, string $current, int $depth = 0): string {
    $html = '';
    static $seq = 0;
    static $jumpIndex = null;

    if ($depth === 0) {
        $jumpIndex = menu_jump_build_index($items);
    }

    foreach ($items as $it) {
        if (!menu_item_visible($it)) {
            continue;
        }

        $hasKids  = !empty($it['children']);
        $isActive = route_is_active($current, $it);
        $icon     = !empty($it['icon']) ? '<i class="bi bi-'.htmlspecialchars($it['icon'], ENT_QUOTES).' me-2"></i>' : '';
        $itemCode = menu_item_display_code($it, is_array($jumpIndex) ? $jumpIndex : ['routeMap' => []]);
        $badge = $itemCode !== null
            ? '<span class="badge text-bg-light border ms-auto me-2">'.htmlspecialchars($itemCode, ENT_QUOTES).'</span>'
            : '';

        if ($hasKids) {
            $id = 'oc_' . (++$seq);
            $expanded = $isActive ? 'true' : 'false';
            $show = $isActive ? ' show' : '';
            $html .= '<a class="list-group-item list-group-item-action d-flex align-items-center"'
                  .  ' data-bs-toggle="collapse" href="#'.$id.'" role="button"'
                  .  ' aria-expanded="'.$expanded.'" aria-controls="'.$id.'">'
                  .  $icon . htmlspecialchars($it['label'] ?? 'Menu', ENT_QUOTES)
                  .  $badge
                  .  '<i class="bi bi-chevron-right ms-auto chev"></i>'
                  .  '</a>';
            $html .= '<div class="collapse'.$show.'" id="'.$id.'"><div class="list-group list-group-flush ms-3">';
            $html .= render_offcanvas_level($it['children'], $current, $depth+1);
            $html .= '</div></div>';
        } else {
            $href = '#';
            if (!empty($it['href'])) {
                $href = (string) $it['href'];
            } elseif (!empty($it['route'])) {
                $href = 'index.php?route=' . urlencode($it['route']);
            }
            $html .= '<a class="list-group-item list-group-item-action'.($isActive ? ' active' : '').'"'
                  .  ' href="'.htmlspecialchars($href, ENT_QUOTES).'">'
                  .  '<span class="d-flex align-items-center gap-2">'
                  .  '<span>'
                  .  $icon . htmlspecialchars($it['label'] ?? 'Item', ENT_QUOTES)
                  .  '</span>'
                  .  $badge
                  .  '</span>'
                  .  '</a>';
        }
    }
    return $html;
}

/**
 * Check if a menu item should be visible.
 */
function menu_item_visible(array $item): bool {
    $loggedIn = (bool)SessionHelper::get('auth.user_id');

    if (array_key_exists('enabled', $item) && !$item['enabled']) {
        return false;
    }

    if (!empty($item['perms']) && !Rbac::canAny((array)$item['perms'])) {
        return false;
    }
    if (!empty($item['roles']) && !Rbac::hasAnyRole((array)$item['roles'])) {
        return false;
    }
    return true;
}

/**
 * Check if the current route is active.
 */
function route_is_active(string $current, array $item): bool {
    if (!empty($item['route']) && $current === $item['route']) return true;
    if (!empty($item['active']) && is_array($item['active'])) {
        foreach ($item['active'] as $p) {
            if ($p === $current) return true;
            if (str_ends_with($p, '*') && str_starts_with($current, rtrim($p, '*'))) return true;
        }
    }
    if (!empty($item['children'])) {
        foreach ($item['children'] as $c) {
            if (route_is_active($current, $c)) return true;
        }
    }
    return false;
}

/**
 * Debug banner: show current roles and perms if APP_DEBUG=true
 */
function render_menu_debug(): string {
    if (!envFlag('APP_DEBUG', false)) {
        return '';
    }
    $roles = implode(', ', Rbac::roles());
    $perms = implode(', ', Rbac::perms());
    return <<<HTML
<div class="alert alert-warning m-2 p-2">
  <strong>DEBUG:</strong> Roles = [$roles] | Perms = [$perms]
</div>
HTML;
}

function menu_jump_build_index(array $items): array {
    $entries = [];
    menu_jump_collect_entries($items, $entries);

    $usedCodes = [];
    foreach ($entries as &$entry) {
        $entry['code'] = null;
        foreach ($entry['candidateCodes'] as $candidate) {
            if ($candidate === '' || isset($usedCodes[$candidate])) {
                continue;
            }

            $entry['code'] = $candidate;
            $usedCodes[$candidate] = true;
            break;
        }
    }
    unset($entry);

    $aliasToRoutes = [];
    foreach ($entries as $entry) {
        $aliases = [];
        if (!empty($entry['code'])) {
            $aliases[] = $entry['code'];
        }
        $aliases[] = menu_jump_normalize((string) ($entry['route'] ?? ''));
        $aliases[] = strtoupper((string) ($entry['route'] ?? ''));

        foreach (array_unique(array_filter($aliases, static fn(string $value): bool => $value !== '')) as $alias) {
            $aliasToRoutes[$alias] ??= [];
            $aliasToRoutes[$alias][$entry['route']] = true;
        }
    }

    $lookup = [];
    foreach ($aliasToRoutes as $alias => $routes) {
        if (count($routes) === 1) {
            $lookup[$alias] = array_key_first($routes);
        }
    }

    $routeMap = [];
    foreach ($entries as $entry) {
        $routeMap[$entry['route']] = $entry;
    }

    return [
        'entries' => $entries,
        'lookup' => $lookup,
        'routeMap' => $routeMap,
    ];
}

function menu_jump_collect_entries(array $items, array &$entries): void {
    foreach ($items as $item) {
        if (!menu_item_visible($item)) {
            continue;
        }

        if (!empty($item['children']) && is_array($item['children'])) {
            menu_jump_collect_entries($item['children'], $entries);
            continue;
        }

        $route = trim((string) ($item['route'] ?? ''));
        if ($route === '') {
            continue;
        }

        $label = trim((string) ($item['label'] ?? $route));
        $entries[] = [
            'label' => $label,
            'route' => $route,
            'icon' => (string) ($item['icon'] ?? ''),
            'candidateCodes' => menu_jump_candidate_codes($item),
        ];
    }
}

function menu_jump_candidate_codes(array $item): array {
    $route = trim((string) ($item['route'] ?? ''));
    $label = trim((string) ($item['label'] ?? $route));

    $candidates = [];
    $explicit = menu_jump_normalize((string) ($item['code'] ?? ''));
    if ($explicit !== '') {
        $candidates[] = $explicit;
    }

    $labelInitials = menu_jump_initials($label);
    if ($labelInitials !== '') {
        $candidates[] = $labelInitials;
    }

    $routeInitials = menu_jump_initials(str_replace('/', ' ', $route));
    if ($routeInitials !== '') {
        $candidates[] = $routeInitials;
    }

    $compactRoute = menu_jump_normalize($route);
    if ($compactRoute !== '') {
        $candidates[] = $compactRoute;
    }

    return array_values(array_unique(array_filter($candidates, static fn(string $value): bool => $value !== '')));
}

function menu_jump_initials(string $text): string {
    $parts = preg_split('/[^A-Za-z0-9]+/', strtoupper($text)) ?: [];
    $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    if ($parts === []) {
        return '';
    }

    $initials = '';
    foreach ($parts as $part) {
        $initials .= $part[0];
    }

    return substr($initials, 0, 8);
}

function menu_jump_normalize(string $text): string {
    $normalized = preg_replace('/[^A-Za-z0-9]+/', '', strtoupper(trim($text)));
    return is_string($normalized) ? $normalized : '';
}

function menu_jump_resolve(array $jumpIndex, string $rawCode): ?array {
    $normalized = menu_jump_normalize($rawCode);
    if ($normalized !== '' && isset($jumpIndex['lookup'][$normalized], $jumpIndex['routeMap'][$jumpIndex['lookup'][$normalized]])) {
        return $jumpIndex['routeMap'][$jumpIndex['lookup'][$normalized]];
    }

    $route = trim($rawCode);
    if ($route !== '' && isset($jumpIndex['routeMap'][$route])) {
        return $jumpIndex['routeMap'][$route];
    }

    return null;
}

function menu_jump_code_for_item(array $item, array $jumpIndex): ?string {
    $route = trim((string) ($item['route'] ?? ''));
    if ($route === '' || empty($jumpIndex['routeMap'][$route]['code'])) {
        return null;
    }

    return (string) $jumpIndex['routeMap'][$route]['code'];
}

function menu_item_display_code(array $item, array $jumpIndex): ?string {
    $explicit = menu_jump_normalize((string) ($item['code'] ?? ''));
    if ($explicit !== '') {
        return $explicit;
    }

    return menu_jump_code_for_item($item, $jumpIndex);
}
