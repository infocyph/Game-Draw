<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\Support\WeightTools;

class FlexibleState
{
    /**
     * @var array<string, int>
     */
    public array $cumulativeScores = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $items = [];

    public string $lastPickedItem = '';

    /**
     * @var array<string, float>
     */
    public array $lastPickedTimestamps = [];

    /**
     * @var list<array{min: int|float|string, max: int|float|string, weight: int|float|string}>
     */
    public array $ranges;

    public int $rrdIndex = 0;

    /**
     * @var list<int>
     */
    public array $timeThresholds = [];

    /**
     * @var array{0: array<int, array{index: int, weight: int}>, 1: int}|null
     */
    private ?array $preparedItemWeights = null;

    /**
     * @param array<int|string, mixed> $items
     */
    public function __construct(array $items)
    {
        $this->ranges = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new ValidationException("Item at index {$index} must be an array.");
            }

            $normalizedItem = [];
            foreach ($item as $key => $value) {
                $normalizedItem[(string) $key] = $value;
            }
            $this->items[] = $normalizedItem;

            $min = $item['min'] ?? null;
            $max = $item['max'] ?? null;
            $weight = $item['weight'] ?? null;
            if (
                (is_int($min) || is_float($min) || is_string($min))
                && (is_int($max) || is_float($max) || is_string($max))
                && (is_int($weight) || is_float($weight) || is_string($weight))
            ) {
                $this->ranges[] = [
                    'min' => $min,
                    'max' => $max,
                    'weight' => $weight,
                ];
            }
        }
    }

    public function ensureCumulativeScoresInitialized(): void
    {
        if ($this->cumulativeScores !== []) {
            return;
        }

        $scores = [];
        foreach ($this->items as $index => $_item) {
            $name = $this->itemName($index);
            if (isset($scores[$name])) {
                throw new ValidationException("Item name '{$name}' must be unique for cumulative draws.");
            }
            $scores[$name] = 0;
        }
        $this->cumulativeScores = $scores;
    }

    public function ensureHasItems(string $message): void
    {
        if ($this->items === []) {
            throw new ValidationException($message);
        }
    }

    public function ensureLastPickedTimestampsInitialized(): void
    {
        if ($this->lastPickedTimestamps !== []) {
            return;
        }

        foreach ($this->items as $index => $_item) {
            $this->lastPickedTimestamps[$this->itemName($index)] = 0.0;
        }
    }

    public function ensureTimeStateInitialized(): void
    {
        if ($this->timeThresholds !== []) {
            return;
        }

        $thresholds = [
            'minute' => 60,
            'hourly' => 60 * 60,
            'daily' => 24 * 60 * 60,
            'weekly' => 7 * 24 * 60 * 60,
            'monthly' => 30 * 24 * 60 * 60,
        ];
        $timestamps = [];
        $resolvedThresholds = [];
        foreach ($this->items as $index => $_item) {
            $name = $this->itemName($index);
            if (isset($timestamps[$name])) {
                throw new ValidationException("Item name '{$name}' must be unique for time-based draws.");
            }
            $time = $this->itemTime($index);
            if (!isset($thresholds[$time])) {
                throw new ValidationException("Unsupported time interval '{$time}'.");
            }
            $timestamps[$name] = 0.0;
            $resolvedThresholds[] = $thresholds[$time];
        }
        $this->lastPickedTimestamps = $timestamps;
        $this->timeThresholds = $resolvedThresholds;
    }

    public function itemName(int|string $index): string
    {
        return $this->requiredStringField($index, 'name');
    }

    public function itemTime(int|string $index): string
    {
        return $this->requiredStringField($index, 'time');
    }

    public function itemTimeThreshold(int $index): int
    {
        return $this->timeThresholds[$index];
    }

    public function itemWeight(int|string $index): float
    {
        $item = $this->items[$index] ?? null;
        if (!is_array($item)) {
            throw new ValidationException("Item at index {$index} is invalid.");
        }

        $weight = $item['weight'] ?? null;
        if (is_int($weight) || is_float($weight)) {
            return (float) $weight;
        }
        if (is_string($weight) && is_numeric($weight)) {
            return (float) $weight;
        }

        throw new ValidationException("Item at index {$index} must have numeric 'weight'.");
    }

    /**
     * @return array{0: array<int, array{index: int, weight: int}>, 1: int}
     */
    public function preparedItemWeights(): array
    {
        if ($this->preparedItemWeights !== null) {
            return $this->preparedItemWeights;
        }

        $weights = [];
        foreach ($this->items as $index => $_item) {
            $weights[] = ['weight' => $this->itemWeight($index)];
        }

        return $this->preparedItemWeights = WeightTools::prepare($weights);
    }

    public function removeItem(int|string $index): void
    {
        $offset = is_int($index) ? $index : (ctype_digit($index) ? (int) $index : null);
        if ($offset === null) {
            return;
        }

        if ($this->preparedItemWeights !== null && isset($this->preparedItemWeights[0][$offset])) {
            [$weights, $totalWeight] = $this->preparedItemWeights;
            $totalWeight -= $weights[$offset]['weight'];

            $remainingWeights = [];
            foreach ($weights as $weightIndex => $weight) {
                if ($weightIndex === $offset) {
                    continue;
                }

                $remainingWeights[] = [
                    'index' => count($remainingWeights),
                    'weight' => $weight['weight'],
                ];
            }
            $this->preparedItemWeights = [$remainingWeights, $totalWeight];
        }

        array_splice($this->items, $offset, 1);
    }

    private function requiredStringField(int|string $index, string $field): string
    {
        $item = $this->items[$index] ?? null;
        if (!is_array($item)) {
            throw new ValidationException("Item at index {$index} is invalid.");
        }

        $value = $item[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new ValidationException("Item at index {$index} must have a non-empty '{$field}'.");
        }

        return $value;
    }
}
