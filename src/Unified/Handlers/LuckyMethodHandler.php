<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\Support\WeightTools;
use Infocyph\Draw\Support\DrawValidator;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Support\ResultBuilder;

class LuckyMethodHandler implements MethodHandlerInterface
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $items = $request['items'] ?? null;
        $optionsRaw = $request['options'] ?? [];

        if (!is_array($items) || $items === []) {
            throw new ValidationException('items is required and must be a non-empty array.');
        }
        if (!is_array($optionsRaw)) {
            throw new ValidationException('options must be an array when provided.');
        }

        $options = $this->normalizeAssocArray($optionsRaw, 'options');
        $count = max(1, $this->intValue($options['count'] ?? null, 1));
        $check = $this->boolValue($options['check'] ?? true);
        $normalizedItems = $this->normalizeLuckyItems($this->normalizeItems($items), $check);

        $entries = [];
        $raw = [];

        for ($i = 0; $i < $count; $i++) {
            $pick = $this->pickLucky($normalizedItems);
            $raw[] = $pick;
            $entries[] = ResultBuilder::entry(
                itemId: $pick['item'],
                candidateId: null,
                value: $pick['amount'],
                meta: ['amount' => $pick['amount']],
            );
        }

        return ResultBuilder::response('lucky', $entries, $raw, $count);
    }

    public function methods(): array
    {
        return ['lucky'];
    }

    /**
     * @return ''|'list'|'weighted'|'range'
     */
    private function amountModeFromValue(mixed $value, string $field): string
    {
        if ($value === null) {
            return '';
        }

        $mode = $this->requiredString($value, $field);
        if (!in_array($mode, ['list', 'weighted', 'range'], true)) {
            throw new ValidationException("{$field} has invalid amountMode '{$mode}'.");
        }

        return $mode;
    }

    /**
     * @param list<float|int>|array<string, float|int|string>|string $amounts
     * @return list<float|int>
     */
    private function asNumericList(array|string $amounts): array
    {
        if (!is_array($amounts)) {
            throw new ValidationException('List amounts must be an array.');
        }

        $list = [];
        foreach (array_values($amounts) as $value) {
            $list[] = $this->normalizeNumericValue($value, 'list amount');
        }

        return $list;
    }

    /**
     * @param list<float|int>|array<string, float|int|string>|string $amounts
     * @return array<string, float|int|string>
     */
    private function asWeightedAmountMap(array|string $amounts): array
    {
        if (!is_array($amounts)) {
            throw new ValidationException('Weighted amounts must be an array.');
        }

        $map = [];
        foreach ($amounts as $key => $value) {
            $map[(string) $key] = $value;
        }

        return $map;
    }

    private function boolValue(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value !== 0,
            is_string($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (bool) $value,
        };
    }

    /**
     * @param array<string, int> $items
     */
    private function drawWeighted(array $items): string
    {
        if ($items === []) {
            throw new ValidationException('Weighted draw requires at least one weight.');
        }
        if (count($items) === 1) {
            return array_key_first($items);
        }

        $total = array_sum($items);
        if ($total <= 0) {
            throw new ValidationException('Weighted draw requires at least one positive weight.');
        }

        $random = $this->random->int(1, $total);
        foreach ($items as $key => $value) {
            $random -= $value;
            if ($random <= 0) {
                return (string) $key;
            }
        }

        return array_key_last($items);
    }

    private function intValue(mixed $value, int $default): int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => $default,
        };
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private function isSequential(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * @return list<float|int>|array<string, float|int|string>|string
     */
    private function normalizeAmountsByMode(string $mode, mixed $amounts): array|string
    {
        if ($mode === 'range') {
            return $this->requiredString($amounts, 'range amounts');
        }

        if (!is_array($amounts)) {
            throw new ValidationException('Amounts payload must be an array for list/weighted modes.');
        }

        if ($mode === 'list') {
            $list = [];
            foreach (array_values($amounts) as $value) {
                $list[] = $this->normalizeNumericValue($value, 'list amount');
            }

            return $list;
        }

        $normalized = [];
        foreach ($amounts as $amount => $weight) {
            $normalized[(string) $amount] = $this->normalizeNumericValue($weight, 'weighted amount');
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAssocArray(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new ValidationException("{$field} must be an array.");
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return list<array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new ValidationException('Each item must be an array.');
            }

            $row = [];
            foreach ($item as $key => $value) {
                $row[(string) $key] = $value;
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{
     *   item: string,
     *   chances: float|int,
     *   amountMode: 'list'|'weighted'|'range',
     *   amounts: list<float|int>|array<string, float|int|string>|string
     * }>
     */
    private function normalizeLuckyItems(array $items, bool $check): array
    {
        DrawValidator::assertRequiredKeys(
            $items,
            ['item' => true, 'chances' => true, 'amounts' => true],
            'Item',
        );

        $normalized = [];
        foreach ($items as $index => $item) {
            $itemId = $this->requiredString($item['item'] ?? null, "Item at index {$index} item");
            $chances = $this->normalizeNumericValue($item['chances'] ?? null, "Chances for '{$itemId}'");
            if ($this->numericAsFloat($chances, "Chances for '{$itemId}'") <= 0) {
                throw new ValidationException("Chances for '{$itemId}' must be greater than zero.");
            }

            $mode = $this->amountModeFromValue($item['amountMode'] ?? null, "Item '{$itemId}' amountMode");
            $amounts = $item['amounts'];
            $resolvedMode = $this->resolveAmountMode($amounts, $mode, $itemId);

            if ($check) {
                $this->validateAmountsByMode($resolvedMode, $amounts, $itemId);
            }

            $normalized[] = [
                'item' => $itemId,
                'chances' => $chances,
                'amountMode' => $resolvedMode,
                'amounts' => $this->normalizeAmountsByMode($resolvedMode, $amounts),
            ];
        }

        return $normalized;
    }

    private function normalizeNumericKey(string $value): float|int
    {
        if (!is_numeric($value)) {
            throw new ValidationException('Weighted amount keys must be numeric.');
        }

        return str_contains($value, '.') || stripos($value, 'e') !== false
            ? (float) $value
            : (int) $value;
    }

    private function normalizeNumericValue(mixed $value, string $field): float|int
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value) || !is_numeric($value)) {
            throw new ValidationException("{$field} must be numeric.");
        }

        return str_contains($value, '.') || stripos($value, 'e') !== false
            ? (float) $value
            : (int) $value;
    }

    private function numericAsFloat(mixed $value, string $field): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new ValidationException("{$field} must be numeric.");
    }

    /**
     * @param list<float|int> $amounts
     */
    private function pickListAmount(array $amounts): float|int
    {
        if (count($amounts) === 1) {
            return $amounts[0];
        }

        $pickedIndex = $this->random->pickArrayKey($amounts);
        if (!is_int($pickedIndex)) {
            throw new ValidationException('List amount selection returned an invalid index.');
        }

        return $amounts[$pickedIndex];
    }

    /**
     * @param list<array{
     *   item: string,
     *   chances: float|int,
     *   amountMode: 'list'|'weighted'|'range',
     *   amounts: list<float|int>|array<string, float|int|string>|string
     * }> $items
     * @return array{item: string, amount: float|int}
     */
    private function pickLucky(array $items): array
    {
        $preparedItems = $this->prepareWeighted(array_column($items, 'chances', 'item'));
        if ($preparedItems === []) {
            throw new ValidationException('At least one item must have a positive chance.');
        }

        $pickedItem = $this->drawWeighted($preparedItems);
        $index = array_search($pickedItem, array_column($items, 'item'), true);
        if (!is_int($index)) {
            throw new ValidationException('Selected item could not be resolved.');
        }

        $item = $items[$index];
        $amount = match ($item['amountMode']) {
            'range' => $this->weightedAmountRange($this->requiredString($item['amounts'], 'range amounts')),
            'list' => $this->pickListAmount($this->asNumericList($item['amounts'])),
            'weighted' => $this->pickWeightedAmount($this->asWeightedAmountMap($item['amounts'])),
        };

        return ['item' => $pickedItem, 'amount' => $amount];
    }

    /**
     * @param array<string, float|int|string> $amounts
     */
    private function pickWeightedAmount(array $amounts): float|int
    {
        $prepared = $this->prepareWeighted($amounts);
        if ($prepared === []) {
            throw new ValidationException('Weighted amount map must contain at least one positive weight.');
        }

        $pickedKey = $this->drawWeighted($prepared);

        return $this->normalizeNumericKey($pickedKey);
    }

    /**
     * @param array<int|string, float|int|string> $items
     * @return array<string, int>
     */
    private function prepareWeighted(array $items): array
    {
        $weightEntries = [];
        $indexToKey = [];
        foreach ($items as $key => $value) {
            DrawValidator::assertPositiveNumeric($value, 'Weight');
            $weightEntries[] = ['weight' => $value];
            $indexToKey[] = (string) $key;
        }

        [$prepared, $totalWeight] = WeightTools::prepare($weightEntries);
        if ($totalWeight <= 0) {
            return [];
        }

        $result = [];
        foreach ($prepared as $weight) {
            $result[$indexToKey[$weight['index']]] = $weight['weight'];
        }

        return $result;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new ValidationException("{$field} must be a non-empty string.");
        }

        return trim($value);
    }

    /**
     * @param ''|'list'|'weighted'|'range' $declaredMode
     * @return 'list'|'weighted'|'range'
     */
    private function resolveAmountMode(mixed $amounts, string $declaredMode, string $itemId): string
    {
        $inferred = match (true) {
            is_string($amounts) => 'range',
            is_array($amounts) && $this->isSequential($amounts) => 'list',
            is_array($amounts) => 'weighted',
            default => throw new ValidationException("Item '{$itemId}' has unsupported amounts payload."),
        };

        if ($declaredMode !== '' && $declaredMode !== $inferred) {
            throw new ValidationException(
                "Item '{$itemId}' declares amountMode '{$declaredMode}' but payload matches '{$inferred}'.",
            );
        }

        return $declaredMode !== '' ? $declaredMode : $inferred;
    }

    private function validateAmountsByMode(string $mode, mixed $amounts, string $itemId): void
    {
        match ($mode) {
            'range' => $this->validateRangeAmounts($amounts, $itemId),
            'list' => $this->validateListAmounts($amounts, $itemId),
            'weighted' => $this->validateWeightedAmounts($amounts, $itemId),
            default => throw new ValidationException("Unsupported amount mode '{$mode}'."),
        };
    }

    private function validateListAmounts(mixed $amounts, string $itemId): void
    {
        if (!is_array($amounts) || $amounts === []) {
            throw new ValidationException("Item '{$itemId}' amounts must be a non-empty array.");
        }

        foreach ($amounts as $value) {
            if (!is_numeric($value)) {
                throw new ValidationException("Item '{$itemId}' list amounts must contain only numeric values.");
            }
        }
    }

    private function validateRangeAmounts(mixed $amounts, string $itemId): void
    {
        if (!is_string($amounts)) {
            throw new ValidationException("Item '{$itemId}' range amounts must be a string: min,max,bias.");
        }

        $parts = str_getcsv($amounts, ',', '"', '\\');
        if (count($parts) !== 3) {
            throw new ValidationException("Item '{$itemId}' range amounts must be in format: min,max,bias.");
        }

        foreach ($parts as $part) {
            if (!is_string($part) || !is_numeric(trim($part))) {
                throw new ValidationException("Item '{$itemId}' range values must be numeric.");
            }
        }
    }

    private function validateWeightedAmounts(mixed $amounts, string $itemId): void
    {
        if (!is_array($amounts) || $amounts === []) {
            throw new ValidationException("Item '{$itemId}' amounts must be a non-empty array.");
        }

        foreach ($amounts as $amount => $weight) {
            if (!is_numeric((string) $amount)) {
                throw new ValidationException("Item '{$itemId}' weighted amount keys must be numeric.");
            }
            DrawValidator::assertPositiveNumeric($weight, "Item '{$itemId}' weighted amount value");
        }
    }

    private function weightedAmountRange(string $amounts): float
    {
        $parts = str_getcsv($amounts, ',', '"', '\\');
        if (count($parts) !== 3) {
            throw new ValidationException('Invalid amount range (expected: min,max,bias).');
        }

        $min = $this->numericAsFloat($parts[0], 'range min');
        $max = $this->numericAsFloat($parts[1], 'range max');
        $bias = $this->numericAsFloat($parts[2], 'range bias');
        if ($max <= $min) {
            throw new ValidationException('Maximum value should be greater than minimum.');
        }
        if ($bias <= 0) {
            throw new ValidationException('Bias should be greater than 0.');
        }

        return max(
            min(
                round($min + $this->random->float() ** $bias * ($max - $min + 1), 12),
                $max,
            ),
            $min,
        );
    }
}
