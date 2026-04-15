<?php

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\EmptyPoolException;
use Infocyph\Draw\Exceptions\ValidationException;

class BatchedDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state, int $count, bool $withReplacement): array
    {
        if ($count <= 0) {
            throw new ValidationException("Count must be a positive integer.");
        }

        $pickedItems = [];
        for ($i = 0; $i < $count && !empty($state->items); $i++) {
            $pickedItems[] = $withReplacement
                ? $state->items[$this->random->pickArrayKey($state->items)]['name']
                : $this->pickWithoutReplacement($state);
        }

        return $pickedItems;
    }

    private function pickWithoutReplacement(FlexibleState $state): string
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
