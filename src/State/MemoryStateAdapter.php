<?php

declare(strict_types=1);

namespace Infocyph\Draw\State;

use Infocyph\Draw\Contracts\StateAdapterInterface;

class MemoryStateAdapter implements StateAdapterInterface
{
    /**
     * @var array<string, mixed>
     */
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
        $current = $this->toInt($this->state[$key] ?? 0);
        $current += $by;
        $this->state[$key] = $current;

        return $current;
    }

    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    private function toInt(mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => 0,
        };
    }
}
