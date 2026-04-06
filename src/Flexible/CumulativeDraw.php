<?php

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\EmptyPoolException;

class CumulativeDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random)
    {
    }

    public function draw(FlexibleState $state): string
    {
        if (empty($state->items)) {
            throw new EmptyPoolException('No items left to draw.');
        }

        if (empty($state->cumulativeScores)) {
            $state->cumulativeScores = array_fill_keys(array_column($state->items, 'name'), 0);
        }

        foreach ($state->cumulativeScores as $item => &$score) {
            if ($item !== $state->lastPickedItem) {
                $score += $this->random->int(1, 100);
            }
        }
        unset($score);

        $pickedItem = array_search(max($state->cumulativeScores), $state->cumulativeScores, true);
        $state->cumulativeScores[$pickedItem] = 0;
        $state->lastPickedItem = $pickedItem;

        return $pickedItem;
    }
}
