<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible\Support;

use Infocyph\Draw\Exceptions\ValidationException;

final class WeightTools
{
    private const int MAX_SCALE = 12;

    /**
     * @param array<int, array{weight: int|float|string}> $items
     */
    public static function maxFractionLength(array $items): int
    {
        $maxFractionLength = 0;
        foreach ($items as $item) {
            $rawWeight = self::normalizeNumericString($item['weight']);
            $dotPosition = strpos($rawWeight, '.');
            if ($dotPosition === false) {
                continue;
            }

            $fraction = rtrim(substr($rawWeight, $dotPosition + 1), '0');
            $maxFractionLength = max($maxFractionLength, strlen($fraction));
        }

        return $maxFractionLength;
    }

    /**
     * @param array<int, array{weight: int|float|string}> $items
     * @return array{0: array<int, array{index: int, weight: int}>, 1: int}
     */
    public static function prepare(array $items): array
    {
        $maxFractionLength = min(self::MAX_SCALE, self::maxFractionLength($items));

        for ($scale = $maxFractionLength; $scale >= 0; $scale--) {
            $prepared = self::prepareAtScale($items, $scale);
            if ($prepared !== null) {
                return $prepared;
            }
        }

        throw new ValidationException('Weights are too large to be represented safely as integers.');
    }

    private static function normalizeNumericString(int|float|string $value): string
    {
        $rawWeight = is_string($value) ? trim($value) : (string) $value;
        if ($rawWeight === '' || !is_numeric($rawWeight)) {
            throw new ValidationException('Weight must be numeric.');
        }

        if (stripos($rawWeight, 'e') !== false) {
            $rawWeight = rtrim(rtrim(sprintf('%.20F', (float) $rawWeight), '0'), '.');
            if ($rawWeight === '') {
                return '0';
            }
        }

        if (!str_contains($rawWeight, '.')) {
            return $rawWeight;
        }

        [$whole, $fraction] = explode('.', $rawWeight, 2);
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : "{$whole}.{$fraction}";
    }

    private static function numericValue(int|float|string $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_numeric($value)) {
            throw new ValidationException('Weight must be numeric.');
        }

        return (float) self::normalizeNumericString($value);
    }

    /**
     * @param array<int, array{weight: int|float|string}> $items
     * @return array{0: array<int, array{index: int, weight: int}>, 1: int}|null
     */
    private static function prepareAtScale(array $items, int $scale): ?array
    {
        $weights = [];
        $totalWeight = 0;
        $multiplier = 10 ** $scale;

        foreach ($items as $index => $item) {
            $normalizedWeight = self::numericValue($item['weight']);
            if ($normalizedWeight < 0) {
                throw new ValidationException('Weight must be greater than or equal to zero.');
            }

            $scaledWeight = (int) round($normalizedWeight * $multiplier);
            if ($scaledWeight < 0 || $scaledWeight > PHP_INT_MAX - $totalWeight) {
                return null;
            }

            $weights[] = ['index' => $index, 'weight' => $scaledWeight];
            $totalWeight += $scaledWeight;
        }

        return [$weights, $totalWeight];
    }
}
