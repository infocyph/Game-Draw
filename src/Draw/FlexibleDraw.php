<?php

namespace Infocyph\Draw;

use Exception;
use InvalidArgumentException;
use LengthException;

class FlexibleDraw
{
    private int $rrdIndex = 0;
    private array $cumulativeScores = [];
    private array $lastPickedTimestamps = [];
    private array $ranges;
    private string $lastPickedItem = '';

    /**
     * Construct a new FlexibleDraw instance.
     *
     * @param array $items The list of items to draw from.
     * @param bool $check Whether to check the items array for validity before executing a draw.
     */
    public function __construct(private array $items, private bool $check = true)
    {
        $this->ranges = array_filter($items, fn ($item) => isset($item['min'], $item['max'], $item['weight']));
    }

    /**
     * Validates the items array structure based on the draw type.
     *
     * @param string $drawType The specific draw type being validated.
     * @throws LengthException If the items array is empty.
     * @throws InvalidArgumentException If required keys are missing or invalid for the given draw type.
     */
    public function check(string $drawType = ''): void
    {
        if (empty($this->items)) {
            throw new LengthException("Items array must contain at least one item.");
        }

        // Define the required keys based on the draw type
        $requiredKeys = match ($drawType) {
            'weightedElimination', 'probability', 'weightedBatch' => ['name' => true, 'weight' => true],
            'timeBased' => ['name' => true, 'weight' => true, 'time' => true],
            'rangeWeighted' => ['name' => true, 'min' => true, 'max' => true, 'weight' => true],
            default => ['name' => true]
        };

        foreach ($this->items as $index => $item) {
            $missingKeys = array_diff_key($requiredKeys, $item);
            if (!empty($missingKeys)) {
                throw new InvalidArgumentException(
                    "Item at index $index is missing required keys for $drawType draw: " . implode(
                        ', ',
                        array_keys($missingKeys),
                    ),
                );
            }
            if ($drawType === 'rangeWeighted' && $item['min'] >= $item['max']) {
                throw new InvalidArgumentException("For rangeWeighted draw, 'min' should be less than 'max'.");
            }
        }
    }

    /**
     * Main draw function that lets user specify the draw type.
     *
     * @param string $type The type of draw to execute.
     * @param int $count Optional number of items to draw (for batched/weightedBatch draw).
     * @param bool $withReplacement Whether to draw with replacement (for batched draw).
     * @return array|string Returns the selected item(s).
     * @throws Exception
     */
    public function draw(string $type, int $count = 1, bool $withReplacement = false): array|string
    {
        if ($this->check) {
            $this->check($type);
        }

        return match ($type) {
            'probability' => $this->probabilityWeightedDraw(),
            'elimination' => $this->eliminationDraw(),
            'weightedElimination' => $this->weightedEliminationDraw(),
            'roundRobin' => $this->roundRobinDraw(),
            'cumulative' => $this->cumulativeDraw(),
            'batched' => $this->batchedDraw($count, $withReplacement),
            'timeBased' => $this->timeBasedWeightedDraw(),
            'weightedBatch' => $this->weightedRandomBatchDraw($count),
            'sequential' => $this->sequentialDraw(),
            'rangeWeighted' => $this->rangeWeightedDraw(),
            default => throw new InvalidArgumentException("Unknown draw type: $type")
        };
    }


    /**
     * Probability Weighted Draw: Selects an item based on weight probabilities.
     *
     * This method calculates the total weight of all items and uses a random
     * number to select one item based on their individual weight. Items with
     * higher weight have a higher chance of being selected. If the total weight
     * is zero or less, an exception is thrown. If no item is selected, an
     * unexpected exception is raised.
     *
     * @return string The name of the selected item.
     * @throws Exception If the total weight is zero or less, or if the draw fails unexpectedly.
     */
    private function probabilityWeightedDraw(): string
    {
        $totalWeight = array_sum(array_column($this->items, 'weight'));
        if ($totalWeight <= 0) {
            throw new Exception("Total weight must be greater than zero.");
        }

        $randomWeight = random_int(1, $totalWeight);
        foreach ($this->items as $item) {
            $randomWeight -= $item['weight'];
            if ($randomWeight <= 0) {
                return $item['name'];
            }
        }
        $items = array_column($this->items, 'weight', 'name');
        return array_search(max($items), $items);
    }

    /**
     * Elimination Draw: Draws an item without replacement from the list of items.
     *
     * This method randomly selects an item from the available items and removes it
     * from the list, ensuring that each item can only be drawn once. If no items
     * are left to draw, an exception is thrown.
     *
     * @return string The name of the selected item.
     * @throws Exception If there are no items left to draw.
     */
    private function eliminationDraw(): string
    {
        $index = array_rand($this->items);
        $pickedItem = $this->items[$index]['name'];
        unset($this->items[$index]);

        return $pickedItem;
    }


    /**
     * Weighted Elimination Draw: Draws an item based on weight without replacement.
     *
     * This method selects an item using weighted probabilities and removes
     * it from the list, ensuring that each item can only be drawn once.
     * If no items are left to draw, an exception is thrown.
     *
     * @return string The name of the selected item.
     * @throws Exception If there are no items left to draw.
     */
    private function weightedEliminationDraw(): string
    {
        $totalWeight = array_sum(array_column($this->items, 'weight'));
        if ($totalWeight <= 0) {
            throw new Exception(
                "Total weight must be greater than zero.",
            );
        }

        $randomWeight = random_int(1, $totalWeight);
        foreach ($this->items as $index => $item) {
            $randomWeight -= $item['weight'];
            if ($randomWeight <= 0) {
                $pickedItem = $item['name'];
                unset($this->items[$index]);
                $this->items = array_values($this->items);
                return $pickedItem;
            }
        }

        throw new Exception("Draw failed unexpectedly.");
    }


    /**
     * Round Robin Draw: Cycles through items sequentially, selecting each item once.
     *
     * @return string The name of the selected item.
     * @throws Exception
     */
    private function roundRobinDraw(): string
    {
        $pickedItem = $this->items[$this->rrdIndex]['name'];
        $this->rrdIndex = ($this->rrdIndex + 1) % count($this->items);

        return $pickedItem;
    }


    /**
     * Cumulative Draw: Selects an item based on accumulated scores.
     *
     * This method increases the score for each item randomly,
     * enhancing the probability of selection over time.
     * The item with the highest cumulative score is picked,
     * and its score is reset to zero after selection.
     *
     * @return string The name of the item selected.
     */
    private function cumulativeDraw(): string
    {
        if (empty($this->cumulativeScores)) {
            $this->cumulativeScores = array_fill_keys(array_column($this->items, 'name'), 0);
        }

        foreach ($this->cumulativeScores as $item => &$score) {
            if ($item !== $this->lastPickedItem) {
                $score += random_int(1, 100);
            }
        }

        $pickedItem = array_search(max($this->cumulativeScores), $this->cumulativeScores);

        // Reset the score of the picked item and update the last-picked item
        $this->cumulativeScores[$pickedItem] = 0;
        $this->lastPickedItem = $pickedItem;

        return $pickedItem;
    }


    /**
     * Batched Draw: Draws a specified number of items from the pool.
     *
     * This method allows drawing multiple items in a single operation.
     * The items can be drawn with or without replacement based on the
     * provided parameter.
     *
     * @param int $count The number of items to draw.
     * @param bool $withReplacement Determines if items should be drawn with replacement.
     * @return array An array of drawn item names.
     * @throws InvalidArgumentException if the count is not a positive integer.
     */
    private function batchedDraw(int $count, bool $withReplacement): array
    {
        if ($count <= 0) {
            throw new InvalidArgumentException("Count must be a positive integer.");
        }

        $pickedItems = [];
        for ($i = 0; $i < $count && !empty($this->items); $i++) {
            $pickedItems[] = $withReplacement
                ? $this->items[array_rand($this->items)]['name']
                : $this->pickWithoutReplacement();
        }

        return $pickedItems;
    }


    /**
     * Time-Based Weighted Draw with Probability: Selects an item based on weight and time-based priority.
     *
     * Each item has an associated weight and a time interval (`minute`, `hourly`, `daily`, etc.).
     * Items that haven’t been selected within their defined time interval have higher selection priority.
     * A weighted probability is applied after sorting by recency to allow for occasional selection of lower-weight items.
     *
     * @return string The name of the selected item.
     * @throws Exception If no items are defined.
     */
    private function timeBasedWeightedDraw(): string
    {
        if (empty($this->lastPickedTimestamps)) {
            $this->lastPickedTimestamps = array_fill_keys(array_column($this->items, 'name'), 0);
        }

        $currentTime = microtime(true);

        // Define the thresholds for each possible interval
        $intervalThresholds = [
            'minute' => 60,                     // 1 minute
            'hourly' => 60 * 60,                // 1 hour
            'daily' => 24 * 60 * 60,            // 1 day
            'weekly' => 7 * 24 * 60 * 60,       // 1 week
            'monthly' => 30 * 24 * 60 * 60,     // 1 month (approximate)
        ];
        usort($this->items, function ($a, $b) use ($currentTime, $intervalThresholds) {
            $lastPickedA = $this->lastPickedTimestamps[$a['name']] ?? 0;
            $lastPickedB = $this->lastPickedTimestamps[$b['name']] ?? 0;

            // Get the interval threshold for each item
            $thresholdA = $intervalThresholds[$a['time']] ?? $intervalThresholds['daily'];
            $thresholdB = $intervalThresholds[$b['time']] ?? $intervalThresholds['daily'];

            // Determine if each item is beyond its recency threshold
            $aIsOld = ($currentTime - $lastPickedA) >= $thresholdA;
            $bIsOld = ($currentTime - $lastPickedB) >= $thresholdB;

            return match (true) {
                $aIsOld && !$bIsOld => -1,
                !$aIsOld && $bIsOld => 1,
                default => $b['weight'] <=> $a['weight']
            };
        });

        // Step 2: Calculate the total weight after sorting and apply a weighted probability
        $totalWeight = array_sum(array_column($this->items, 'weight'));
        $randomWeight = random_int(1, $totalWeight);

        // Traverse the sorted list and pick an item based on the weighted probability
        foreach ($this->items as $item) {
            $randomWeight -= $item['weight'];
            if ($randomWeight <= 0) {
                $pickedItem = $item['name'];
                $this->lastPickedTimestamps[$pickedItem] = $currentTime;
                return $pickedItem;
            }
        }

        // Fallback, although theoretically unreachable if weights are valid
        throw new Exception("Draw failed unexpectedly (probably due to invalid weights).");
    }


    /**
     * Weighted Random Batch Draw: Draws a batch of items using weighted probabilities, balancing selection based on item weights.
     *
     * @param int $count The number of items to draw in the batch.
     * @return array An array of strings, each representing a randomly selected item.
     * @throws InvalidArgumentException|Exception If the $count is not a positive integer.
     */
    private function weightedRandomBatchDraw(int $count): array
    {
        if ($count <= 0) {
            throw new InvalidArgumentException("Count must be a positive integer.");
        }

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->probabilityWeightedDraw();
        }
        return $results;
    }


    /**
     * Sequential Draw: Selects items in a strict sequence.
     *
     * This method picks items one by one from the list of items, cycling back to the start when the end is reached.
     * The order of items is preserved, and once an item is picked, it is not picked again until the sequence has been
     * fully cycled.
     *
     * @return string The selected item.
     * @throws Exception if no items are defined.
     */
    private function sequentialDraw(): string
    {
        $item = $this->items[$this->rrdIndex]['name'];
        $this->rrdIndex = ($this->rrdIndex + 1) % count($this->items);
        return $item;
    }


    /**
     * Range-Weighted Draw: Selects a random value based on weighted ranges.
     *
     * This method iterates over a list of ranges, each with an associated weight,
     * to select a random number. The selection is influenced by the weights,
     * meaning ranges with higher weights are more likely to be selected.
     *
     * @return float|int A random value from the selected range.
     * @throws Exception if no ranges are defined, if the total weight is zero or less, or if the draw fails.
     */
    private function rangeWeightedDraw(): float|int
    {
        if (empty($this->ranges)) {
            throw new Exception("No ranges defined for range-weighted draw.");
        }

        $totalWeight = array_sum(array_column($this->ranges, 'weight'));
        if ($totalWeight <= 0) {
            throw new Exception("Total weight of ranges must be greater than zero.");
        }

        $randomWeight = random_int(1, $totalWeight);
        foreach ($this->ranges as $range) {
            $randomWeight -= $range['weight'];
            if ($randomWeight <= 0) {
                return $this->randomInRange($range['min'], $range['max']);
            }
        }

        throw new Exception("Draw failed.");
    }

    /**
     * Generates a random number within the given range.
     *
     * @param float|int $min The minimum value of the range.
     * @param float|int $max The maximum value of the range.
     * @return float|int A random number within the range.
     */
    private function randomInRange(float|int $min, float|int $max): float|int
    {
        return $min + lcg_value() * ($max - $min);
    }


    /**
     * Draws an item without replacement.
     *
     * @return string The picked item name.
     */
    private function pickWithoutReplacement(): string
    {
        $index = array_rand($this->items);
        $pickedItem = $this->items[$index]['name'];
        unset($this->items[$index]);

        return $pickedItem;
    }
}
