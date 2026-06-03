<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class FullRecalculationController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['CALC_ADMIN', 'SYSADMIN', 'ADMIN_ALL']],
    ];

    public function index(): void
    {
        $availableScopes = $this->availableScopes();
        $availableTransactionTypes = $this->availableTransactionTypes();
        $selection = $this->normalizeSelection([
            'scope' => trim((string) ($_POST['scope'] ?? $_GET['scope'] ?? 'all')),
            'mode' => trim((string) ($_POST['mode'] ?? $_GET['mode'] ?? 'benchmark')),
            'ceiling_mode' => trim((string) ($_POST['ceiling_mode'] ?? $_GET['ceiling_mode'] ?? 'none')),
            'transaction_type_codes' => $_POST['transaction_type_codes'] ?? $_GET['transaction_type_codes'] ?? '',
        ]);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->requirePostWithCsrf();
            $action = trim((string) ($_POST['action'] ?? 'start'));

            try {
                if ($action === 'cancel') {
                    if ($this->cancelActiveJob()) {
                        $this->flashSuccess('Cancel requested. The recalculation worker is being stopped.');
                    } else {
                        $this->flashError('No active recalculation batch was found to cancel.');
                    }
                } else {
                    $this->startBackgroundJob($selection);
                    $this->flashSuccess('Recalculation batch is now running in the background. You can monitor progress here and cancel it if needed.');
                }
            } catch (\Throwable $e) {
                $this->flashError('Full recalculation failed: ' . $e->getMessage());
            }

            header('Location: index.php?route=full-recalculation/index');
            exit;
        }

        $job = $this->loadCurrentJobState();

        $this->render('fullrecalculation/Index', [
            'title' => 'Full Recalculation',
            'selection' => $selection,
            'job' => $job,
            'recentJobs' => $this->loadRecentJobs(15),
            'availableScopes' => $availableScopes,
            'availableModes' => $this->availableModes(),
            'availableCeilingModes' => $this->availableCeilingModes(),
            'availableTransactionTypes' => $availableTransactionTypes,
        ]);
    }

    public function log(): void
    {
        $jobId = trim((string) ($_GET['job'] ?? ''));
        if ($jobId === '' || !preg_match('/^[A-Za-z0-9_]+$/', $jobId)) {
            http_response_code(400);
            echo 'Invalid job ID.';
            return;
        }

        $logPath = $this->getJobDirectory() . DIRECTORY_SEPARATOR . 'fullrecalc_' . $jobId . '.log';
        if (!is_file($logPath)) {
            http_response_code(404);
            echo 'Log file not found.';
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline; filename="' . basename($logPath) . '"');
        readfile($logPath);
        exit;
    }

    private function availableScopes(): array
    {
        $roots = $this->loadRootCalculations();
        $scopes = [
            'all' => [
                'label' => 'All Root Calculations',
                'description' => $roots === []
                    ? 'Runs all discovered root calculations for the active Fiscal Year when they become available.'
                    : 'Runs every discovered root calculation for the active Fiscal Year.',
                'calculation_ids' => array_values(array_map(static fn(array $row): int => (int) $row['CalculationID'], $roots)),
                'is_chain' => false,
            ],
        ];

        foreach ($roots as $row) {
            $calculationId = (int) ($row['CalculationID'] ?? 0);
            if ($calculationId <= 0) {
                continue;
            }

            $scopeKey = 'calc_' . $calculationId;
            $calculationName = trim((string) ($row['CalculationName'] ?? ''));
            $childCalculationId = isset($row['ChildCalculationID']) && $row['ChildCalculationID'] !== null
                ? (int) $row['ChildCalculationID']
                : null;
            $isChain = $childCalculationId !== null && $childCalculationId > 0;
            $label = 'CalculationID ' . $calculationId;
            if ($calculationName !== '') {
                $label .= ' - ' . $calculationName;
            }

            $scopes[$scopeKey] = [
                'label' => $label,
                'description' => $isChain
                    ? 'Runs the full calculation chain starting from CalculationID ' . $calculationId . '.'
                    : 'Runs the standalone batch for CalculationID ' . $calculationId . '.',
                'calculation_ids' => [$calculationId],
                'is_chain' => $isChain,
                'root_calculation_id' => $calculationId,
            ];
        }

        return $scopes;
    }

    private function availableModes(): array
    {
        return [
            'transactional' => [
                'label' => 'Transaction by Transaction',
                'description' => 'Runs the non in-memory batch path, processing one transaction at a time with immediate persistence.',
            ],
            'benchmark' => [
                'label' => 'In-Memory Benchmark',
                'description' => 'Runs the calculations fully in memory and skips database write-back.',
            ],
            'writeback' => [
                'label' => 'Bulk Write-Back',
                'description' => 'Runs the calculations in memory and bulk writes results back to the database.',
            ],
        ];
    }

    private function availableCeilingModes(): array
    {
        return [
            'none' => [
                'label' => 'No Ceiling Checks',
                'description' => 'Runs the recalculation without ceiling validation.',
            ],
            'validate' => [
                'label' => 'Validate Only',
                'description' => 'Runs stored-procedure ceiling validation after persistence without updating ceiling status columns.',
            ],
            'persist' => [
                'label' => 'Validate + Persist Status',
                'description' => 'Runs stored-procedure ceiling validation after persistence and updates ceiling status fields on tblTransactionInput.',
            ],
        ];
    }

    private function startBackgroundJob(array $selection): void
    {
        $existing = $this->loadCurrentJobState();
        if ($existing !== null && $this->jobIsRunning($existing)) {
            throw new \RuntimeException('A recalculation batch is already running. Cancel it or wait for it to finish.');
        }

        $commands = $this->buildCommandPlan($selection);
        if ($commands === []) {
            throw new \RuntimeException('No recalculation commands were generated.');
        }

        $jobId = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $jobDir = $this->getJobDirectory();
        $statePath = $jobDir . DIRECTORY_SEPARATOR . 'fullrecalc_' . $jobId . '.state.json';
        $logPath = $jobDir . DIRECTORY_SEPARATOR . 'fullrecalc_' . $jobId . '.log';
        $stopFile = $jobDir . DIRECTORY_SEPARATOR . 'fullrecalc_' . $jobId . '.stop';
        $jobDefinitionPath = $jobDir . DIRECTORY_SEPARATOR . 'fullrecalc_' . $jobId . '.job.json';

        $jobDefinition = [
            'jobId' => $jobId,
            'selection' => $selection,
            'commands' => array_values($commands),
            'repoRoot' => $this->getRepoRoot(),
            'logPath' => $logPath,
            'stopFile' => $stopFile,
            'statePath' => $statePath,
            'createdAt' => date('c'),
        ];

        file_put_contents($jobDefinitionPath, json_encode($jobDefinition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $initialState = [
            'jobId' => $jobId,
            'status' => 'queued',
            'createdAt' => date('c'),
            'startedAt' => null,
            'finishedAt' => null,
            'selection' => $selection,
            'steps' => [],
            'currentStepIndex' => null,
            'currentStepLabel' => null,
            'wrapperPid' => null,
            'childPid' => null,
            'logPath' => $logPath,
            'statePath' => $statePath,
            'stopFile' => $stopFile,
            'jobDefinitionPath' => $jobDefinitionPath,
            'lastMessage' => 'Queued.',
        ];
        file_put_contents($statePath, json_encode($initialState, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($this->getCurrentJobPointerPath(), $statePath);

        $scriptPath = $this->getRepoRoot() . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'run_full_recalc_job.ps1';
        if (!is_file($scriptPath)) {
            throw new \RuntimeException('Background recalculation script was not found.');
        }

        $cmd = sprintf(
            'cmd.exe /d /c start "" /min powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%s" -JobDefinitionPath "%s"',
            $scriptPath,
            $jobDefinitionPath
        );

        $handle = @popen($cmd, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not launch the background recalculation worker.');
        }

        @pclose($handle);
    }

    private function cancelActiveJob(): bool
    {
        $job = $this->loadCurrentJobState();
        if ($job === null || !$this->jobIsRunning($job)) {
            return false;
        }

        $stopFile = (string) ($job['stopFile'] ?? '');
        if ($stopFile !== '') {
            @file_put_contents($stopFile, "stop\n");
        }

        $childPid = (int) ($job['childPid'] ?? 0);
        $wrapperPid = (int) ($job['wrapperPid'] ?? 0);
        foreach (array_unique(array_filter([$childPid, $wrapperPid])) as $pid) {
            @exec('taskkill /PID ' . (int) $pid . ' /T /F 2>NUL');
        }

        $job['status'] = 'cancel_requested';
        $job['lastMessage'] = 'Cancel requested.';
        $job['finishedAt'] = $job['finishedAt'] ?? date('c');
        $this->writeJobState($job);
        return true;
    }

    private function loadCurrentJobState(): ?array
    {
        $pointerPath = $this->getCurrentJobPointerPath();
        if (!is_file($pointerPath)) {
            return null;
        }

        $statePath = trim((string) @file_get_contents($pointerPath));
        if ($statePath === '' || !is_file($statePath)) {
            return null;
        }

        $raw = @file_get_contents($statePath);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $job = json_decode($this->stripUtf8Bom($raw), true);
        if (!is_array($job)) {
            return null;
        }

        $job = $this->refreshJobState($job);
        if (!$this->jobIsRunning($job)) {
            $finishedAtRaw = trim((string) ($job['finishedAt'] ?? ''));
            $finishedAt = $finishedAtRaw !== '' ? strtotime($finishedAtRaw) : false;
            if ($finishedAt === false || (time() - $finishedAt) > 15) {
                $this->clearCurrentJobPointer($statePath);
                return null;
            }
        }

        if (!empty($job['logPath']) && is_file((string) $job['logPath'])) {
            $job['logTail'] = $this->tailFile((string) $job['logPath'], 200);
        } else {
            $job['logTail'] = [];
        }

        return $job;
    }

    private function refreshJobState(array $job): array
    {
        if (!$this->jobIsRunning($job)) {
            return $job;
        }

        if ((string) ($job['status'] ?? '') === 'queued' && empty($job['startedAt'])) {
            $createdAtRaw = (string) ($job['createdAt'] ?? '');
            $createdAt = $createdAtRaw !== '' ? strtotime($createdAtRaw) : false;
            $ageSeconds = $createdAt !== false ? (time() - $createdAt) : 0;

            if ($ageSeconds < 15) {
                return $job;
            }

            $job['status'] = 'failed';
            $job['finishedAt'] = $job['finishedAt'] ?? date('c');
            $job['lastMessage'] = 'Background worker did not start. Please retry. If this keeps happening, check the PHP worker launch permissions.';
            $this->writeJobState($job);
            return $job;
        }

        $wrapperPid = (int) ($job['wrapperPid'] ?? 0);
        $childPid = (int) ($job['childPid'] ?? 0);
        $wrapperAlive = $wrapperPid > 0 && $this->processIsRunning($wrapperPid);
        $childAlive = $childPid > 0 && $this->processIsRunning($childPid);

        if ($wrapperAlive || $childAlive) {
            return $job;
        }

        $stopFile = (string) ($job['stopFile'] ?? '');
        $lastStep = $this->getLastStep($job);
        $stepError = trim((string) ($lastStep['errorMessage'] ?? ''));
        $job['status'] = ($stopFile !== '' && is_file($stopFile)) ? 'cancelled' : 'failed';
        $job['finishedAt'] = $job['finishedAt'] ?? date('c');
        $job['lastMessage'] = $job['status'] === 'cancelled'
            ? 'Cancelled before completion.'
            : ($stepError !== '' ? $stepError : 'Worker stopped unexpectedly.');
        $job['wrapperPid'] = null;
        $job['childPid'] = null;
        $this->writeJobState($job);
        return $job;
    }

    private function jobIsRunning(array $job): bool
    {
        return in_array((string) ($job['status'] ?? ''), ['queued', 'running', 'cancel_requested'], true);
    }

    private function buildCommandPlan(array $selection): array
    {
        $selection = $this->normalizeSelection($selection);
        $scope = (string) ($selection['scope'] ?? 'all');
        $mode = (string) ($selection['mode'] ?? 'benchmark');
        $ceilingMode = (string) ($selection['ceiling_mode'] ?? 'none');
        $filterArgs = $this->buildFilterArgs($selection);
        $ceilingArgs = $this->buildCeilingArgs($ceilingMode);
        $availableScopes = $this->availableScopes();
        $scopeMeta = $availableScopes[$scope] ?? $availableScopes['all'] ?? null;
        if (!is_array($scopeMeta)) {
            return [];
        }

        $rootCalculationIds = array_values(array_filter(
            array_map('intval', $scopeMeta['calculation_ids'] ?? []),
            static fn(int $value): bool => $value > 0
        ));
        if ($rootCalculationIds === []) {
            return [];
        }

        $commands = [];
        foreach ($rootCalculationIds as $calculationId) {
            $singleScopeMeta = $availableScopes['calc_' . $calculationId] ?? $scopeMeta;
            $isChain = !empty($singleScopeMeta['is_chain']);
            $baseCommand = $this->buildCalculationCommand($calculationId, $mode, $isChain);
            if ($filterArgs !== '') {
                $baseCommand .= ' ' . $filterArgs;
            }
            if ($ceilingArgs !== '' && $mode !== 'benchmark') {
                $baseCommand .= ' ' . $ceilingArgs;
            }

            $label = trim((string) ($singleScopeMeta['label'] ?? ('CalculationID ' . $calculationId)));
            $commands[] = [
                'label' => $label,
                'command' => $baseCommand,
            ];
        }

        return $commands;
    }

    private function buildCalculationCommand(int $calculationId, string $mode, bool $isChain): string
    {
        if ($isChain) {
            return match ($mode) {
                'transactional' => 'dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch ' . $calculationId . ' --progress=250',
                'writeback' => 'dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-chain-batch-bulk ' . $calculationId . ' --progress=5000',
                default => 'dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- benchmark-transaction-chain-batch ' . $calculationId . ' --progress=5000',
            };
        }

        return match ($mode) {
            'transactional' => 'dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch ' . $calculationId . ' --progress=1000',
            'writeback' => 'dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch-bulk ' . $calculationId . ' --progress=10000',
            default => 'dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- benchmark-transaction-batch ' . $calculationId . ' --progress=10000',
        };
    }

    private function buildFilterArgs(array $selection): string
    {
        $fiscalYearId = (int) ($selection['fiscal_year_id'] ?? 0);
        $versionId = (int) ($selection['version_id'] ?? 0);
        if ($fiscalYearId <= 0 || $versionId <= 0) {
            throw new \RuntimeException('Select a Fiscal Year and Version before running a recalculation batch.');
        }

        $args = [
            '--fy=' . $fiscalYearId,
            '--version=' . $versionId,
        ];

        $dataObjects = is_array($selection['effective_data_object_code_list'] ?? null)
            ? $selection['effective_data_object_code_list']
            : $this->normalizeCsvFilter((string) ($selection['data_object_codes'] ?? ''));
        if ($dataObjects !== []) {
            $args[] = '--dataobjects=' . implode(',', $dataObjects);
        }

        $transactionTypes = is_array($selection['transaction_type_code_list'] ?? null)
            ? $selection['transaction_type_code_list']
            : $this->normalizeCsvFilter((string) ($selection['transaction_type_codes'] ?? ''));
        if ($transactionTypes !== []) {
            $args[] = '--transactiontypes=' . implode(',', $transactionTypes);
        }

        return implode(' ', $args);
    }

    private function buildCeilingArgs(string $ceilingMode): string
    {
        return $ceilingMode === 'none' ? '' : '--ceiling-mode=' . $ceilingMode;
    }

    private function normalizeSelection(array $selection): array
    {
        $availableScopes = $this->availableScopes();
        $mode = (string) ($selection['mode'] ?? 'benchmark');
        $ceilingMode = (string) ($selection['ceiling_mode'] ?? 'none');
        $scope = (string) ($selection['scope'] ?? 'all');

        if (!array_key_exists($scope, $availableScopes)) {
            $scope = 'all';
        }
        if (!array_key_exists($mode, $this->availableModes())) {
            $mode = 'benchmark';
        }
        if (!array_key_exists($ceilingMode, $this->availableCeilingModes())) {
            $ceilingMode = 'none';
        }
        if ($mode === 'benchmark') {
            $ceilingMode = 'none';
        }

        $ctxFiscalYearId = (int) SessionHelper::get('FiscalYearID', 0);
        $ctxVersionId = (int) SessionHelper::get('VersionID', 0);
        $ctxDataObjectCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));
        $ctxDataObjectName = trim((string) SessionHelper::get('scope.dataobject_name', ''));
        if (strcasecmp($ctxDataObjectCode, 'ALL') === 0) {
            $ctxDataObjectCode = '';
            $ctxDataObjectName = '';
        }

        $manualDataObjects = $this->normalizeCsvFilter((string) ($selection['data_object_codes'] ?? ''));
        $transactionTypes = $this->normalizeMultiValueFilter($selection['transaction_type_codes'] ?? '');
        $effectiveDataObjects = $manualDataObjects !== []
            ? $manualDataObjects
            : ($ctxDataObjectCode !== '' ? [$ctxDataObjectCode] : []);

        $selection['scope'] = $scope;
        $selection['mode'] = $mode;
        $selection['ceiling_mode'] = $ceilingMode;
        $selection['fiscal_year_id'] = $ctxFiscalYearId;
        $selection['version_id'] = $ctxVersionId;
        $selection['context_data_object_code'] = $ctxDataObjectCode;
        $selection['context_data_object_name'] = $ctxDataObjectName;
        $selection['data_object_codes'] = '';
        $selection['transaction_type_codes'] = implode(', ', $transactionTypes);
        $selection['transaction_type_code_values'] = $transactionTypes;
        $selection['effective_data_object_code_list'] = $effectiveDataObjects;
        $selection['effective_data_object_codes'] = implode(', ', $effectiveDataObjects);
        $selection['transaction_type_code_list'] = $transactionTypes;
        return $selection;
    }

    private function loadRootCalculations(): array
    {
        if (!$this->db instanceof \PDO) {
            return [];
        }

        $fiscalYearId = (int) SessionHelper::get('FiscalYearID', 0);
        if ($fiscalYearId <= 0) {
            return [];
        }

        $sql = "
            WITH ChildRefs AS (
                SELECT DISTINCT ChildCalculationID
                FROM dbo.tblCalculations
                WHERE FiscalYearID = ?
                  AND ChildCalculationID IS NOT NULL
            )
            SELECT
                c.CalculationID,
                c.CalculationName,
                c.ChildCalculationID,
                c.GenerateTransaction
            FROM dbo.tblCalculations c
            LEFT JOIN ChildRefs r
              ON r.ChildCalculationID = c.CalculationID
            WHERE c.FiscalYearID = ?
              AND r.ChildCalculationID IS NULL
            ORDER BY c.CalculationID ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$fiscalYearId, $fiscalYearId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_values(array_filter($rows, static function (array $row): bool {
            return (int) ($row['CalculationID'] ?? 0) > 0;
        }));
    }

    private function normalizeCsvFilter(string $raw): array
    {
        $parts = preg_split('/[\s,]+/', trim($raw)) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn(string $value): bool => $value !== ''));
        return array_values(array_unique($parts));
    }

    private function normalizeMultiValueFilter(array|string $raw): array
    {
        if (is_array($raw)) {
            $parts = array_map(static fn(mixed $value): string => trim((string) $value), $raw);
            $parts = array_values(array_filter($parts, static fn(string $value): bool => $value !== ''));
            return array_values(array_unique($parts));
        }

        return $this->normalizeCsvFilter((string) $raw);
    }

    private function availableTransactionTypes(): array
    {
        if (!$this->db instanceof \PDO) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT TransactionTypeCode, TransactionTypeName
            FROM dbo.tblTransactionTypes
            ORDER BY TransactionTypeCode ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function getRepoRoot(): string
    {
        $repoRoot = realpath(__DIR__ . '/../../..');
        if ($repoRoot === false) {
            throw new \RuntimeException('Could not resolve repository root.');
        }
        return $repoRoot;
    }

    private function getJobDirectory(): string
    {
        $path = $this->getRepoRoot() . DIRECTORY_SEPARATOR . 'backend-php' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'fullrecalculation';
        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException('Could not create the recalculation job directory.');
        }
        return $path;
    }

    private function getCurrentJobPointerPath(): string
    {
        return $this->getJobDirectory() . DIRECTORY_SEPARATOR . 'current_job.txt';
    }

    private function clearCurrentJobPointer(string $expectedStatePath = ''): void
    {
        $pointerPath = $this->getCurrentJobPointerPath();
        if (!is_file($pointerPath)) {
            return;
        }

        if ($expectedStatePath !== '') {
            $current = trim((string) @file_get_contents($pointerPath));
            if ($current !== '' && strcasecmp($current, $expectedStatePath) !== 0) {
                return;
            }
        }

        @unlink($pointerPath);
    }

    private function processIsRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        $output = [];
        @exec('tasklist /FI "PID eq ' . $pid . '" /NH 2>NUL', $output);
        if ($output === []) {
            return false;
        }

        foreach ($output as $line) {
            if (stripos($line, 'No tasks are running') !== false) {
                return false;
            }
            if (preg_match('/\s' . preg_quote((string) $pid, '/') . '\s/', $line)) {
                return true;
            }
        }

        return false;
    }

    private function tailFile(string $path, int $maxLines): array
    {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        return array_slice($lines, -1 * max(1, $maxLines));
    }

    private function loadRecentJobs(int $maxJobs): array
    {
        $files = glob($this->getJobDirectory() . DIRECTORY_SEPARATOR . 'fullrecalc_*.state.json') ?: [];
        $rows = [];
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            if ($raw === false || trim($raw) === '') {
                continue;
            }

            $job = json_decode($this->stripUtf8Bom($raw), true);
            if (!is_array($job)) {
                continue;
            }

            $jobId = (string) ($job['jobId'] ?? '');
            $selection = is_array($job['selection'] ?? null) ? $job['selection'] : [];
            $steps = is_array($job['steps'] ?? null) ? $job['steps'] : [];
            $scope = (string) ($selection['scope'] ?? 'all');
            $mode = (string) ($selection['mode'] ?? 'benchmark');
            $status = (string) ($job['status'] ?? 'unknown');
            $createdAt = (string) ($job['createdAt'] ?? '');
            $startedAt = (string) ($job['startedAt'] ?? '');
            $finishedAt = (string) ($job['finishedAt'] ?? '');
            $headline = trim((string) ($job['lastMessage'] ?? ''));
            if ($headline === '' && $steps !== []) {
                $lastStep = end($steps);
                if (is_array($lastStep)) {
                    $headline = trim((string) (($lastStep['outputSummary'] ?? '') !== '' ? $lastStep['outputSummary'] : ($lastStep['errorMessage'] ?? '')));
                }
            }

            $sortTimestamp = $this->resolveJobSortTimestamp($createdAt, $startedAt, $finishedAt, $jobId);

            $rows[] = [
                'job_id' => $jobId,
                'status' => $status,
                'scope' => $scope,
                'scope_label' => (string) ($this->availableScopes()[$scope]['label'] ?? $scope),
                'mode' => $mode,
                'mode_label' => (string) ($this->availableModes()[$mode]['label'] ?? $mode),
                'created_at' => $createdAt,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'steps' => count($steps),
                'headline' => $headline,
                'log_exists' => !empty($job['logPath']) && is_file((string) $job['logPath']),
                'sort_ts' => $sortTimestamp,
                'is_current' => false,
            ];
        }

        usort(
            $rows,
            static fn(array $a, array $b): int => (($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0))
                ?: strcmp((string) ($b['job_id'] ?? ''), (string) ($a['job_id'] ?? ''))
        );

        $currentJobId = $this->loadCurrentJobId();
        if ($currentJobId !== '') {
            foreach ($rows as $index => $row) {
                if ((string) ($row['job_id'] ?? '') !== $currentJobId) {
                    continue;
                }

                $rows[$index]['is_current'] = true;
                if ($index > 0) {
                    $currentRow = $rows[$index];
                    array_splice($rows, $index, 1);
                    array_unshift($rows, $currentRow);
                }
                break;
            }
        }

        return array_slice($rows, 0, max(1, $maxJobs));
    }

    private function resolveJobSortTimestamp(string $createdAt, string $startedAt, string $finishedAt, string $jobId): int
    {
        foreach ([$finishedAt, $startedAt, $createdAt] as $candidate) {
            if ($candidate !== '') {
                $timestamp = strtotime($candidate);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            }
        }

        if (preg_match('/^(\d{8}_\d{6})_/', $jobId, $matches) === 1) {
            $timestamp = strtotime($matches[1]);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return 0;
    }

    private function loadCurrentJobId(): string
    {
        $pointerPath = $this->getCurrentJobPointerPath();
        if (!is_file($pointerPath)) {
            return '';
        }

        $statePath = trim((string) @file_get_contents($pointerPath));
        if ($statePath === '' || !is_file($statePath)) {
            return '';
        }

        $raw = @file_get_contents($statePath);
        if ($raw === false || trim($raw) === '') {
            return '';
        }

        $job = json_decode($this->stripUtf8Bom($raw), true);
        if (!is_array($job)) {
            return '';
        }

        return trim((string) ($job['jobId'] ?? ''));
    }

    private function writeJobState(array $job): void
    {
        $statePath = (string) ($job['statePath'] ?? '');
        if ($statePath === '') {
            return;
        }

        @file_put_contents($statePath, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function stripUtf8Bom(string $raw): string
    {
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            return substr($raw, 3);
        }

        return $raw;
    }

    private function getLastStep(array $job): array
    {
        $steps = is_array($job['steps'] ?? null) ? $job['steps'] : [];
        if ($steps === []) {
            return [];
        }

        $last = end($steps);
        return is_array($last) ? $last : [];
    }

    private function requirePostWithCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            exit;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=full-recalculation/index');
            exit;
        }
    }
}
