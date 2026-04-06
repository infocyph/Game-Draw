<?php

namespace Infocyph\Draw\Contracts;

interface StateAdapterInterface
{
    public function clear(): void;
    public function get(string $key, mixed $default = null): mixed;

    public function increment(string $key, int $by = 1): int;

    public function set(string $key, mixed $value): void;
}
