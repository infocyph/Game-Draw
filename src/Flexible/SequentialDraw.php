<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Exceptions\EmptyPoolException;

class SequentialDraw
{
    public function draw(FlexibleState $state): string
    {
        if (empty($state->items)) {
            throw new EmptyPoolException('No items left to draw.');
        }

        $item = $state->itemName($state->rrdIndex);
        $state->rrdIndex = ($state->rrdIndex + 1) % count($state->items);

        return $item;
    }
}
