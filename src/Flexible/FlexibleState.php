<?php

namespace Infocyph\Draw\Flexible;

class FlexibleState
{
    public array $cumulativeScores = [];
    public string $lastPickedItem = '';
    public array $lastPickedTimestamps = [];
    public array $ranges;
    public int $rrdIndex = 0;

    public function __construct(public array $items)
    {
        $this->ranges = array_filter($items, fn ($item) => isset($item['min'], $item['max'], $item['weight']));
    }
}
