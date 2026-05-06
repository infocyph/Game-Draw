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
        $weightsInput = [];
        foreach (array_keys($state->items) as $index) {
            $weightsInput[] = ['weight' => $state->itemWeight($index)];
        }

        $pickedIndex = WeightedSelector::pickIndex(
            random: $this->random,
            weightsInput: $weightsInput,
            exhaustedMessage: 'Probability draw failed unexpectedly.',
        );

        return $state->itemName($pickedIndex);
    }
}
