<?php

namespace Infocyph\Draw\Flexible\Support;

use Infocyph\Draw\Exceptions\ValidationException;

final class WeightTools
{
    public static function maxFractionLength(array $items): int
    {
        $maxFractionLength = 0;
        foreach ($items as $item) {
            $rawWeight = (string) ($item['weight'] ?? '');
            is_numeric($rawWeight) || throw new ValidationException("Weight must be numeric.");

            if (stripos($rawWeight, 'e') !== false) {
                $rawWeight = rtrim(rtrim(sprintf('%.14F', (float) $rawWeight), '0'), '.');
            }

            $dotPosition = strpos($rawWeight, '.');
            $dotPosition !== false
                && $maxFractionLength = max($maxFractionLength, strlen($rawWeight) - $dotPosition - 1);
        }

        return $maxFractionLength;
    }
    /**
     * @return array{0: array<int, array{index: int, weight: int}>, 1: int}
     */
    public static function prepare(array $items): array
    {
        $maxFractionLength = self::maxFractionLength($items);
        $multiplier = 10 ** $maxFractionLength;
        $weights = [];
        $totalWeight = 0;

        foreach ($items as $index => $item) {
            $weight = (float) $item['weight'];
            $weight < 0 && throw new ValidationException("Weight must be greater than or equal to zero.");
            $scaledWeight = (int) round($weight * $multiplier);
            $weights[] = ['index' => $index, 'weight' => $scaledWeight];
            $totalWeight += $scaledWeight;
        }

        return [$weights, $totalWeight];
    }
}
