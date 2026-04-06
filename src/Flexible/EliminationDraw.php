<?php

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\EmptyPoolException;

class EliminationDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random)
    {
    }

    public function draw(FlexibleState $state): string
    {
        if (empty($state->items)) {
            throw new EmptyPoolException('No items left to draw.');
        }

        $index = $this->random->pickArrayKey($state->items);
        $pickedItem = $state->items[$index]['name'];
        unset($state->items[$index]);

        return $pickedItem;
    }
}
