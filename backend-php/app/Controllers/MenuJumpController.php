<?php
declare(strict_types=1);

namespace App\Controllers;

final class MenuJumpController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true],
    ];

    public function go(): void
    {
        $rawCode = trim((string) ($_GET['code'] ?? $_POST['code'] ?? ''));
        $returnUrl = $this->sanitizeReturnUrl((string) ($_GET['return'] ?? $_POST['return'] ?? ''));

        require_once __DIR__ . '/../../shared/nav_tree.php';
        $menuFile = __DIR__ . '/../../config/menu.php';
        $quickLinksFile = __DIR__ . '/../../config/quick_links.php';
        $menu = (is_file($menuFile) && is_array($tmp = require $menuFile)) ? $tmp : [];
        $quickLinkGroups = (is_file($quickLinksFile) && is_array($tmp = require $quickLinksFile)) ? $tmp : [];
        $jumpIndex = menu_jump_build_index($menu);

        if ($rawCode === '') {
            $this->flashError('Enter a menu code or route.');
            header('Location: ' . $returnUrl);
            exit;
        }

        $match = menu_jump_resolve($jumpIndex, $rawCode);
        if ($match === null || empty($match['route'])) {
            $restrictedItem = $this->findMenuItemForJump($menu, $rawCode);
            if ($restrictedItem !== null && !menu_item_visible($restrictedItem)) {
                $this->flashRestrictedMenuItem($restrictedItem);
                header('Location: ' . $returnUrl);
                exit;
            }

            $quickLink = $this->findQuickLinkForJump($quickLinkGroups, $rawCode);
            if ($quickLink !== null && !empty($quickLink['route'])) {
                $route = trim((string) $quickLink['route']);
                $menuItem = $this->findMenuItemByRoute($menu, $route);
                $securityItem = $menuItem ?? $quickLink;
                if (!menu_item_visible($securityItem)) {
                    $this->flashRestrictedMenuItem($securityItem, trim((string) ($quickLink['label'] ?? '')));
                    header('Location: ' . $returnUrl);
                    exit;
                }

                header('Location: index.php?route=' . urlencode($route));
                exit;
            }

            $this->flashAccessRestricted(
                'Screen not available',
                'CBMS could not find a screen for "' . $rawCode . '", or your account does not include the access needed for it.',
                'Check the menu code and, if this access is required for your work, ask a system administrator to update your assigned roles.'
            );
            header('Location: ' . $returnUrl);
            exit;
        }

        header('Location: index.php?route=' . urlencode((string) $match['route']));
        exit;
    }

    private function findMenuItemForJump(array $items, string $rawCode): ?array
    {
        $normalized = menu_jump_normalize($rawCode);
        $rawRoute = trim($rawCode);

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!empty($item['children']) && is_array($item['children'])) {
                $child = $this->findMenuItemForJump($item['children'], $rawCode);
                if ($child !== null) {
                    return $child;
                }
            }

            $route = trim((string) ($item['route'] ?? ''));
            if ($route === '') {
                continue;
            }

            if ($rawRoute !== '' && $rawRoute === $route) {
                return $item;
            }

            if ($normalized === '') {
                continue;
            }

            $aliases = menu_jump_candidate_codes($item);
            $aliases[] = menu_jump_normalize($route);
            $aliases[] = strtoupper($route);
            if (in_array($normalized, array_values(array_unique($aliases)), true)) {
                return $item;
            }
        }

        return null;
    }

    private function findMenuItemByRoute(array $items, string $route): ?array
    {
        $route = trim($route);
        if ($route === '') {
            return null;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (trim((string) ($item['route'] ?? '')) === $route) {
                return $item;
            }
            $child = $this->findMenuItemByRoute(is_array($item['children'] ?? null) ? $item['children'] : [], $route);
            if ($child !== null) {
                return $child;
            }
        }

        return null;
    }

    private function findQuickLinkForJump(array $groups, string $rawCode): ?array
    {
        $normalized = menu_jump_normalize($rawCode);
        $rawRoute = trim($rawCode);
        if ($normalized === '' && $rawRoute === '') {
            return null;
        }

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach (is_array($group['links'] ?? null) ? $group['links'] : [] as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $route = trim((string) ($link['route'] ?? ''));
                if ($rawRoute !== '' && $route === $rawRoute) {
                    return $link;
                }

                $aliases = [];
                $aliases[] = menu_jump_normalize((string) ($link['code'] ?? ''));
                $aliases[] = menu_jump_initials((string) ($link['label'] ?? ''));
                $aliases[] = menu_jump_initials(str_replace('/', ' ', $route));
                $aliases[] = menu_jump_normalize($route);
                if ($normalized !== '' && in_array($normalized, array_values(array_unique(array_filter($aliases))), true)) {
                    return $link;
                }
            }
        }

        return null;
    }

    private function flashRestrictedMenuItem(array $item, string $labelOverride = ''): void
    {
        $label = $labelOverride !== ''
            ? $labelOverride
            : trim((string) ($item['label'] ?? $item['route'] ?? 'this screen'));
        $perms = array_values(array_filter(array_map('strval', (array) ($item['perms'] ?? []))));
        $roles = array_values(array_filter(array_map('strval', (array) ($item['roles'] ?? []))));
        $requirements = [];

        if ($perms !== []) {
            $requirements[] = 'Required access: ' . $this->formatAccessList($perms, 'or');
        }
        if ($roles !== []) {
            $requirements[] = 'Required role: ' . $this->formatTextList($roles, 'or');
        }

        $guidance = 'If this access is required for your work, ask a system administrator to update your assigned roles.';
        $detail = $requirements !== []
            ? implode('. ', $requirements) . '. ' . $guidance
            : $guidance;

        \App\Shared\SessionHelper::set('flash.message', [
            'type' => 'warning',
            'accessDenied' => true,
            'title' => 'Access Restricted',
            'text' => 'Your account does not include the access needed for ' . $label . '.',
            'detail' => $detail,
        ]);
        session_write_close();
    }

    private function flashAccessRestricted(string $title, string $text, string $detail): void
    {
        \App\Shared\SessionHelper::set('flash.message', [
            'type' => 'warning',
            'accessDenied' => true,
            'title' => $title,
            'text' => $text,
            'detail' => $detail,
        ]);
        session_write_close();
    }

    private function sanitizeReturnUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_contains($url, "\n") || str_contains($url, "\r")) {
            return 'index.php?route=home/index';
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (str_starts_with($url, 'index.php')) {
            return $url;
        }

        return 'index.php?route=home/index';
    }
}
