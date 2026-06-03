<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\TransactionCalculationDiagnosticsModel;

require_once __DIR__ . '/../../shared/csrf.php';

final class TransactionCalculationDiagnosticsController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['CALC_ADMIN', 'SYSADMIN', 'ADMIN_ALL']],
    ];

    public function index(): void
    {
        $transactionId = max(0, (int) ($_GET['transaction_id'] ?? 0));
        $model = $this->buildModel();

        $inspection = $transactionId > 0
            ? $model->inspectTransaction($transactionId)
            : null;

        $this->render('transactioncalcdiagnostics/Index', [
            'title' => 'Transaction Calculation Diagnostics',
            'transactionId' => $transactionId,
            'inspection' => $inspection,
            'notFound' => $transactionId > 0 && $inspection === null,
        ]);
    }

    public function recalculate(): void
    {
        $this->requirePostWithCsrf();

        $transactionId = max(0, (int) ($_POST['transaction_id'] ?? 0));
        if ($transactionId <= 0) {
            $this->flashError('A valid Transaction ID is required.');
            header('Location: index.php?route=transaction-calc-diagnostics/index');
            exit;
        }

        try {
            $result = $this->runScenarioEngineRecalculation($transactionId);
            if (($result['exitCode'] ?? 1) === 0) {
                $this->flashSuccess('Transaction recalculated successfully.');
            } else {
                $this->flashError('Recalculation failed: ' . ($result['summary'] ?? 'Unknown error.'));
            }
        } catch (\Throwable $e) {
            $this->flashError('Recalculation failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=transaction-calc-diagnostics/index&transaction_id=' . $transactionId);
        exit;
    }

    private function buildModel(): TransactionCalculationDiagnosticsModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new TransactionCalculationDiagnosticsModel($this->db);
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
            header('Location: index.php?route=transaction-calc-diagnostics/index');
            exit;
        }
    }

    private function runScenarioEngineRecalculation(int $transactionId): array
    {
        $repoRoot = realpath(__DIR__ . '/../../..');
        if ($repoRoot === false) {
            throw new \RuntimeException('Could not resolve repository root.');
        }

        $projectPath = $repoRoot . DIRECTORY_SEPARATOR . 'scenario-engine' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'CBMS.ScenarioEngine.Runner';
        if (!is_dir($projectPath)) {
            throw new \RuntimeException('Scenario engine runner project was not found.');
        }

        $repoArg = '"' . str_replace('"', '""', $repoRoot) . '"';
        $projectArg = '"' . str_replace('"', '""', $projectPath) . '"';
        $command = 'cmd /d /c "cd /d ' . $repoArg . ' && dotnet run --project ' . $projectArg . ' -- execute-transaction ' . $transactionId . ' 2>&1"';

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        $summary = '';
        foreach ($output as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $summary = $line;
        }

        return [
            'exitCode' => $exitCode,
            'summary' => $summary,
            'output' => $output,
        ];
    }
}
