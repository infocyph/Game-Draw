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
        $state->ensureTimeStateInitialized();

        $currentTime = microtime(true);
        $weightedItems = [];
        $itemIndexes = [];
        foreach ($state->items as $index => $item) {
            $name = $state->itemName($index);
            $lastPicked = $state->lastPickedTimestamps[$name] ?? 0.0;
            $threshold = $state->itemTimeThreshold($index);
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
