<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\EmptyPoolException;

class EliminationDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state): string
    {
        if (empty($state->items)) {
            throw new EmptyPoolException('No items left to draw.');
        }

        return (new BatchedDraw($this->random))->draw($state, 1, false)[0];
    }
}
