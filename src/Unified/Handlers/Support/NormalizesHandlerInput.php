<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers\Support;

use Infocyph\Draw\Exceptions\ValidationException;

trait NormalizesHandlerInput
{
    protected function boolValue(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new ValidationException("{$field} must be a boolean.");
    }

    protected function floatValue(mixed $value, float $default): float
    {
        if ($value === null) {
            return $default;
        }
        if (is_int($value) || is_float($value)) {
            $normalized = (float) $value;
        } elseif (is_string($value) && is_numeric($value)) {
            $normalized = (float) $value;
        } else {
            throw new ValidationException('Numeric value must be finite.');
        }

        if (!is_finite($normalized)) {
            throw new ValidationException('Numeric value must be finite.');
        }

        return $normalized;
    }

    protected function intValue(mixed $value, int $default, string $field): int
    {
        if ($value === null) {
            return $default;
        }
        if (!is_int($value)) {
            throw new ValidationException("{$field} must be an integer.");
        }

        return $value;
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
     * @param array<int|string, mixed> $candidates
     * @return list<string>
     */
    protected function normalizeCandidateIds(array $candidates, int $maximum): array
    {
        if (count($candidates) > $maximum) {
            throw new ValidationException("candidates exceeds the {$maximum} candidate limit.");
        }

        $normalized = [];
        $seen = [];
        foreach ($candidates as $index => $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                throw new ValidationException("Candidate at index {$index} must be a non-empty string.");
            }

            $candidate = trim($candidate);
            if (!isset($seen[$candidate])) {
                $seen[$candidate] = true;
                $normalized[] = $candidate;
            }
        }
        if (count($normalized) > $maximum) {
            throw new ValidationException("candidates exceeds the {$maximum} candidate limit.");
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
        $normalized = null;
        if (is_int($value) || is_float($value)) {
            $normalized = (float) $value;
        } elseif (is_string($value) && is_numeric($value)) {
            $normalized = (float) $value;
        }

        if ($normalized === null || !is_finite($normalized)) {
            throw new ValidationException("{$field} must be a finite numeric value.");
        }

        return $normalized;
    }

    protected function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ValidationException('Optional string value must be a string when provided.');
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
