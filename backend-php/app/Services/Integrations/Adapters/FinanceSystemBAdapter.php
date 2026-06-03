<?php
declare(strict_types=1);

namespace App\Services\Integrations\Adapters;

use App\Services\Integrations\AbstractConnectorAdapter;

final class FinanceSystemBAdapter extends AbstractConnectorAdapter
{
    public function systemCode(): string
    {
        return 'FINANCE_B';
    }

    public function displayName(): string
    {
        return 'Finance System B';
    }

    public function defaultAuthType(): string
    {
        return 'oauth2_client_credentials';
    }

    public function buildOutboundPayload(array $records, array $interfaceDefinition, array $context = []): array
    {
        return [
            'batch' => [
                'interface_code' => (string) ($interfaceDefinition['InterfaceCode'] ?? ''),
                'record_count' => count($records),
                'context' => $context,
            ],
            'lines' => array_values($records),
        ];
    }
}
