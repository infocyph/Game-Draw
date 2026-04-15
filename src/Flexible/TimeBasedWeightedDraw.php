<?php

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
            $state->lastPickedTimestamps = array_fill_keys(array_column($state->items, 'name'), 0);
        }

        $currentTime = microtime(true);
        $intervalThresholds = [
            'minute' => 60,
            'hourly' => 60 * 60,
            'daily' => 24 * 60 * 60,
            'weekly' => 7 * 24 * 60 * 60,
            'monthly' => 30 * 24 * 60 * 60,
        ];

        usort($state->items, function ($a, $b) use ($currentTime, $intervalThresholds, $state) {
            $lastPickedA = $state->lastPickedTimestamps[$a['name']] ?? 0;
            $lastPickedB = $state->lastPickedTimestamps[$b['name']] ?? 0;
            $thresholdA = $intervalThresholds[$a['time']] ?? $intervalThresholds['daily'];
            $thresholdB = $intervalThresholds[$b['time']] ?? $intervalThresholds['daily'];
            $aIsOld = ($currentTime - $lastPickedA) >= $thresholdA;
            $bIsOld = ($currentTime - $lastPickedB) >= $thresholdB;

            return match (true) {
                $aIsOld && !$bIsOld => -1,
                !$aIsOld && $bIsOld => 1,
                default => $b['weight'] <=> $a['weight'],
            };
        });

        [$weights, $totalWeight] = WeightTools::prepare($state->items);
        if ($totalWeight <= 0) {
            throw new ValidationException("Total weight must be greater than zero.");
        }

        $randomWeight = $this->random->int(1, $totalWeight);
        foreach ($weights as $weight) {
            $randomWeight -= $weight['weight'];
            if ($randomWeight <= 0) {
                $pickedItem = $state->items[$weight['index']]['name'];
                $state->lastPickedTimestamps[$pickedItem] = $currentTime;
                return $pickedItem;
            }
        }

        throw new DrawExhaustedException("Draw failed unexpectedly (probably due to invalid weights).");
    }
}
