<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers;

use Infocyph\Draw\Audit\AuditTrail;
use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Contracts\StateAdapterInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Random\SeededRandomGenerator;
use Infocyph\Draw\Rules\RuleSet;
use Infocyph\Draw\State\MemoryStateAdapter;
use Infocyph\Draw\Support\DrawValidator;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Handlers\Support\CampaignBatchFormatter;
use Infocyph\Draw\Unified\Handlers\Support\CampaignEngine;
use Infocyph\Draw\Unified\Support\ResultBuilder;

class CampaignMethodHandler implements MethodHandlerInterface
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $method = $this->requiredString($request['method'] ?? null, 'method');
        $items = $request['items'] ?? null;
        $candidatesRaw = $request['candidates'] ?? null;
        $optionsRaw = $request['options'] ?? [];

        if (!is_array($candidatesRaw) || $candidatesRaw === []) {
            throw new ValidationException('candidates is required and must be a non-empty array for campaign methods.');
        }
        if (!is_array($optionsRaw)) {
            throw new ValidationException('options must be an array when provided.');
        }

        $options = $this->normalizeAssocArray($optionsRaw, 'options');
        $users = $this->normalizeUsers($candidatesRaw);
        if ($method === 'campaign.batch') {
            $normalizedItems = [];
        } else {
            if (!is_array($items) || $items === []) {
                throw new ValidationException('items is required and must be a non-empty array.');
            }
            $normalizedItems = $this->normalizeItems($items);
        }

        $rules = RuleSet::fromArray($this->normalizeAssocArray($options['rules'] ?? [], 'options.rules'));
        $auditSecret = $this->optionalString($options['auditSecret'] ?? null) ?? '';
        $eligibility = $this->resolveEligibility($options);
        $stateAdapter = $this->resolveStateAdapter($options);
        $random = $this->resolveRandom($options);

        return match ($method) {
            'campaign.run' => $this->runCampaign(
                users: $users,
                items: $normalizedItems,
                rules: $rules,
                auditSecret: $auditSecret,
                options: $options,
                eligibility: $eligibility,
                stateAdapter: $stateAdapter,
                random: $random,
            ),
            'campaign.batch' => $this->runBatch(
                users: $users,
                defaultRules: $rules,
                auditSecret: $auditSecret,
                options: $options,
                eligibility: $eligibility,
                stateAdapter: $stateAdapter,
                random: $random,
            ),
            'campaign.simulate' => $this->simulate(
                users: $users,
                items: $normalizedItems,
                rules: $rules,
                options: $options,
                eligibility: $eligibility,
            ),
            default => throw new ValidationException("Unsupported campaign method: {$method}"),
        };
    }

    public function methods(): array
    {
        return ['campaign.run', 'campaign.batch', 'campaign.simulate'];
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

    private function firstDefinedString(mixed $first, mixed $second): string
    {
        $primary = $this->optionalString($first);
        if ($primary !== null) {
            return $primary;
        }

        return $this->optionalString($second) ?? '';
    }

    private function floatValue(mixed $value, float $default): float
    {
        return match (true) {
            is_int($value) => (float) $value,
            is_float($value) => $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => $default,
        };
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
     * @return array<string, array{count: int, weight: float, group: ?string}>
     */
    private function normalizeItems(array $items): array
    {
        DrawValidator::assertNotEmpty($items, 'Items array must contain at least one item.');
        $normalized = [];

        foreach ($items as $itemKey => $itemDefinition) {
            $itemId = '';
            $count = 1;
            $weight = 1.0;
            $group = null;

            if (is_array($itemDefinition)) {
                $itemId = is_string($itemKey) ? trim($itemKey) : $this->firstDefinedString(
                    $itemDefinition['item'] ?? null,
                    $itemDefinition['name'] ?? null,
                );
                $count = max(0, $this->intValue($itemDefinition['count'] ?? null, 1));
                $weight = max(0.0, $this->floatValue($itemDefinition['weight'] ?? null, 1.0));
                $group = $this->optionalString($itemDefinition['group'] ?? null);
            } elseif (is_int($itemDefinition)) {
                $itemId = is_string($itemKey) ? trim($itemKey) : '';
                $count = max(0, $itemDefinition);
            }

            if ($itemId === '') {
                throw new ValidationException('Each campaign item must have a valid string identifier.');
            }

            DrawValidator::assertNonNegativeInt($count, "Item count for '{$itemId}'");
            DrawValidator::assertNonNegativeNumeric($weight, "Item weight for '{$itemId}'");
            $normalized[$itemId] = [
                'count' => $count,
                'weight' => $weight,
                'group' => $group,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $users
     * @return list<string>
     */
    private function normalizeUsers(array $users): array
    {
        $normalized = [];
        foreach ($users as $user) {
            $userId = $this->optionalString($user);
            if ($userId !== null) {
                $normalized[] = $userId;
            }
        }

        $normalized = array_values(array_unique($normalized));
        DrawValidator::assertNotEmpty($normalized, 'candidates must contain at least one user id.');

        return $normalized;
    }

    private function optionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new ValidationException("{$field} is required and must be a non-empty string.");
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveEligibility(array $options): ?callable
    {
        $eligibility = $options['eligibility'] ?? null;
        if ($eligibility === null) {
            return null;
        }
        if (!is_callable($eligibility)) {
            throw new ValidationException('options.eligibility must be callable when provided.');
        }

        return $eligibility;
    }

    private function resolvePhaseRules(mixed $rules, string $phaseName): RuleSet
    {
        if ($rules instanceof RuleSet) {
            return $rules;
        }
        if (!is_array($rules)) {
            throw new ValidationException("Phase '{$phaseName}' has invalid rules.");
        }

        return RuleSet::fromArray($this->normalizeAssocArray($rules, "phase '{$phaseName}' rules"));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveRandom(array $options): RandomGeneratorInterface
    {
        if (array_key_exists('seed', $options)) {
            return new SeededRandomGenerator($this->intValue($options['seed'], 0));
        }

        return $this->random;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveStateAdapter(array $options): StateAdapterInterface
    {
        $adapter = $options['stateAdapter'] ?? null;
        if ($adapter === null) {
            return new MemoryStateAdapter();
        }
        if (!$adapter instanceof StateAdapterInterface) {
            throw new ValidationException(
                'options.stateAdapter must implement ' . StateAdapterInterface::class . '.',
            );
        }

        return $adapter;
    }

    /**
     * @param list<string> $users
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function runBatch(
        array $users,
        RuleSet $defaultRules,
        string $auditSecret,
        array $options,
        ?callable $eligibility,
        StateAdapterInterface $stateAdapter,
        RandomGeneratorInterface $random,
    ): array {
        $phasesRaw = $options['phases'] ?? null;
        if (!is_array($phasesRaw) || $phasesRaw === []) {
            throw new ValidationException('options.phases is required and must be a non-empty array for campaign.batch.');
        }

        $withExplain = $this->boolValue($options['withExplain'] ?? false);
        $retryLimit = max(1, $this->intValue($options['retryLimit'] ?? null, 100));
        $phaseResults = [];

        foreach ($phasesRaw as $index => $phaseRaw) {
            $phase = $this->runBatchPhase(
                index: $index,
                phaseRaw: $phaseRaw,
                users: $users,
                defaultRules: $defaultRules,
                auditSecret: $auditSecret,
                eligibility: $eligibility,
                stateAdapter: $stateAdapter,
                random: $random,
                withExplain: $withExplain,
            );
            $phaseResults[$phase['name']] = $phase['result'];
        }

        $raw = [
            'phases' => $phaseResults,
            'partialReason' => $this->summarizePhasePartialReason($phaseResults),
            'audit' => AuditTrail::create(
                configuration: [
                    'feature' => 'batch-campaign',
                    'phaseCount' => count($phasesRaw),
                    'selectionMode' => 'eligible_pool',
                ],
                result: $phaseResults,
                seedFingerprint: $random->seedFingerprint(),
                secret: $auditSecret,
            ),
        ];

        $entries = CampaignBatchFormatter::buildEntries($phaseResults);

        return ResultBuilder::response(
            'campaign.batch',
            $entries,
            $raw,
            $this->sumPhaseCounts($phasesRaw),
            [
                'phaseCount' => count($phasesRaw),
                'withExplain' => $withExplain,
                'retryLimit' => $retryLimit,
                'selectionMode' => 'eligible_pool',
                'partialReason' => $raw['partialReason'],
            ],
        );
    }

    /**
     * @param list<string> $users
     * @return array{name: string, result: array<string, mixed>}
     */
    private function runBatchPhase(
        int|string $index,
        mixed $phaseRaw,
        array $users,
        RuleSet $defaultRules,
        string $auditSecret,
        ?callable $eligibility,
        StateAdapterInterface $stateAdapter,
        RandomGeneratorInterface $random,
        bool $withExplain,
    ): array {
        if (!is_array($phaseRaw)) {
            throw new ValidationException("Phase at index {$index} must be an array.");
        }

        $defaultPhaseName = is_string($index) && $index !== ''
            ? "phase_{$index}"
            : 'phase_' . ((is_int($index) ? $index : 0) + 1);
        $phaseName = $this->optionalString($phaseRaw['name'] ?? null) ?? $defaultPhaseName;
        if (!array_key_exists('items', $phaseRaw) || !is_array($phaseRaw['items'])) {
            throw new ValidationException("Phase '{$phaseName}' must define items.");
        }

        $phaseItems = $this->normalizeItems($phaseRaw['items']);
        $phaseRules = $this->resolvePhaseRules($phaseRaw['rules'] ?? $defaultRules, $phaseName);
        $phaseRandom = array_key_exists('seed', $phaseRaw)
            ? new SeededRandomGenerator($this->intValue($phaseRaw['seed'], 0))
            : $random;

        $engine = new CampaignEngine($phaseRules, $stateAdapter, $phaseRandom, $eligibility, $withExplain);
        $phaseResult = $engine->run($users, $phaseItems);
        $phaseResult['audit'] = AuditTrail::create(
            configuration: [
                'feature' => 'campaign-phase',
                'name' => $phaseName,
                'usersCount' => count($users),
                'items' => $phaseItems,
                'rules' => $phaseRules->toArray(),
                'selectionMode' => 'eligible_pool',
            ],
            result: $phaseResult['winners'],
            seedFingerprint: $phaseRandom->seedFingerprint(),
            secret: $auditSecret,
        );

        return ['name' => $phaseName, 'result' => $phaseResult];
    }

    /**
     * @param list<string> $users
     * @param array<string, array{count: int, weight: float, group: ?string}> $items
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function runCampaign(
        array $users,
        array $items,
        RuleSet $rules,
        string $auditSecret,
        array $options,
        ?callable $eligibility,
        StateAdapterInterface $stateAdapter,
        RandomGeneratorInterface $random,
    ): array {
        $withExplain = $this->boolValue($options['withExplain'] ?? false);
        $retryLimit = max(1, $this->intValue($options['retryLimit'] ?? null, 100));

        $engine = new CampaignEngine($rules, $stateAdapter, $random, $eligibility, $withExplain);
        $raw = $engine->run($users, $items);
        $raw['audit'] = AuditTrail::create(
            configuration: [
                'feature' => 'campaign',
                'usersCount' => count($users),
                'items' => $items,
                'rules' => $rules->toArray(),
                'selectionMode' => 'eligible_pool',
            ],
            result: $raw['winners'],
            seedFingerprint: $random->seedFingerprint(),
            secret: $auditSecret,
        );

        $entries = [];
        foreach ($raw['winners'] as $item => $winners) {
            foreach ($winners as $winner) {
                $entries[] = ResultBuilder::entry($item, $winner, $winner);
            }
        }

        return ResultBuilder::response(
            'campaign.run',
            $entries,
            $raw,
            $this->sumItemCounts($items),
            [
                'withExplain' => $withExplain,
                'retryLimit' => $retryLimit,
                'selectionMode' => 'eligible_pool',
                'partialReason' => $raw['partialReason'],
            ],
        );
    }

    /**
     * @param list<string> $users
     * @param array<string, array{count: int, weight: float, group: ?string}> $items
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function simulate(
        array $users,
        array $items,
        RuleSet $rules,
        array $options,
        ?callable $eligibility,
    ): array {
        $iterations = max(1, $this->intValue($options['iterations'] ?? null, 1000));
        $retryLimit = max(1, $this->intValue($options['retryLimit'] ?? null, 100));
        $seedBase = $this->intValue($options['seed'] ?? null, 0);

        $userWins = [];
        foreach ($users as $user) {
            $userWins[$user] = 0;
        }
        $itemWins = [];
        foreach (array_keys($items) as $itemId) {
            $itemWins[$itemId] = 0;
        }
        $totalSlotsPerIteration = $this->sumItemCounts($items);

        for ($i = 1; $i <= $iterations; $i++) {
            $iterationRandom = new SeededRandomGenerator($seedBase + $i);
            $engine = new CampaignEngine($rules, new MemoryStateAdapter(), $iterationRandom, $eligibility, false);
            $result = $engine->run($users, $items)['winners'];

            foreach ($result as $item => $winners) {
                $itemWins[$item] += count($winners);
                foreach ($winners as $winner) {
                    $userWins[$winner] = ($userWins[$winner] ?? 0) + 1;
                }
            }
        }

        $totalSlots = max(1, $totalSlotsPerIteration * $iterations);
        $userDistribution = [];
        foreach ($userWins as $user => $wins) {
            $rate = $wins / $totalSlots;
            $margin = 1.96 * sqrt(($rate * (1 - $rate)) / $totalSlots);
            $userDistribution[$user] = [
                'wins' => $wins,
                'rate' => $rate,
                'ci95' => [
                    'low' => max(0.0, $rate - $margin),
                    'high' => min(1.0, $rate + $margin),
                ],
            ];
        }

        $itemDistribution = [];
        foreach ($itemWins as $item => $wins) {
            $itemDistribution[$item] = [
                'wins' => $wins,
                'avgPerIteration' => $wins / $iterations,
            ];
        }

        $raw = [
            'iterations' => $iterations,
            'totalSlots' => $totalSlots,
            'userDistribution' => $userDistribution,
            'itemDistribution' => $itemDistribution,
        ];

        $entries = [];
        foreach ($raw['userDistribution'] as $user => $distribution) {
            $entries[] = ResultBuilder::entry(
                itemId: null,
                candidateId: $user,
                value: $distribution['rate'],
                meta: ['kind' => 'userDistribution', 'wins' => $distribution['wins']],
            );
        }

        return ResultBuilder::response(
            'campaign.simulate',
            $entries,
            $raw,
            $iterations,
            [
                'iterations' => $iterations,
                'retryLimit' => $retryLimit,
                'selectionMode' => 'eligible_pool',
                'seedBase' => $seedBase,
            ],
        );
    }

    /**
     * @param array<int|string, mixed>|array<string, array{count: int, weight: float, group: ?string}> $items
     */
    private function sumItemCounts(array $items): int
    {
        $sum = 0;
        foreach ($items as $item) {
            if (is_int($item)) {
                $sum += max(0, $item);

                continue;
            }
            if (is_array($item)) {
                $sum += max(0, $this->intValue($item['count'] ?? null, 1));
            }
        }

        return $sum;
    }

    /**
     * @param array<string, mixed> $phaseResults
     */
    private function summarizePhasePartialReason(array $phaseResults): ?string
    {
        $reasons = [];
        foreach ($phaseResults as $phaseResult) {
            if (!is_array($phaseResult)) {
                continue;
            }
            $reason = $phaseResult['partialReason'] ?? null;
            if (is_string($reason)) {
                $reasons[] = $reason;
            }
        }

        if ($reasons === []) {
            return null;
        }

        $counts = array_count_values($reasons);
        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * @param array<int|string, mixed> $phases
     */
    private function sumPhaseCounts(array $phases): int
    {
        $sum = 0;
        foreach ($phases as $phase) {
            if (!is_array($phase)) {
                continue;
            }
            $items = $phase['items'] ?? [];
            if (!is_array($items)) {
                continue;
            }
            $sum += $this->sumItemCounts($items);
        }

        return $sum;
    }
}
