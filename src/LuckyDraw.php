<?php

namespace Infocyph\Draw;

use Exception;
use InvalidArgumentException;
use LengthException;
use UnexpectedValueException;

class LuckyDraw
{
    public function __construct(private array $items)
    {
    }

    /**
     * Validates the items array.
     *
     * @throws LengthException If the number of items is less than 1.
     * @throws InvalidArgumentException If required keys (item, chances, amounts) are missing in any item.
     */
    private function check(): void
    {
        if (empty($this->items)) {
            throw new LengthException('Items array must contain at least one item.');
        }

        $requiredKeys = ['item' => true, 'chances' => true, 'amounts' => true];
        foreach ($this->items as $index => $item) {
            $missingKeys = array_diff_key($requiredKeys, $item);
            if (!empty($missingKeys)) {
                throw new InvalidArgumentException(
                    "Item at index $index is missing required keys: " . implode(', ', array_keys($missingKeys)),
                );
            }
        }
    }

    /**
     * Picks an item & amount based on chances.
     *
     * @param bool $check Indicates whether to run the check method before picking an item (default: true)
     * @return array Returns an array containing the picked item and its amount.
     * @throws Exception
     */
    public function pick(bool $check = true): array
    {
        $check && $this->check();
        $items = $this->prepare(array_filter(array_column($this->items, 'chances', 'item')));
        $pickedItem = $this->draw($items);

        // Retrieve amounts array for the picked item
        $amounts = $this->items[array_search($pickedItem, array_column($this->items, 'item'))]['amounts'];
        is_string($amounts) && $amounts = [$this->weightedAmountRange($amounts)];

        return [
            'item' => $pickedItem,
            'amount' => $this->selectAmount($amounts),
        ];
    }

    /**
     * Selects an amount based on type.
     *
     * @param array $amounts
     * @return float|int
     * @throws Exception
     */
    private function selectAmount(array $amounts): float|int
    {
        return match (true) {
            count($amounts) === 1 => current($amounts),
            $this->isSequential($amounts) => $amounts[array_rand($amounts)],
            default => $this->draw($amounts),
        };
    }

    /**
     * Generates a weighted random number within a specified range.
     *
     * @param string $amounts A string containing the minimum, maximum, and bias values separated by commas.
     * @return float|int A single randomly generated number within the specified range.
     * @throws UnexpectedValueException If the amount range is invalid or the bias is less than or equal to 0.
     */
    private function weightedAmountRange(string $amounts): float|int
    {
        $amounts = str_getcsv($amounts);
        count($amounts) !== 3 && throw new UnexpectedValueException('Invalid amount range (expected: min,max,bias).');
        [$min, $max, $bias] = array_map('floatval', $amounts);
        $max <= $min && throw new UnexpectedValueException('Maximum value should be greater than minimum.');
        $bias <= 0 && throw new UnexpectedValueException('Bias should be greater than 0.');
        return max(
            min(
                round($min + lcg_value() ** $bias * ($max - $min + 1), $this->getFractionLength([$min, $max])),
                $max
            ),
            $min,
        );
    }

    /**
     * Draws among an array of items based on given weight.
     *
     * @param array $items The array of items to be processed.
     * @return string The selected item from the array.
     * @throws Exception if the random number generation fails.
     */
    private function draw(array $items): string
    {
        if (count($items) === 1) {
            return key($items);
        }

        $random = random_int(1, array_sum($items));
        foreach ($items as $key => $value) {
            $random -= (int)$value;
            if ($random <= 0) {
                return $key;
            }
        }
        return array_search(max($items), $items);
    }

    /**
     * Prepares an array of items.
     *
     * @param array $items The array of items to be prepared.
     * @return array The prepared array of items.
     * @throws UnexpectedValueException
     */
    private function prepare(array $items): array
    {
        if ($length = $this->getFractionLength($items)) {
            $items = $this->multiply($items, $length);
        }
        return $items;
    }

    /**
     * Calculate the length of the fraction part in an array of items.
     *
     * @param array $items The array of items to calculate the fraction length from.
     * @return int The length of the fraction part.
     * @throws UnexpectedValueException
     */
    private function getFractionLength(array $items): int
    {
        $length = 0;
        foreach ($items as $item) {
            $item > 0 || throw new UnexpectedValueException('Chances should be positive decimal number!');
            $fraction = strpos($item, '.');
            $fraction && $length = max(strlen($item) - $fraction - 1, $length);
        }
        return (int)$length;
    }

    /**
     * Multiplies each item by a decimal length.
     *
     * @param array $items Items array.
     * @param int $length Length to multiply by.
     * @return array Adjusted items array.
     */
    private function multiply(array $items, int $length): array
    {
        $multiplier = 10 ** $length;
        return array_map(fn ($value) => (int)bcmul((string)$value, (string)$multiplier), $items);
    }

    /**
     * Checks if an array has sequential keys.
     *
     * @param array $array Array to check.
     * @return bool True if sequential.
     */
    private function isSequential(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }
}
