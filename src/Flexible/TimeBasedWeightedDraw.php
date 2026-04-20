<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Exceptions\EmptyPoolException;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\Support\WeightTools;

class TimeBasedWeightedDraw
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function draw(FlexibleState $state): string
    {
        if (empty($state->items)) {
            throw new EmptyPoolException('No items left to draw.');
        }

        if (empty($state->lastPickedTimestamps)) {
            foreach (array_keys($state->items) as $index) {
                $state->lastPickedTimestamps[$state->itemName($index)] = 0.0;
            }
        }

        $currentTime = microtime(true);
        $intervalThresholds = [
            'minute' => 60,
            'hourly' => 60 * 60,
            'daily' => 24 * 60 * 60,
            'weekly' => 7 * 24 * 60 * 60,
            'monthly' => 30 * 24 * 60 * 60,
        ];

        $weightedItems = [];
        foreach ($state->items as $index => $item) {
            $name = $state->itemName($index);
            $time = $state->itemTime($index);
            $lastPicked = $state->lastPickedTimestamps[$name] ?? 0.0;
            $threshold = $intervalThresholds[$time] ?? $intervalThresholds['daily'];
            $elapsed = max(0.0, $currentTime - $lastPicked);
            $urgencyBoost = max(1.0, $elapsed / $threshold);

            $weightedItems[] = [
                'index' => $index,
                'weight' => $state->itemWeight($index) * $urgencyBoost,
            ];
        }

        [$weights, $totalWeight] = WeightTools::prepare($weightedItems);
        if ($totalWeight <= 0) {
            throw new ValidationException('Total weight must be greater than zero.');
        }

        $randomWeight = $this->random->int(1, $totalWeight);
        foreach ($weights as $weight) {
            $randomWeight -= $weight['weight'];
            if ($randomWeight <= 0) {
                $pickedItem = $state->itemName($weightedItems[$weight['index']]['index']);
                $state->lastPickedTimestamps[$pickedItem] = $currentTime;

                return $pickedItem;
            }
        }

        throw new DrawExhaustedException('Draw failed unexpectedly (probably due to invalid weights).');
    }
}
