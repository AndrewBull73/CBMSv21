<?php
declare(strict_types=1);

namespace App\Services\Integrations;

final class IntegrationManager
{
    public function __construct(private ConnectorRegistry $registry)
    {
    }

    public function adapterForSystem(array $systemDefinition): ConnectorAdapterInterface
    {
        $systemCode = (string) ($systemDefinition['SystemCode'] ?? '');
        if ($systemCode === '') {
            throw new \RuntimeException('Integration system definition is missing SystemCode.');
        }

        return $this->registry->get($systemCode);
    }

    public function describeRegisteredAdapters(): array
    {
        $rows = [];
        foreach ($this->registry->all() as $adapter) {
            $rows[] = $adapter->describeConnection();
        }

        return $rows;
    }

    public function buildExecutionContext(array $interfaceDefinition, array $requestContext = []): array
    {
        $context = [
            'fiscal_year_id' => $requestContext['FiscalYearID'] ?? null,
            'version_id' => $requestContext['VersionID'] ?? null,
            'data_object_code' => $requestContext['DataObjectCode'] ?? null,
            'trigger_source' => $requestContext['TriggerSourceCode'] ?? 'manual',
        ];

        return [
            'interface_code' => (string) ($interfaceDefinition['InterfaceCode'] ?? ''),
            'direction' => (string) ($interfaceDefinition['DirectionCode'] ?? ''),
            'module_code' => (string) ($interfaceDefinition['ModuleCode'] ?? ''),
            'entity_code' => (string) ($interfaceDefinition['EntityCode'] ?? ''),
            'transport' => [
                'endpoint_path' => (string) ($interfaceDefinition['EndpointPath'] ?? ''),
                'http_method' => (string) ($interfaceDefinition['HttpMethod'] ?? ''),
                'payload_format' => (string) ($interfaceDefinition['PayloadFormat'] ?? ''),
                'timeout_seconds' => (int) ($interfaceDefinition['TimeoutSeconds'] ?? 0),
                'batch_size' => (int) ($interfaceDefinition['BatchSize'] ?? 0),
            ],
            'context' => $context,
        ];
    }
}
