<?php

declare(strict_types=1);

namespace Infocyph\Draw\Support;

use Infocyph\Draw\Exceptions\ValidationException;

final class DrawValidator
{
    public static function assertNonNegativeInt(mixed $value, string $field): void
    {
        if (!is_int($value) || $value < 0) {
            throw new ValidationException("{$field} must be a non-negative integer.");
        }
    }

    public static function assertNonNegativeNumeric(mixed $value, string $field): void
    {
        if (self::numericValue($value, $field) < 0) {
            throw new ValidationException("{$field} must be greater than or equal to zero.");
        }
    }

    /**
     * @param array<mixed> $values
     */
    public static function assertNotEmpty(array $values, string $message): void
    {
        if (empty($values)) {
            throw new ValidationException($message);
        }
    }

    public static function assertNumeric(mixed $value, string $field): void
    {
        if (!is_numeric($value)) {
            throw new ValidationException("{$field} must be numeric.");
        }
    }

    public static function assertPositiveNumeric(mixed $value, string $field): void
    {
        if (self::numericValue($value, $field) <= 0) {
            throw new ValidationException("{$field} must be greater than zero.");
        }
    }

    public static function assertReadableFile(string $filePath): void
    {
        if (!is_readable($filePath)) {
            throw new ValidationException('File not found or not readable');
        }
    }

    /**
     * @param array<mixed> $items
     * @param array<string, bool> $requiredKeys
     */
    public static function assertRequiredKeys(array $items, array $requiredKeys, string $context): void
    {
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new ValidationException("{$context} at index {$index} must be an array.");
            }

            $missingKeys = array_diff_key($requiredKeys, $item);
            if (!empty($missingKeys)) {
                throw new ValidationException(
                    "{$context} at index {$index} is missing required keys: " . implode(', ', array_keys($missingKeys)),
                );
            }
        }
    }

    private static function numericValue(mixed $value, string $field): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new ValidationException("{$field} must be numeric.");
    }
}
