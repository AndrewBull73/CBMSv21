<?php
declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../shared/env.php';

final class ApplicationLogController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['METRICS_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'index' => ['auth' => true, 'permsAny' => ['METRICS_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'download' => ['auth' => true, 'permsAny' => ['METRICS_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function index(): void
    {
        $files = $this->listLogFiles();
        $selectedName = trim((string) ($_GET['file'] ?? ''));
        $selectedPath = $this->resolveLogFile($selectedName, $files);
        if ($selectedPath === null && $files !== []) {
            $selectedPath = (string) ($files[0]['path'] ?? '');
            $selectedName = (string) ($files[0]['name'] ?? '');
        }

        $level = strtoupper(trim((string) ($_GET['level'] ?? '')));
        $search = trim((string) ($_GET['q'] ?? ''));
        $limit = (int) ($_GET['lines'] ?? 200);
        if ($limit < 50) {
            $limit = 50;
        } elseif ($limit > 500) {
            $limit = 500;
        }

        $entries = $selectedPath !== null && $selectedPath !== ''
            ? $this->readEntries($selectedPath, $level, $search, $limit)
            : [];

        $summary = [
            'displayed' => count($entries),
            'errors' => 0,
            'warns' => 0,
            'infos' => 0,
            'debugs' => 0,
        ];
        foreach ($entries as $entry) {
            $entryLevel = strtoupper((string) ($entry['level'] ?? ''));
            if ($entryLevel === 'ERROR') {
                $summary['errors']++;
            } elseif ($entryLevel === 'WARN') {
                $summary['warns']++;
            } elseif ($entryLevel === 'INFO') {
                $summary['infos']++;
            } elseif ($entryLevel === 'DEBUG') {
                $summary['debugs']++;
            }
        }

        $this->render('diagnostics/ApplicationLogView', [
            'title' => 'Application Log',
            'files' => $files,
            'selectedFile' => $selectedName,
            'selectedPath' => $selectedPath,
            'filters' => [
                'level' => $level,
                'q' => $search,
                'lines' => $limit,
            ],
            'entries' => $entries,
            'summary' => $summary,
        ]);
    }

    public function download(): void
    {
        $files = $this->listLogFiles();
        $selectedName = trim((string) ($_GET['file'] ?? ''));
        $selectedPath = $this->resolveLogFile($selectedName, $files);
        if ($selectedPath === null || !is_file($selectedPath)) {
            http_response_code(404);
            echo 'Log file not found.';
            return;
        }

        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . basename($selectedPath) . '"');
        readfile($selectedPath);
    }

    /**
     * @return array<int, array{name:string,path:string,size:int,modified:int}>
     */
    private function listLogFiles(): array
    {
        $files = [];
        foreach ($this->candidateLogDirectories() as $directory) {
            foreach (glob($directory . DIRECTORY_SEPARATOR . 'app-*.log') ?: [] as $path) {
                $realPath = realpath($path);
                if ($realPath === false || !is_file($realPath)) {
                    continue;
                }

                $name = basename($realPath);
                if (!preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $name)) {
                    continue;
                }

                $files[$realPath] = [
                    'name' => $name,
                    'path' => $realPath,
                    'size' => (int) (@filesize($realPath) ?: 0),
                    'modified' => (int) (@filemtime($realPath) ?: 0),
                ];
            }
        }

        usort($files, static fn(array $a, array $b): int => ($b['modified'] <=> $a['modified']) ?: strcmp($b['name'], $a['name']));
        return array_values($files);
    }

    /**
     * @param array<int, array{name:string,path:string,size:int,modified:int}> $files
     */
    private function resolveLogFile(string $selectedName, array $files): ?string
    {
        if ($selectedName === '' || !preg_match('/^app-\d{4}-\d{2}-\d{2}\.log$/', $selectedName)) {
            return null;
        }

        foreach ($files as $file) {
            if ((string) ($file['name'] ?? '') === $selectedName) {
                return (string) ($file['path'] ?? '');
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readEntries(string $path, string $level, string $search, int $limit): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $entries = [];

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $raw = trim((string) $lines[$index]);
            if ($raw === '') {
                continue;
            }

            $entry = $this->parseLine($raw);
            $entryLevel = strtoupper((string) ($entry['level'] ?? ''));

            if ($level !== '' && $entryLevel !== $level) {
                continue;
            }

            if ($search !== '' && stripos($raw, $search) === false) {
                continue;
            }

            $entries[] = $entry;
            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseLine(string $raw): array
    {
        $entry = [
            'timestamp' => '',
            'level' => '',
            'message' => $raw,
            'context' => null,
            'raw' => $raw,
        ];

        if (!preg_match('/^\[(?<timestamp>[^\]]+)\]\s+\[(?<level>[A-Z]+)\]\s+(?<rest>.*)$/', $raw, $matches)) {
            return $entry;
        }

        $entry['timestamp'] = trim((string) ($matches['timestamp'] ?? ''));
        $entry['level'] = strtoupper(trim((string) ($matches['level'] ?? '')));
        $rest = trim((string) ($matches['rest'] ?? ''));
        $entry['message'] = $rest;

        $offset = strpos($rest, '{');
        while ($offset !== false) {
            $candidateContext = trim(substr($rest, $offset));
            $decoded = json_decode($candidateContext, true);
            if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
                $entry['context'] = $decoded;
                $entry['message'] = rtrim(substr($rest, 0, $offset));
                break;
            }

            $offset = strpos($rest, '{', $offset + 1);
        }

        return $entry;
    }

    /**
     * @return array<int, string>
     */
    private function candidateLogDirectories(): array
    {
        $directories = [];
        $defaultDir = realpath(__DIR__ . '/../../logs');
        if ($defaultDir !== false) {
            $directories[] = $defaultDir;
        }

        $currentLogPath = envStr('APP_LOG_PATH', ($defaultDir !== false ? $defaultDir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log' : ''));
        $customDir = $currentLogPath !== '' ? realpath(dirname($currentLogPath)) : false;
        if ($customDir !== false) {
            $directories[] = $customDir;
        }

        return array_values(array_unique(array_filter($directories, static fn(string $path): bool => is_dir($path))));
    }
}
