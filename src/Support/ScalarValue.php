<?php

declare(strict_types=1);

namespace Infocyph\Draw\Support;

final class ScalarValue
{
    public static function toInt(mixed $value, int $default = 0): int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => $default,
        };
    }
}
