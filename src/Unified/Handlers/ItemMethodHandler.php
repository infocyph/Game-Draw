<?php

declare(strict_types=1);

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
use Infocyph\Draw\Unified\Handlers\Support\NormalizesHandlerInput;
use Infocyph\Draw\Unified\Support\ResultBuilder;

class ItemMethodHandler implements MethodHandlerInterface
{
    use NormalizesHandlerInput;

    public function __construct(private readonly RandomGeneratorInterface $random) {}

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $method = $this->requiredString($request['method'] ?? null, 'method');
        $itemsRaw = $this->requireNonEmptyArray($request['items'] ?? null, 'items is required and must be a non-empty array.');
        if (!in_array($method, $this->methods(), true)) {
            throw new ValidationException("Unsupported item draw method: {$method}");
        }

        $options = $this->normalizeAssocArray($request['options'] ?? [], 'options');
        $normalizedItems = $this->normalizeRows($itemsRaw);

        return $this->executeFlexible($method, $normalizedItems, $options);
    }

    public function methods(): array
    {
        return [
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

    /**
     * @param list<array<string, mixed>> $items
     */
    private function checkFlexible(array $items, string $method): void
    {
        DrawValidator::assertNotEmpty($items, 'Items array must contain at least one item.');

        $requiredKeys = match ($method) {
            'weightedElimination', 'probability', 'weightedBatch' => ['name' => true, 'weight' => true],
            'timeBased' => ['name' => true, 'weight' => true, 'time' => true],
            'rangeWeighted' => ['name' => true, 'min' => true, 'max' => true, 'weight' => true],
            default => ['name' => true],
        };

        DrawValidator::assertRequiredKeys($items, $requiredKeys, 'Item');
        if ($method !== 'rangeWeighted') {
            return;
        }

        foreach ($items as $index => $item) {
            $min = $this->numericAsFloat($item['min'] ?? null, "Item at index {$index} min");
            $max = $this->numericAsFloat($item['max'] ?? null, "Item at index {$index} max");
            if ($min >= $max) {
                throw new ValidationException("For rangeWeighted draw, 'min' should be less than 'max'.");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function drawFlexibleBatch(
        string $method,
        FlexibleState $state,
        int $count,
        bool $withReplacement,
    ): array {
        return match ($method) {
            'batched' => (new BatchedDraw($this->random))->draw($state, $count, $withReplacement),
            'weightedBatch' => (new WeightedBatchDraw(new ProbabilityDraw($this->random)))->draw($state, $count),
            default => throw new ValidationException("Unsupported batch-capable method: {$method}"),
        };
    }

    private function drawFlexibleSingle(string $method, FlexibleState $state): string|float|int
    {
        return match ($method) {
            'probability' => (new ProbabilityDraw($this->random))->draw($state),
            'elimination' => (new EliminationDraw($this->random))->draw($state),
            'weightedElimination' => (new WeightedEliminationDraw($this->random))->draw($state),
            'roundRobin' => (new RoundRobinDraw())->draw($state),
            'cumulative' => (new CumulativeDraw($this->random))->draw($state),
            'timeBased' => (new TimeBasedWeightedDraw($this->random))->draw($state),
            'sequential' => (new SequentialDraw())->draw($state),
            'rangeWeighted' => (new RangeWeightedDraw($this->random))->draw($state),
            default => throw new ValidationException("Unsupported item draw method: {$method}"),
        };
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function executeFlexible(string $method, array $items, array $options): array
    {
        $count = max(1, $this->intValue($options['count'] ?? null, 1));
        $withReplacement = $this->boolValue($options['withReplacement'] ?? false);
        $check = $this->boolValue($options['check'] ?? true);

        $state = new FlexibleState($items);
        if ($check) {
            $this->checkFlexible($state->items, $method);
        }

        if (in_array($method, ['batched', 'weightedBatch'], true)) {
            $batch = $this->drawFlexibleBatch($method, $state, $count, $withReplacement);
            $entries = [];
            foreach ($batch as $value) {
                $entries[] = ResultBuilder::entry(
                    itemId: $value,
                    candidateId: null,
                    value: $value,
                );
            }

            return ResultBuilder::response($method, $entries, $batch, $count);
        }

        $entries = [];
        $raw = [];
        for ($i = 0; $i < $count; $i++) {
            $value = $this->drawFlexibleSingle($method, $state);
            $raw[] = $value;
            $entries[] = ResultBuilder::entry(
                itemId: is_string($value) ? $value : null,
                candidateId: null,
                value: $value,
            );
        }

        return ResultBuilder::response($method, $entries, $raw, $count);
    }
}
