<?php

namespace Infocyph\Draw\State;

use Infocyph\Draw\Contracts\StateAdapterInterface;

class MemoryStateAdapter implements StateAdapterInterface
{
    private array $state = [];

    public function clear(): void
    {
        $this->state = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function increment(string $key, int $by = 1): int
    {
        $current = (int)($this->state[$key] ?? 0);
        $current += $by;
        $this->state[$key] = $current;
        return $current;
    }

    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }
}
