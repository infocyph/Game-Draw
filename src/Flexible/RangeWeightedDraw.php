<?php

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\Support\WeightTools;

class RangeWeightedDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random)
    {
    }

    public function draw(FlexibleState $state): float|int
    {
        if (empty($state->ranges)) {
            throw new ValidationException("No ranges defined for range-weighted draw.");
        }

        [$weights, $totalWeight] = WeightTools::prepare($state->ranges);
        if ($totalWeight <= 0) {
            throw new ValidationException("Total weight of ranges must be greater than zero.");
        }

        $randomWeight = $this->random->int(1, $totalWeight);
        foreach ($weights as $weight) {
            $randomWeight -= $weight['weight'];
            if ($randomWeight <= 0) {
                $range = $state->ranges[$weight['index']];
                return $this->randomInRange($range['min'], $range['max']);
            }
        }

        throw new DrawExhaustedException("Draw failed.");
    }

    private function randomInRange(float|int $min, float|int $max): float|int
    {
        return $min + $this->random->float() * ($max - $min);
    }
}
