<?php
declare(strict_types=1);

namespace App\Services\Integrations;

abstract class AbstractConnectorAdapter implements ConnectorAdapterInterface
{
    public function supportsInterface(array $interfaceDefinition): bool
    {
        return true;
    }

    public function describeConnection(): array
    {
        return [
            'system_code' => $this->systemCode(),
            'display_name' => $this->displayName(),
            'default_auth_type' => $this->defaultAuthType(),
        ];
    }

    public function normaliseInboundPayload(mixed $payload, array $interfaceDefinition, array $context = []): array
    {
        return [
            'records' => is_array($payload) ? $payload : [],
            'meta' => [
                'adapter' => $this->systemCode(),
                'interface_code' => (string) ($interfaceDefinition['InterfaceCode'] ?? ''),
                'context' => $context,
            ],
        ];
    }

    public function buildOutboundPayload(array $records, array $interfaceDefinition, array $context = []): array
    {
        return [
            'records' => array_values($records),
            'meta' => [
                'adapter' => $this->systemCode(),
                'interface_code' => (string) ($interfaceDefinition['InterfaceCode'] ?? ''),
                'context' => $context,
            ],
        ];
    }
}
