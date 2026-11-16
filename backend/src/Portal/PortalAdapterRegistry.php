<?php

namespace App\Portal;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class PortalAdapterRegistry
{
    /** @var array<string, PortalAdapterInterface> */
    private array $adapters = [];

    /** @param iterable<PortalAdapterInterface> $adapters */
    public function __construct(#[AutowireIterator(PortalAdapterInterface::TAG)] iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->getCode()] = $adapter;
        }
    }

    /** @return array<string, PortalAdapterInterface> */
    public function all(): array
    {
        return $this->adapters;
    }

    public function has(string $code): bool
    {
        return isset($this->adapters[$code]);
    }

    public function get(string $code): PortalAdapterInterface
    {
        return $this->adapters[$code] ?? throw new \InvalidArgumentException(\sprintf('Nieznany portal "%s". Dostępne: %s', $code, implode(', ', array_keys($this->adapters))));
    }
}
