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
        $menu = (is_file($menuFile) && is_array($tmp = require $menuFile)) ? $tmp : [];
        $jumpIndex = menu_jump_build_index($menu);

        if ($rawCode === '') {
            $this->flashError('Enter a menu code or route.');
            header('Location: ' . $returnUrl);
            exit;
        }

        $match = menu_jump_resolve($jumpIndex, $rawCode);
        if ($match === null || empty($match['route'])) {
            $this->flashError('Menu code not found, or you do not have access to that screen.');
            header('Location: ' . $returnUrl);
            exit;
        }

        header('Location: index.php?route=' . urlencode((string) $match['route']));
        exit;
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
