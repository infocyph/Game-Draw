<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers;

use Infocyph\Draw\Audit\AuditTrail;
use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Random\SeededRandomGenerator;
use Infocyph\Draw\Rules\RuleSet;
use Infocyph\Draw\State\MemoryCachePool;
use Infocyph\Draw\Support\DrawValidator;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Handlers\Support\CampaignBatchFormatter;
use Infocyph\Draw\Unified\Handlers\Support\CampaignEngine;
use Infocyph\Draw\Unified\Handlers\Support\NormalizesHandlerInput;
use Infocyph\Draw\Unified\Support\ResultBuilder;
use Psr\Cache\CacheItemPoolInterface;

class CampaignMethodHandler implements MethodHandlerInterface
{
    use NormalizesHandlerInput;

    private const MAX_CANDIDATES = 1_000_000;

    private const MAX_EVALUATIONS = 100_000_000;

    private const MAX_ITEMS = 10_000;

    private const MAX_ITERATIONS = 100_000;

    private const MAX_PHASES = 1_000;

    private const MAX_TOTAL_SLOTS = 100_000;

    public function __construct(private readonly RandomGeneratorInterface $random) {}

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $context = $this->prepareExecutionContext($request);

        return match ($context['method']) {
            'campaign.run' => $this->runCampaign(
                users: $context['users'],
                items: $context['items'],
                rules: $context['rules'],
                auditSecret: $context['auditSecret'],
                options: $context['options'],
                eligibility: $context['eligibility'],
                cachePool: $context['cachePool'],
                random: $context['random'],
            ),
            'campaign.batch' => $this->runBatch(
                users: $context['users'],
                defaultRules: $context['rules'],
                auditSecret: $context['auditSecret'],
                options: $context['options'],
                eligibility: $context['eligibility'],
                cachePool: $context['cachePool'],
                random: $context['random'],
            ),
            'campaign.simulate' => $this->simulate(
                users: $context['users'],
                items: $context['items'],
                rules: $context['rules'],
                options: $context['options'],
                eligibility: $context['eligibility'],
            ),
            default => throw new ValidationException("Unsupported campaign method: {$context['method']}"),
        };
    }

    public function methods(): array
    {
        return ['campaign.run', 'campaign.batch', 'campaign.simulate'];
    }

    private function assertEvaluationBudget(int $slots, int $candidates, int $multiplier = 1): void
    {
        if ($slots === 0) {
            return;
        }

        $maximumCandidates = intdiv(self::MAX_EVALUATIONS, $slots);
        if ($candidates > $maximumCandidates) {
            throw new ValidationException('The campaign workload exceeds 100000000 candidate evaluations.');
        }

        $evaluations = $slots * $candidates;
        if ($multiplier > intdiv(self::MAX_EVALUATIONS, $evaluations)) {
            throw new ValidationException('The campaign workload exceeds 100000000 candidate evaluations.');
        }
    }

    private function firstDefinedString(mixed $first, mixed $second): string
    {
        $primary = $this->optionalString($first);
        if ($primary !== null) {
            return $primary;
        }

        return $this->optionalString($second) ?? '';
    }

    /**
     * @param array<string, int> $itemWins
     * @return array<string, array{wins: int, avgPerIteration: float}>
     */
    private function itemDistribution(array $itemWins, int $iterations): array
    {
        $distribution = [];
        foreach ($itemWins as $item => $wins) {
            $distribution[$item] = [
                'wins' => $wins,
                'avgPerIteration' => $wins / $iterations,
            ];
        }

        return $distribution;
    }

    /**
     * @return array{id: string, count: int, weight: float, group: ?string}
     */
    private function normalizeItem(int|string $itemKey, mixed $definition): array
    {
        if (is_int($definition)) {
            $itemId = is_string($itemKey) ? trim($itemKey) : '';
            $count = $definition;
            $weight = 1.0;
            $group = null;
        } elseif (is_array($definition)) {
            $itemId = is_string($itemKey) ? trim($itemKey) : $this->firstDefinedString(
                $definition['item'] ?? null,
                $definition['name'] ?? null,
            );
            $count = $this->intValue($definition['count'] ?? null, 1, "Item '{$itemId}' count");
            $weight = $this->floatValue($definition['weight'] ?? null, 1.0);
            $group = $this->optionalString($definition['group'] ?? null);
        } else {
            throw new ValidationException('Each campaign item must be an integer count or an item definition array.');
        }

        if ($itemId === '') {
            throw new ValidationException('Each campaign item must have a valid string identifier.');
        }

        DrawValidator::assertNonNegativeInt($count, "Item count for '{$itemId}'");
        DrawValidator::assertNonNegativeNumeric($weight, "Item weight for '{$itemId}'");

        return ['id' => $itemId, 'count' => $count, 'weight' => $weight, 'group' => $group];
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<string, array{count: int, weight: float, group: ?string}>
     */
    private function normalizeItems(array $items): array
    {
        DrawValidator::assertNotEmpty($items, 'Items array must contain at least one item.');
        DrawValidator::assertCountAtMost($items, self::MAX_ITEMS, 'items');
        $normalized = [];

        foreach ($items as $itemKey => $itemDefinition) {
            $item = $this->normalizeItem($itemKey, $itemDefinition);
            $itemId = $item['id'];
            if (isset($normalized[$itemId])) {
                throw new ValidationException("Campaign item id '{$itemId}' must be unique.");
            }

            $normalized[$itemId] = [
                'count' => $item['count'],
                'weight' => $item['weight'],
                'group' => $item['group'],
            ];
        }

        $this->sumItemCounts($normalized);

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $users
     * @return list<string>
     */
    private function normalizeUsers(array $users): array
    {
        $normalized = $this->normalizeCandidateIds($users, self::MAX_CANDIDATES);
        DrawValidator::assertNotEmpty($normalized, 'candidates must contain at least one user id.');

        return $normalized;
    }

    /**
     * @return array{
     *   name: string,
     *   items: array<string, array{count: int, weight: float, group: ?string}>,
     *   rules: RuleSet,
     *   random: RandomGeneratorInterface
     * }
     */
    private function prepareBatchPhase(
        int|string $index,
        mixed $phaseRaw,
        RuleSet $defaultRules,
        RandomGeneratorInterface $random,
    ): array {
        if (!is_array($phaseRaw)) {
            throw new ValidationException("Phase at index {$index} must be an array.");
        }

        $phaseName = $this->resolvePhaseName($index, $phaseRaw);
        if (!isset($phaseRaw['items']) || !is_array($phaseRaw['items'])) {
            throw new ValidationException("Phase '{$phaseName}' must define items.");
        }

        return [
            'name' => $phaseName,
            'items' => $this->normalizeItems($phaseRaw['items']),
            'rules' => $this->resolvePhaseRules($phaseRaw['rules'] ?? $defaultRules, $phaseName),
            'random' => array_key_exists('seed', $phaseRaw)
                ? new SeededRandomGenerator($this->intValue($phaseRaw['seed'], 0, "Phase '{$phaseName}' seed"))
                : $random,
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @return array{
     *   method: string,
     *   options: array<string, mixed>,
     *   users: list<string>,
     *   items: array<string, array{count: int, weight: float, group: ?string}>,
     *   rules: RuleSet,
     *   auditSecret: string,
     *   eligibility: ?callable,
     *   cachePool: CacheItemPoolInterface,
     *   random: RandomGeneratorInterface
     * }
     */
    private function prepareExecutionContext(array $request): array
    {
        $method = $this->requiredString($request['method'] ?? null, 'method');
        $options = $this->normalizeAssocArray($request['options'] ?? [], 'options');
        $users = $this->normalizeUsers($this->requireNonEmptyArray(
            $request['candidates'] ?? null,
            'candidates is required and must be a non-empty array for campaign methods.',
        ));
        $items = $method === 'campaign.batch'
            ? []
            : $this->normalizeItems($this->requireNonEmptyArray(
                $request['items'] ?? null,
                'items is required and must be a non-empty array.',
            ));

        return [
            'method' => $method,
            'options' => $options,
            'users' => $users,
            'items' => $items,
            'rules' => RuleSet::fromArray($this->normalizeAssocArray($options['rules'] ?? [], 'options.rules')),
            'auditSecret' => $this->optionalString($options['auditSecret'] ?? null) ?? '',
            'eligibility' => $this->resolveEligibility($options),
            'cachePool' => $this->resolveCachePool($options),
            'random' => $this->resolveRandom($options),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveCachePool(array $options): CacheItemPoolInterface
    {
        $pool = $options['cachePool'] ?? null;
        if ($pool === null) {
            return new MemoryCachePool();
        }
        if (!$pool instanceof CacheItemPoolInterface) {
            throw new ValidationException(
                'options.cachePool must implement ' . CacheItemPoolInterface::class . '.',
            );
        }

        return $pool;
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

    /**
     * @param array<int|string, mixed> $phase
     */
    private function resolvePhaseName(int|string $index, array $phase): string
    {
        $defaultName = is_string($index) && $index !== ''
            ? "phase_{$index}"
            : 'phase_' . ((is_int($index) ? $index : 0) + 1);

        return $this->optionalString($phase['name'] ?? null) ?? $defaultName;
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
            return new SeededRandomGenerator($this->intValue($options['seed'], 0, 'options.seed'));
        }

        return $this->random;
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
        CacheItemPoolInterface $cachePool,
        RandomGeneratorInterface $random,
    ): array {
        $phasesRaw = $options['phases'] ?? null;
        if (!is_array($phasesRaw) || $phasesRaw === []) {
            throw new ValidationException('options.phases is required and must be a non-empty array for campaign.batch.');
        }
        DrawValidator::assertCountAtMost($phasesRaw, self::MAX_PHASES, 'options.phases');

        $preparedPhases = [];
        $phaseNames = [];
        $requestedCount = 0;
        foreach ($phasesRaw as $index => $phaseRaw) {
            $phase = $this->prepareBatchPhase($index, $phaseRaw, $defaultRules, $random);
            if (isset($phaseNames[$phase['name']])) {
                throw new ValidationException("Campaign phase name '{$phase['name']}' must be unique.");
            }
            $phaseNames[$phase['name']] = true;
            $preparedPhases[] = $phase;
            $requestedCount += $this->sumItemCounts($phase['items']);
            if ($requestedCount > self::MAX_TOTAL_SLOTS) {
                throw new ValidationException('The total campaign batch slot count exceeds 100000.');
            }
        }

        $withExplain = $this->boolValue($options['withExplain'] ?? false, 'options.withExplain');
        $retryLimit = $this->intValue($options['retryLimit'] ?? null, 100, 'options.retryLimit');
        DrawValidator::assertPositiveIntWithin($retryLimit, self::MAX_TOTAL_SLOTS, 'options.retryLimit');
        $this->assertEvaluationBudget($requestedCount, count($users));
        $phaseResults = [];

        foreach ($preparedPhases as $preparedPhase) {
            $phase = $this->runBatchPhase(
                phase: $preparedPhase,
                users: $users,
                auditSecret: $auditSecret,
                eligibility: $eligibility,
                cachePool: $cachePool,
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
            $requestedCount,
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
     * @param array{
     *   name: string,
     *   items: array<string, array{count: int, weight: float, group: ?string}>,
     *   rules: RuleSet,
     *   random: RandomGeneratorInterface
     * } $phase
     * @param list<string> $users
     * @return array{name: string, result: array<string, mixed>}
     */
    private function runBatchPhase(
        array $phase,
        array $users,
        string $auditSecret,
        ?callable $eligibility,
        CacheItemPoolInterface $cachePool,
        bool $withExplain,
    ): array {
        $phaseName = $phase['name'];
        $phaseItems = $phase['items'];
        $phaseRules = $phase['rules'];
        $phaseRandom = $phase['random'];

        $engine = new CampaignEngine($phaseRules, $cachePool, $phaseRandom, $eligibility, $withExplain);
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
        CacheItemPoolInterface $cachePool,
        RandomGeneratorInterface $random,
    ): array {
        $withExplain = $this->boolValue($options['withExplain'] ?? false, 'options.withExplain');
        $retryLimit = $this->intValue($options['retryLimit'] ?? null, 100, 'options.retryLimit');
        DrawValidator::assertPositiveIntWithin($retryLimit, self::MAX_TOTAL_SLOTS, 'options.retryLimit');
        $this->assertEvaluationBudget($this->sumItemCounts($items), count($users));

        $engine = new CampaignEngine($rules, $cachePool, $random, $eligibility, $withExplain);
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
     * @return array{0: array<string, int>, 1: array<string, int>}
     */
    private function runSimulationIterations(
        array $users,
        array $items,
        RuleSet $rules,
        ?callable $eligibility,
        int $iterations,
        int $seedBase,
    ): array {
        $userWins = array_fill_keys($users, 0);
        $itemWins = array_fill_keys(array_keys($items), 0);

        for ($i = 1; $i <= $iterations; $i++) {
            $random = new SeededRandomGenerator($seedBase + $i);
            $engine = new CampaignEngine($rules, new MemoryCachePool(), $random, $eligibility, false);
            $result = $engine->run($users, $items)['winners'];

            foreach ($result as $item => $winners) {
                $itemWins[$item] += count($winners);
                foreach ($winners as $winner) {
                    $userWins[$winner]++;
                }
            }
        }

        return [$userWins, $itemWins];
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
        $iterations = $this->intValue($options['iterations'] ?? null, 1000, 'options.iterations');
        DrawValidator::assertPositiveIntWithin($iterations, self::MAX_ITERATIONS, 'options.iterations');
        $retryLimit = $this->intValue($options['retryLimit'] ?? null, 100, 'options.retryLimit');
        DrawValidator::assertPositiveIntWithin($retryLimit, self::MAX_TOTAL_SLOTS, 'options.retryLimit');
        $seedBase = $this->intValue($options['seed'] ?? null, 0, 'options.seed');
        if ($seedBase > PHP_INT_MAX - $iterations) {
            throw new ValidationException('options.seed is too large for the requested iteration count.');
        }

        $totalSlotsPerIteration = $this->sumItemCounts($items);
        if ($totalSlotsPerIteration > 0 && $iterations > intdiv(PHP_INT_MAX, $totalSlotsPerIteration)) {
            throw new ValidationException('The simulation workload exceeds the supported integer range.');
        }
        $this->assertEvaluationBudget($totalSlotsPerIteration, count($users), $iterations);

        [$userWins, $itemWins] = $this->runSimulationIterations(
            users: $users,
            items: $items,
            rules: $rules,
            eligibility: $eligibility,
            iterations: $iterations,
            seedBase: $seedBase,
        );

        $totalSlots = $totalSlotsPerIteration * $iterations;
        $userDistribution = $this->userDistribution($userWins, $totalSlots);
        $itemDistribution = $this->itemDistribution($itemWins, $iterations);

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
            count($entries),
            [
                'iterations' => $iterations,
                'retryLimit' => $retryLimit,
                'selectionMode' => 'eligible_pool',
                'seedBase' => $seedBase,
            ],
        );
    }

    /**
     * @param array<string, array{count: int, weight: float, group: ?string}> $items
     */
    private function sumItemCounts(array $items): int
    {
        $sum = 0;
        foreach ($items as $item) {
            if ($item['count'] > self::MAX_TOTAL_SLOTS - $sum) {
                throw new ValidationException('The total campaign slot count exceeds 100000.');
            }
            $sum += $item['count'];
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
     * @param array<string, int> $userWins
     * @return array<string, array{wins: int, rate: float, ci95: array{low: float, high: float}}>
     */
    private function userDistribution(array $userWins, int $totalSlots): array
    {
        $distribution = [];
        foreach ($userWins as $user => $wins) {
            $rate = $totalSlots === 0 ? 0.0 : $wins / $totalSlots;
            $margin = $totalSlots === 0 ? 0.0 : 1.96 * sqrt(($rate * (1 - $rate)) / $totalSlots);
            $distribution[$user] = [
                'wins' => $wins,
                'rate' => $rate,
                'ci95' => [
                    'low' => max(0.0, $rate - $margin),
                    'high' => min(1.0, $rate + $margin),
                ],
            ];
        }

        return $distribution;
    }
}
