<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Flexible\Support\WeightedSelector;

class ProbabilityDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state): string
    {
        [$weights, $totalWeight] = $state->preparedItemWeights();

        $pickedIndex = WeightedSelector::pickPrepared(
            random: $this->random,
            weights: $weights,
            totalWeight: $totalWeight,
            exhaustedMessage: 'Probability draw failed unexpectedly.',
        );

        return $state->itemName($pickedIndex);
    }
}
