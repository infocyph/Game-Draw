<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Exceptions\EmptyPoolException;

class CumulativeDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state): string
    {
        if ($state->items === []) {
            throw new EmptyPoolException('No items left to draw.');
        }
        $state->ensureCumulativeScoresInitialized();

        foreach ($state->cumulativeScores as $item => &$score) {
            if ($item !== $state->lastPickedItem) {
                $score += $this->random->int(1, 100);
            }
        }
        unset($score);

        if ($state->cumulativeScores === []) {
            throw new DrawExhaustedException('Unable to resolve cumulative pick.');
        }

        $bestScore = max($state->cumulativeScores);
        $pickedItem = array_search($bestScore, $state->cumulativeScores, true);
        if (!is_string($pickedItem)) {
            throw new DrawExhaustedException('Unable to resolve cumulative pick.');
        }
        $state->cumulativeScores[$pickedItem] = 0;
        $state->lastPickedItem = $pickedItem;

        return $pickedItem;
    }
}
