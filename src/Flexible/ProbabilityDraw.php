<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\Support\WeightTools;

class ProbabilityDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state): string
    {
        $weightsInput = [];
        foreach (array_keys($state->items) as $index) {
            $weightsInput[] = ['weight' => $state->itemWeight($index)];
        }

        [$weights, $totalWeight] = WeightTools::prepare($weightsInput);
        if ($totalWeight <= 0) {
            throw new ValidationException('Total weight must be greater than zero.');
        }

        $randomWeight = $this->random->int(1, $totalWeight);
        foreach ($weights as $weight) {
            $randomWeight -= $weight['weight'];
            if ($randomWeight <= 0) {
                return $state->itemName($weight['index']);
            }
        }

        throw new DrawExhaustedException('Probability draw failed unexpectedly.');
    }
}
