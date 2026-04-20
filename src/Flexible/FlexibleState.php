<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible;

use Infocyph\Draw\Exceptions\ValidationException;

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
     * @param array<int|string, mixed> $items
     */
    public function __construct(array $items)
    {
        $this->ranges = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
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

    public function itemName(int|string $index): string
    {
        $item = $this->items[$index] ?? null;
        if (!is_array($item)) {
            throw new ValidationException("Item at index {$index} is invalid.");
        }

        $name = $item['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new ValidationException("Item at index {$index} must have a non-empty 'name'.");
        }

        return $name;
    }

    public function itemTime(int|string $index): string
    {
        $item = $this->items[$index] ?? null;
        if (!is_array($item)) {
            throw new ValidationException("Item at index {$index} is invalid.");
        }

        $time = $item['time'] ?? null;
        if (!is_string($time) || $time === '') {
            throw new ValidationException("Item at index {$index} must have a non-empty 'time'.");
        }

        return $time;
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

    public function removeItem(int|string $index): void
    {
        $offset = is_int($index) ? $index : (ctype_digit($index) ? (int) $index : null);
        if ($offset === null) {
            return;
        }

        array_splice($this->items, $offset, 1);
    }
}
