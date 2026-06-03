<?php
declare(strict_types=1);

namespace App\Services\Integrations;

interface ConnectorAdapterInterface
{
    public function systemCode(): string;

    public function displayName(): string;

    public function defaultAuthType(): string;

    public function supportsInterface(array $interfaceDefinition): bool;

    public function describeConnection(): array;

    public function normaliseInboundPayload(mixed $payload, array $interfaceDefinition, array $context = []): array;

    public function buildOutboundPayload(array $records, array $interfaceDefinition, array $context = []): array;
}
