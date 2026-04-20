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
        if (empty($state->items)) {
            throw new EmptyPoolException('No items left to draw.');
        }

        if (empty($state->cumulativeScores)) {
            foreach (array_keys($state->items) as $index) {
                $state->cumulativeScores[$state->itemName($index)] = 0;
            }
        }

        foreach ($state->cumulativeScores as $item => &$score) {
            if ($item !== $state->lastPickedItem) {
                $score += $this->random->int(1, 100);
            }
        }
        unset($score);

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
