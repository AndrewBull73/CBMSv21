<?php
declare(strict_types=1);

namespace App\Services\Integrations\Adapters;

use App\Services\Integrations\AbstractConnectorAdapter;

final class FinanceSystemAAdapter extends AbstractConnectorAdapter
{
    public function systemCode(): string
    {
        return 'FINANCE_A';
    }

    public function displayName(): string
    {
        return 'Finance System A';
    }

    public function defaultAuthType(): string
    {
        return 'api_key';
    }

    public function normaliseInboundPayload(mixed $payload, array $interfaceDefinition, array $context = []): array
    {
        $records = [];
        if (is_array($payload)) {
            $records = isset($payload['items']) && is_array($payload['items'])
                ? array_values($payload['items'])
                : array_values($payload);
        }

        return [
            'records' => $records,
            'meta' => [
                'adapter' => $this->systemCode(),
                'source' => 'api',
                'interface_code' => (string) ($interfaceDefinition['InterfaceCode'] ?? ''),
                'context' => $context,
            ],
        ];
    }
}
