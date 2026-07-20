<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Exceptions\ValidationException;

class WeightedBatchDraw
{
    private const MAX_DRAW_COUNT = 100_000;

    public function __construct(private readonly ProbabilityDraw $probabilityDraw) {}

    /**
     * @return list<string>
     */
    public function draw(FlexibleState $state, int $count): array
    {
        if ($count <= 0 || $count > self::MAX_DRAW_COUNT) {
            throw new ValidationException('Count must be between 1 and 100000.');
        }

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->probabilityDraw->draw($state);
        }

        return $results;
    }
}
