<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Exceptions\ValidationException;

class WeightedBatchDraw
{
    public function __construct(private readonly ProbabilityDraw $probabilityDraw) {}

    /**
     * @return list<string>
     */
    public function draw(FlexibleState $state, int $count): array
    {
        if ($count <= 0) {
            throw new ValidationException('Count must be a positive integer.');
        }

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->probabilityDraw->draw($state);
        }

        return $results;
    }
}
