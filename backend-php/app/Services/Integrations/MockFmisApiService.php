<?php
declare(strict_types=1);

namespace App\Services\Integrations;

final class MockFmisApiService
{
    public function acceptBudgetExport(array $payload): array
    {
        $records = $this->extractRecords($payload, ['budgetLines', 'records', 'lines']);
        $messages = [];
        $acceptedRecords = [];
        $accepted = 0;
        $failed = 0;

        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                $failed++;
                $messages[] = $this->message($index, 'record', 'Record must be an object.');
                continue;
            }

            foreach (['recordCode', 'programCode', 'economicCode', 'approvedBudgetAmount'] as $field) {
                if (!array_key_exists($field, $record) || trim((string) $record[$field]) === '') {
                    $failed++;
                    $messages[] = $this->message($index, $field, 'Required field is missing.');
                    continue 2;
                }
            }

            if (!is_numeric($record['approvedBudgetAmount'])) {
                $failed++;
                $messages[] = $this->message($index, 'approvedBudgetAmount', 'Amount must be numeric.');
                continue;
            }

            if ((float) $record['approvedBudgetAmount'] < 0) {
                $failed++;
                $messages[] = $this->message($index, 'approvedBudgetAmount', 'Amount cannot be negative.');
                continue;
            }

            $accepted++;
            $acceptedRecords[] = $record;
        }

        return $this->response('budget_export', $records, $accepted, $failed, $messages, $acceptedRecords);
    }

    public function acceptActualsImport(array $payload): array
    {
        $records = $this->extractRecords($payload, ['actuals', 'actualLines', 'records', 'lines']);
        $messages = [];
        $acceptedRecords = [];
        $accepted = 0;
        $failed = 0;

        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                $failed++;
                $messages[] = $this->message($index, 'record', 'Record must be an object.');
                continue;
            }

            foreach (['transactionReference', 'fiscalYear', 'period', 'economicCode', 'actualAmount'] as $field) {
                if (!array_key_exists($field, $record) || trim((string) $record[$field]) === '') {
                    $failed++;
                    $messages[] = $this->message($index, $field, 'Required field is missing.');
                    continue 2;
                }
            }

            if (!is_numeric($record['actualAmount'])) {
                $failed++;
                $messages[] = $this->message($index, 'actualAmount', 'Amount must be numeric.');
                continue;
            }

            $accepted++;
            $acceptedRecords[] = $record;
        }

        return $this->response('actuals_import', $records, $accepted, $failed, $messages, $acceptedRecords);
    }

    private function extractRecords(array $payload, array $candidateKeys): array
    {
        foreach ($candidateKeys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        return [];
    }

    private function response(string $operation, array $records, int $accepted, int $failed, array $messages, array $acceptedRecords): array
    {
        $ok = $accepted > 0 && $failed === 0;
        $partial = $accepted > 0 && $failed > 0;

        return [
            'ok' => $ok,
            'partial' => $partial,
            'operation' => $operation,
            'mock' => true,
            'external_system' => 'MOCK_FMIS',
            'correlation_id' => 'MOCK-' . strtoupper(bin2hex(random_bytes(6))),
            'received_at_utc' => gmdate('Y-m-d H:i:s'),
            'record_count' => count($records),
            'accepted_count' => $accepted,
            'failed_count' => $failed,
            'status' => $ok ? 'accepted' : ($partial ? 'partially_accepted' : 'rejected'),
            'messages' => $messages,
            'accepted_records' => $acceptedRecords,
        ];
    }

    private function message(int $index, string $field, string $text): array
    {
        return [
            'row' => $index + 1,
            'field' => $field,
            'message' => $text,
        ];
    }
}
