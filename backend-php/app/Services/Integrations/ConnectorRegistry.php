<?php
declare(strict_types=1);

namespace App\Services\Integrations;

use App\Services\Integrations\Adapters\FinanceSystemAAdapter;
use App\Services\Integrations\Adapters\FinanceSystemBAdapter;

final class ConnectorRegistry
{
    /** @var array<string, ConnectorAdapterInterface> */
    private array $adapters = [];

    public function __construct(?array $adapters = null)
    {
        $defaults = $adapters ?? [
            new FinanceSystemAAdapter(),
            new FinanceSystemBAdapter(),
        ];

        foreach ($defaults as $adapter) {
            if ($adapter instanceof ConnectorAdapterInterface) {
                $this->register($adapter);
            }
        }
    }

    public function register(ConnectorAdapterInterface $adapter): void
    {
        $this->adapters[strtoupper($adapter->systemCode())] = $adapter;
    }

    public function has(string $systemCode): bool
    {
        return isset($this->adapters[strtoupper(trim($systemCode))]);
    }

    public function get(string $systemCode): ConnectorAdapterInterface
    {
        $key = strtoupper(trim($systemCode));
        if (!isset($this->adapters[$key])) {
            throw new \RuntimeException('No connector adapter is registered for system code ' . $systemCode . '.');
        }

        return $this->adapters[$key];
    }

    public function all(): array
    {
        ksort($this->adapters, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->adapters;
    }
}
