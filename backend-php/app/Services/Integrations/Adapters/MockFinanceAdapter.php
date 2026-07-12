<?php
declare(strict_types=1);

namespace App\Services\Integrations\Adapters;

use App\Services\Integrations\AbstractConnectorAdapter;

final class MockFinanceAdapter extends AbstractConnectorAdapter
{
    public function systemCode(): string
    {
        return 'MOCK_FINANCE';
    }

    public function displayName(): string
    {
        return 'Mock Finance System';
    }

    public function defaultAuthType(): string
    {
        return 'none';
    }

    public function buildOutboundPayload(array $records, array $interfaceDefinition, array $context = []): array
    {
        return [
            'mock_dispatch' => true,
            'interface_code' => (string) ($interfaceDefinition['InterfaceCode'] ?? ''),
            'record_count' => count($records),
            'context' => $context,
            'budget_lines' => array_values($records),
        ];
    }
}
