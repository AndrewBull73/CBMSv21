<?php
declare(strict_types=1);

namespace App\Shared;

final class CbmsModuleCatalog
{
    public const MODULES = [
        'Home',
        'Planning',
        'Strategic Budgeting',
        'Budget Execution',
        'Analytics',
        'Financial Configuration',
        'System Configuration',
        'Workflow Operations',
        'Training',
        'Testing Scripts',
        'Security & Access',
        'Readiness & Diagnostics',
        'Administration',
        'Integration',
        'Reporting',
    ];

    private const ALIASES = [
        'Base Configuration' => 'System Configuration',
        'CBMS Fundamentals' => 'Home',
        'Strategic Framework' => 'Planning',
        'Strategy' => 'Strategic Budgeting',
        'Workflow' => 'Workflow Operations',
        'Security' => 'Security & Access',
        'Users' => 'Security & Access',
        'Testing' => 'Testing Scripts',
        'Diagnostics' => 'Readiness & Diagnostics',
        'Readiness' => 'Readiness & Diagnostics',
        'Integrations' => 'Integration',
        'Reports' => 'Reporting',
    ];

    public static function all(): array
    {
        return self::MODULES;
    }

    public static function canonicalize(string $module): string
    {
        $module = trim($module);
        if ($module === '') {
            return '';
        }

        foreach (self::MODULES as $canonical) {
            if (strcasecmp($module, $canonical) === 0) {
                return $canonical;
            }
        }

        foreach (self::ALIASES as $alias => $canonical) {
            if (strcasecmp($module, $alias) === 0) {
                return $canonical;
            }
        }

        return $module;
    }

    public static function mergeWithObserved(array $observedModules): array
    {
        $modules = [];
        foreach (self::MODULES as $module) {
            $modules[$module] = $module;
        }

        foreach ($observedModules as $module) {
            $canonical = self::canonicalize((string) $module);
            if ($canonical !== '') {
                $modules[$canonical] = $canonical;
            }
        }

        natcasesort($modules);
        return array_values($modules);
    }
}
