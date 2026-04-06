<?php

namespace Infocyph\Draw\Unified\Handlers;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Flexible\BatchedDraw;
use Infocyph\Draw\Flexible\CumulativeDraw;
use Infocyph\Draw\Flexible\EliminationDraw;
use Infocyph\Draw\Flexible\FlexibleState;
use Infocyph\Draw\Flexible\ProbabilityDraw;
use Infocyph\Draw\Flexible\RangeWeightedDraw;
use Infocyph\Draw\Flexible\RoundRobinDraw;
use Infocyph\Draw\Flexible\SequentialDraw;
use Infocyph\Draw\Flexible\TimeBasedWeightedDraw;
use Infocyph\Draw\Flexible\WeightedBatchDraw;
use Infocyph\Draw\Flexible\WeightedEliminationDraw;
use Infocyph\Draw\Support\DrawValidator;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Support\ResultBuilder;

class ItemMethodHandler implements MethodHandlerInterface
{
    public function __construct(private readonly RandomGeneratorInterface $random)
    {
    }

    public function execute(array $request): array
    {
        $method = (string)($request['method'] ?? '');
        $items = $request['items'] ?? null;
        $options = $request['options'] ?? [];

        if (!is_array($items) || empty($items)) {
            throw new ValidationException('items is required and must be a non-empty array.');
        }
        if (!is_array($options)) {
            throw new ValidationException('options must be an array when provided.');
        }

        return $method === 'lucky'
            ? $this->executeLucky($items, $options)
            : $this->executeFlexible($method, $items, $options);
    }

    public function methods(): array
    {
        return [
            'lucky',
            'probability',
            'elimination',
            'weightedElimination',
            'roundRobin',
            'cumulative',
            'batched',
            'timeBased',
            'weightedBatch',
            'sequential',
            'rangeWeighted',
        ];
    }

    private function checkFlexible(array $items, string $method): void
    {
        DrawValidator::assertNotEmpty($items, "Items array must contain at least one item.");

        $requiredKeys = match ($method) {
            'weightedElimination', 'probability', 'weightedBatch' => ['name' => true, 'weight' => true],
            'timeBased' => ['name' => true, 'weight' => true, 'time' => true],
            'rangeWeighted' => ['name' => true, 'min' => true, 'max' => true, 'weight' => true],
            default => ['name' => true]
        };

        DrawValidator::assertRequiredKeys($items, $requiredKeys, "Item");
        foreach ($items as $item) {
            if ($method === 'rangeWeighted' && $item['min'] >= $item['max']) {
                throw new ValidationException("For rangeWeighted draw, 'min' should be less than 'max'.");
            }
        }
    }

    private function checkLucky(array $items): void
    {
        DrawValidator::assertNotEmpty($items, 'Items array must contain at least one item.');
        DrawValidator::assertRequiredKeys(
            $items,
            ['item' => true, 'chances' => true, 'amounts' => true],
            'Item',
        );
    }

    private function drawWeighted(array $items): string
    {
        if (count($items) === 1) {
            return (string)key($items);
        }

        $random = $this->random->int(1, array_sum($items));
        foreach ($items as $key => $value) {
            $random -= (int)$value;
            if ($random <= 0) {
                return (string)$key;
            }
        }

        return (string)array_search(max($items), $items, true);
    }

    private function executeFlexible(string $method, array $items, array $options): array
    {
        $count = max(1, (int)($options['count'] ?? 1));
        $withReplacement = (bool)($options['withReplacement'] ?? false);
        $check = (bool)($options['check'] ?? true);

        $state = new FlexibleState($items);
        $strategy = $this->strategy($method);

        if ($check) {
            $this->checkFlexible($state->items, $method);
        }

        $entries = [];
        $raw = [];

        if (in_array($method, ['batched', 'weightedBatch'], true)) {
            $result = match ($method) {
                'batched' => $strategy->draw($state, $count, $withReplacement),
                'weightedBatch' => $strategy->draw($state, $count),
                default => throw new ValidationException("Unsupported batch-capable method: {$method}"),
            };
            $raw = $result;
            foreach ((array)$result as $value) {
                $entries[] = ResultBuilder::entry(
                    itemId: is_string($value) ? $value : null,
                    candidateId: null,
                    value: $value,
                );
            }

            return ResultBuilder::response($method, $entries, $raw, $count);
        }

        for ($i = 0; $i < $count; $i++) {
            $value = $strategy->draw($state);
            $raw[] = $value;
            $entries[] = ResultBuilder::entry(
                itemId: is_string($value) ? $value : null,
                candidateId: null,
                value: $value,
            );
        }

        return ResultBuilder::response($method, $entries, $raw, $count);
    }

    private function executeLucky(array $items, array $options): array
    {
        $count = max(1, (int)($options['count'] ?? 1));
        $check = (bool)($options['check'] ?? true);
        $entries = [];
        $raw = [];

        if ($check) {
            $this->checkLucky($items);
        }

        for ($i = 0; $i < $count; $i++) {
            $pick = $this->pickLucky($items);
            $raw[] = $pick;
            $entries[] = ResultBuilder::entry(
                itemId: (string)$pick['item'],
                candidateId: null,
                value: $pick['amount'],
                meta: ['amount' => $pick['amount']],
            );
        }

        return ResultBuilder::response('lucky', $entries, $raw, $count);
    }

    private function fractionLength(array $items): int
    {
        $length = 0;
        foreach ($items as $item) {
            DrawValidator::assertPositiveNumeric($item, 'Chances');
            $value = (string)$item;
            $dot = strpos($value, '.');
            $dot !== false && $length = max($length, strlen($value) - $dot - 1);
        }
        return $length;
    }

    private function isSequential(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    private function normalizeNumericKey(string $value): float|int
    {
        if (!is_numeric($value)) {
            throw new ValidationException('Weighted amount keys must be numeric.');
        }
        return str_contains($value, '.') ? (float)$value : (int)$value;
    }

    private function pickLucky(array $items): array
    {
        $preparedItems = $this->prepareWeighted(array_filter(array_column($items, 'chances', 'item')));
        if (empty($preparedItems)) {
            throw new ValidationException('At least one item must have a positive chance.');
        }

        $pickedItem = $this->drawWeighted($preparedItems);
        $index = array_search($pickedItem, array_column($items, 'item'), true);
        if ($index === false) {
            throw new ValidationException('Selected item could not be resolved.');
        }

        $amounts = $items[$index]['amounts'];
        is_string($amounts) && $amounts = [$this->weightedAmountRange($amounts)];

        return [
            'item' => $pickedItem,
            'amount' => $this->selectLuckyAmount((array)$amounts),
        ];
    }

    private function prepareWeighted(array $items): array
    {
        $length = $this->fractionLength($items);
        if ($length === 0) {
            return $items;
        }

        $multiplier = 10 ** $length;
        return array_map(fn ($value) => (int)bcmul((string)$value, (string)$multiplier), $items);
    }

    private function selectLuckyAmount(array $amounts): float|int
    {
        return match (true) {
            count($amounts) === 1 => current($amounts),
            $this->isSequential($amounts) => $amounts[$this->random->pickArrayKey($amounts)],
            default => $this->normalizeNumericKey($this->drawWeighted($amounts)),
        };
    }

    private function strategy(string $method): object
    {
        return match ($method) {
            'probability' => new ProbabilityDraw($this->random),
            'elimination' => new EliminationDraw($this->random),
            'weightedElimination' => new WeightedEliminationDraw($this->random),
            'roundRobin' => new RoundRobinDraw(),
            'cumulative' => new CumulativeDraw($this->random),
            'batched' => new BatchedDraw($this->random),
            'timeBased' => new TimeBasedWeightedDraw($this->random),
            'weightedBatch' => new WeightedBatchDraw(new ProbabilityDraw($this->random)),
            'sequential' => new SequentialDraw(),
            'rangeWeighted' => new RangeWeightedDraw($this->random),
            default => throw new ValidationException("Unsupported item draw method: {$method}"),
        };
    }

    private function weightedAmountRange(string $amounts): float|int
    {
        $parts = str_getcsv($amounts, ',', '"', '\\');
        count($parts) !== 3 && throw new ValidationException('Invalid amount range (expected: min,max,bias).');
        [$min, $max, $bias] = array_map('floatval', $parts);
        $max <= $min && throw new ValidationException('Maximum value should be greater than minimum.');
        $bias <= 0 && throw new ValidationException('Bias should be greater than 0.');

        return max(
            min(
                round($min + $this->random->float() ** $bias * ($max - $min + 1), $this->fractionLength([$min, $max])),
                $max,
            ),
            $min,
        );
    }
}
