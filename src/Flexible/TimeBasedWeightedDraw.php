<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\EmptyPoolException;
use Infocyph\Draw\Flexible\Support\WeightedSelector;

class TimeBasedWeightedDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state): string
    {
        if ($state->items === []) {
            throw new EmptyPoolException('No items left to draw.');
        }
        $state->ensureLastPickedTimestampsInitialized();

        $currentTime = microtime(true);
        $intervalThresholds = [
            'minute' => 60,
            'hourly' => 60 * 60,
            'daily' => 24 * 60 * 60,
            'weekly' => 7 * 24 * 60 * 60,
            'monthly' => 30 * 24 * 60 * 60,
        ];

        $weightedItems = [];
        $itemIndexes = [];
        foreach ($state->items as $index => $item) {
            $name = $state->itemName($index);
            $time = $state->itemTime($index);
            $lastPicked = $state->lastPickedTimestamps[$name] ?? 0.0;
            $threshold = $intervalThresholds[$time] ?? $intervalThresholds['daily'];
            $elapsed = max(0.0, $currentTime - $lastPicked);
            $urgencyBoost = max(1.0, $elapsed / $threshold);

            $weightedItems[] = [
                'weight' => $state->itemWeight($index) * $urgencyBoost,
            ];
            $itemIndexes[] = $index;
        }

        $pickedWeightedIndex = WeightedSelector::pickIndex(
            random: $this->random,
            weightsInput: $weightedItems,
            exhaustedMessage: 'Draw failed unexpectedly (probably due to invalid weights).',
        );
        $pickedItem = $state->itemName($itemIndexes[$pickedWeightedIndex]);
        $state->lastPickedTimestamps[$pickedItem] = $currentTime;

        return $pickedItem;
    }
}
