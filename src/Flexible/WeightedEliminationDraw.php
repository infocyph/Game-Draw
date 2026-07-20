<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Flexible\Support\WeightedSelector;

class WeightedEliminationDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state): string
    {
        [$weights, $totalWeight] = $state->preparedItemWeights();

        $index = WeightedSelector::pickPrepared($this->random, $weights, $totalWeight);
        $pickedItem = $state->itemName($index);
        $state->removeItem($index);

        return $pickedItem;
    }
}
