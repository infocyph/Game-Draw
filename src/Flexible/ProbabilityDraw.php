<?php

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\Support\WeightTools;

class ProbabilityDraw
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
                return $state->items[$weight['index']]['name'];
            }
        }

        $items = array_column($weights, 'weight', 'index');
        $index = array_search(max($items), $items, true);
        return $state->items[$index]['name'];
    }
}
