<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;

class BatchedDraw
{
    private const MAX_DRAW_COUNT = 100_000;

    private readonly EliminationDraw $elimination;

    public function __construct(private readonly RandomGeneratorInterface $random)
    {
        $this->elimination = new EliminationDraw($random);
    }

    /**
     * @return list<string>
     */
    public function draw(FlexibleState $state, int $count, bool $withReplacement): array
    {
        if ($count <= 0 || $count > self::MAX_DRAW_COUNT) {
            throw new ValidationException('Count must be between 1 and 100000.');
        }

        $pickedItems = [];
        for ($i = 0; $i < $count && $state->items !== []; $i++) {
            $pickedItems[] = $withReplacement
                ? $state->itemName($this->random->pickArrayKey($state->items))
                : $this->elimination->draw($state);
        }

        return $pickedItems;
    }
}
