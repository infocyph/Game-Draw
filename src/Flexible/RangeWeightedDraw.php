<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\Support\WeightTools;

class RangeWeightedDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state): float|int
    {
        if (empty($state->ranges)) {
            throw new ValidationException('No ranges defined for range-weighted draw.');
        }

        [$weights, $totalWeight] = WeightTools::prepare($state->ranges);
        if ($totalWeight <= 0) {
            throw new ValidationException('Total weight of ranges must be greater than zero.');
        }

        $randomWeight = $this->random->int(1, $totalWeight);
        foreach ($weights as $weight) {
            $randomWeight -= $weight['weight'];
            if ($randomWeight <= 0) {
                $range = $state->ranges[$weight['index']];
                $min = $this->normalizeBoundary($range['min']);
                $max = $this->normalizeBoundary($range['max']);
                if ($min >= $max) {
                    throw new ValidationException('Range minimum must be less than maximum.');
                }

                return $this->randomInRange($min, $max);
            }
        }

        throw new DrawExhaustedException('Draw failed.');
    }

    private function normalizeBoundary(int|float|string $value): float|int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new ValidationException('Range boundaries must be finite.');
            }

            return $value;
        }

        $valueAsString = trim($value);
        if ($valueAsString === '' || !is_numeric($valueAsString)) {
            throw new ValidationException('Range boundaries must be numeric.');
        }

        $normalized = preg_match('/^[+-]?\d+$/', $valueAsString) === 1
            ? $this->normalizeInteger($valueAsString)
            : (float) $valueAsString;
        if (is_float($normalized) && !is_finite($normalized)) {
            throw new ValidationException('Range boundaries must be finite.');
        }

        return $normalized;
    }

    private function normalizeInteger(string $value): int
    {
        $negative = str_starts_with($value, '-');
        $digits = ltrim(ltrim($value, '+-'), '0');
        $digits = $digits === '' ? '0' : $digits;
        $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new ValidationException('Integer range boundary exceeds the platform integer range.');
        }

        return (int) $value;
    }

    private function randomInRange(float|int $min, float|int $max): float|int
    {
        if (is_int($min) && is_int($max)) {
            return $this->random->int($min, $max);
        }

        return $min + $this->random->float() * ($max - $min);
    }
}
