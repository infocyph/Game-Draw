<?php

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\Support\WeightTools;

class WeightedEliminationDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random)
    {
    }

    public function draw(FlexibleState $state): string
    {
        [$weights, $totalWeight] = WeightTools::prepare($state->items);
        if ($totalWeight <= 0) {
            throw new ValidationException("Total weight must be greater than zero.");
        }

        $randomWeight = $this->random->int(1, $totalWeight);
        foreach ($weights as $weight) {
            $randomWeight -= $weight['weight'];
            if ($randomWeight <= 0) {
                $index = $weight['index'];
                $pickedItem = $state->items[$index]['name'];
                unset($state->items[$index]);
                $state->items = array_values($state->items);
                return $pickedItem;
            }
        }

        throw new DrawExhaustedException("Draw failed unexpectedly.");
    }
}
