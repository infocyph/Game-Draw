<?php

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Exceptions\EmptyPoolException;

class RoundRobinDraw
{
    public function draw(FlexibleState $state): string
    {
        if (empty($state->items)) {
            throw new EmptyPoolException('No items left to draw.');
        }

        $pickedItem = $state->items[$state->rrdIndex]['name'];
        $state->rrdIndex = ($state->rrdIndex + 1) % count($state->items);

        return $pickedItem;
    }
}
