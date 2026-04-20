<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\EmptyPoolException;
use Infocyph\Draw\Exceptions\ValidationException;

class BatchedDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    /**
     * @return list<string>
     */
    public function draw(FlexibleState $state, int $count, bool $withReplacement): array
    {
        if ($count <= 0) {
            throw new ValidationException('Count must be a positive integer.');
        }

        $pickedItems = [];
        for ($i = 0; $i < $count && !empty($state->items); $i++) {
            $pickedItems[] = $withReplacement
                ? $state->itemName($this->random->pickArrayKey($state->items))
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
        $pickedItem = $state->itemName($index);
        $state->removeItem($index);

        return $pickedItem;
    }
}
