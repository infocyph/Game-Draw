<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers\Support;

use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Support\ScalarValue;

trait NormalizesHandlerInput
{
    protected function boolValue(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (bool) $value,
        };
    }

    protected function floatValue(mixed $value, float $default): float
    {
        return match (true) {
            is_int($value) => (float) $value,
            is_float($value) => $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => $default,
        };
    }

    protected function intValue(mixed $value, int $default): int
    {
        return ScalarValue::toInt($value, $default);
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeAssocArray(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new ValidationException("{$field} must be an array.");
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return list<array<string, mixed>>
     */
    protected function normalizeRows(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new ValidationException('Each item must be an array.');
            }

            $row = [];
            foreach ($item as $key => $value) {
                $row[(string) $key] = $value;
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    protected function numericAsFloat(mixed $value, string $field): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new ValidationException("{$field} must be numeric.");
    }

    protected function optionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function requireArray(mixed $value, string $message): array
    {
        if (!is_array($value)) {
            throw new ValidationException($message);
        }

        return $value;
    }

    protected function requiredString(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new ValidationException("{$field} is required and must be a non-empty string.");
        }

        return trim($value);
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function requireNonEmptyArray(mixed $value, string $message): array
    {
        $array = $this->requireArray($value, $message);
        if ($array === []) {
            throw new ValidationException($message);
        }

        return $array;
    }
}
