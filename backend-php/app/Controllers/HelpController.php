<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\Lang;

class HelpController extends BaseController
{
    public function show(): void
    {
        $screen = $_GET['screen'] ?? 'default';
        $safeScreen = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', strtolower($screen));

        // Build base key: e.g. "users/list" → "UsersList"
        $parts = preg_split('/[\/_-]+/', $safeScreen) ?: [];
        $viewKey = implode('', array_map('ucfirst', $parts));
        $legacyViewKey = implode('', array_map('ucfirst', explode('/', $safeScreen)));

        // Active language, e.g. "en", "fr", "es"
        $lang = Lang::getActiveLang();

        // Try language-specific file first: UsersList.en.php
        $viewFile = __DIR__ . "/../Views/help/{$viewKey}.{$lang}.php";

        if (!is_file($viewFile) && $legacyViewKey !== $viewKey) {
            $viewFile = __DIR__ . "/../Views/help/{$legacyViewKey}.{$lang}.php";
        }

        // If not found, try English fallback
        if (!is_file($viewFile)) {
            $viewFile = __DIR__ . "/../Views/help/{$viewKey}.en.php";
        }

        if (!is_file($viewFile) && $legacyViewKey !== $viewKey) {
            $viewFile = __DIR__ . "/../Views/help/{$legacyViewKey}.en.php";
        }

        // If still not found, fall back to default help in the active language
        if (!is_file($viewFile)) {
            $viewFile = __DIR__ . "/../Views/help/Default.{$lang}.php";
        }

        // Final fallback: Default.en.php
        if (!is_file($viewFile)) {
            $viewFile = __DIR__ . "/../Views/help/Default.en.php";
        }

        // Render modal content
        $this->renderPartial('help/HelpBody', [
            'screen'   => $screen,
            'viewFile' => $viewFile,
        ]);
    }
}
