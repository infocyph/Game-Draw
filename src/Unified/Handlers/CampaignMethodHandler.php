<?php

namespace Infocyph\Draw\Unified\Handlers;

use Infocyph\Draw\Audit\AuditTrail;
use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Contracts\StateAdapterInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Random\SeededRandomGenerator;
use Infocyph\Draw\Rules\RuleEngine;
use Infocyph\Draw\Rules\RuleSet;
use Infocyph\Draw\State\MemoryStateAdapter;
use Infocyph\Draw\Support\DrawValidator;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Support\ResultBuilder;

class CampaignMethodHandler implements MethodHandlerInterface
{
    public function __construct(private readonly RandomGeneratorInterface $random)
    {
    }

    public function execute(array $request): array
    {
        $method = (string)($request['method'] ?? '');
        $items = $request['items'] ?? null;
        $candidates = $request['candidates'] ?? null;
        $options = $request['options'] ?? [];

        if (!is_array($items) || empty($items)) {
            throw new ValidationException('items is required and must be a non-empty array.');
        }
        if (!is_array($candidates) || empty($candidates)) {
            throw new ValidationException('candidates is required and must be a non-empty array for campaign methods.');
        }
        if (!is_array($options)) {
            throw new ValidationException('options must be an array when provided.');
        }

        $users = $this->normalizeUsers($candidates);
        $normalizedItems = $this->normalizeItems($items);
        $rules = RuleSet::fromArray((array)($options['rules'] ?? []));
        $auditSecret = isset($options['auditSecret']) ? (string)$options['auditSecret'] : '';

        return match ($method) {
            'campaign.run' => $this->runCampaign($users, $normalizedItems, $rules, $auditSecret, $options),
            'campaign.batch' => $this->runBatch($users, $rules, $auditSecret, $options),
            'campaign.simulate' => $this->simulate($users, $normalizedItems, $rules, $options),
            default => throw new ValidationException("Unsupported campaign method: {$method}")
        };
    }

    public function methods(): array
    {
        return ['campaign.run', 'campaign.batch', 'campaign.simulate'];
    }

    /**
     * @param array<string, array{count: int, weight: float, group: ?string}> $items
     * @return array{winners: array<string, array<int, string>>, explain?: array<string, array<int, array>>, audit: array}
     */
    private function executeCampaign(
        array $users,
        array $items,
        RuleSet $rules,
        bool $withExplain,
        int $retryLimit,
        RandomGeneratorInterface $random,
        StateAdapterInterface $stateAdapter,
        string $auditSecret,
    ): array {
        if ($retryLimit < 1) {
            throw new ValidationException('retryLimit must be at least 1.');
        }

        $ruleEngine = new RuleEngine($rules, $stateAdapter);
        $winners = [];
        $explanations = [];

        foreach ($items as $itemId => $itemConfig) {
            $winners[$itemId] = [];
            for ($slot = 0; $slot < $itemConfig['count']; $slot++) {
                try {
                    $picked = $this->pickWinner(
                        users: $users,
                        ruleEngine: $ruleEngine,
                        itemId: $itemId,
                        group: $itemConfig['group'],
                        retryLimit: $retryLimit,
                        withExplain: $withExplain,
                        random: $random,
                    );
                } catch (DrawExhaustedException $exception) {
                    $withExplain && $explanations[$itemId][] = [
                        'status' => 'exhausted',
                        'reason' => $exception->getMessage(),
                    ];
                    break;
                }

                $winners[$itemId][] = $picked['winner'];
                $withExplain && $explanations[$itemId][] = $picked['explain'];
            }
        }

        $result = ['winners' => $winners];
        $withExplain && $result['explain'] = $explanations;
        $result['audit'] = AuditTrail::create(
            configuration: [
                'feature' => 'campaign',
                'usersCount' => count($users),
                'items' => $items,
                'rules' => $rules->toArray(),
            ],
            result: $winners,
            seedFingerprint: $random->seedFingerprint(),
            secret: $auditSecret,
        );

        return $result;
    }

    /**
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
                $itemId = is_string($itemKey)
                    ? $itemKey
                    : trim((string)($itemDefinition['item'] ?? $itemDefinition['name'] ?? ''));
                $count = (int)($itemDefinition['count'] ?? 1);
                $weight = (float)($itemDefinition['weight'] ?? 1);
                $group = isset($itemDefinition['group']) ? (string)$itemDefinition['group'] : null;
            } elseif (is_int($itemDefinition)) {
                $itemId = is_string($itemKey) ? $itemKey : '';
                $count = $itemDefinition;
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
     * @return array<int, string>
     */
    private function normalizeUsers(array $users): array
    {
        $normalized = array_values(array_unique(array_filter(
            array_map(fn ($user) => trim((string)$user), $users),
            fn ($user) => $user !== '',
        )));

        DrawValidator::assertNotEmpty($normalized, 'candidates must contain at least one user id.');
        return $normalized;
    }

    /**
     * @return array{winner: string, explain?: array}
     */
    private function pickWinner(
        array $users,
        RuleEngine $ruleEngine,
        string $itemId,
        ?string $group,
        int $retryLimit,
        bool $withExplain,
        RandomGeneratorInterface $random,
    ): array {
        $attempts = [];
        $now = time();

        for ($attempt = 1; $attempt <= $retryLimit; $attempt++) {
            $user = $users[$random->pickArrayKey($users)];
            [$allowed, $reason] = $ruleEngine->evaluate($user, $itemId, $group, $now);

            $withExplain && $attempts[] = [
                'attempt' => $attempt,
                'candidate' => $user,
                'decision' => $reason,
            ];

            if (!$allowed) {
                continue;
            }

            $ruleEngine->record($user, $itemId, $group, $now);
            return [
                'winner' => $user,
                'explain' => [
                    'winner' => $user,
                    'attempts' => $attempts,
                    'status' => 'selected',
                ],
            ];
        }

        throw new DrawExhaustedException(
            "Unable to select a valid winner for '{$itemId}' within {$retryLimit} attempts.",
        );
    }

    private function runBatch(array $users, RuleSet $defaultRules, string $auditSecret, array $options): array
    {
        $phases = $options['phases'] ?? null;
        if (!is_array($phases) || empty($phases)) {
            throw new ValidationException('options.phases is required and must be a non-empty array for campaign.batch.');
        }

        $withExplain = (bool)($options['withExplain'] ?? false);
        $retryLimit = max(1, (int)($options['retryLimit'] ?? 100));
        $stateAdapter = new MemoryStateAdapter();
        $phaseResults = [];

        foreach ($phases as $index => $phase) {
            is_array($phase) || throw new ValidationException("Phase at index {$index} must be an array.");
            $phaseName = (string)($phase['name'] ?? 'phase_'.($index + 1));
            isset($phase['items']) || throw new ValidationException("Phase '{$phaseName}' must define items.");

            $phaseItems = $this->normalizeItems((array)$phase['items']);
            $phaseRules = $phase['rules'] ?? $defaultRules;
            $phaseRules = is_array($phaseRules) ? RuleSet::fromArray($phaseRules) : $phaseRules;
            $phaseRules instanceof RuleSet || throw new ValidationException("Phase '{$phaseName}' has invalid rules.");

            $phaseResults[$phaseName] = $this->executeCampaign(
                users: $users,
                items: $phaseItems,
                rules: $phaseRules,
                withExplain: $withExplain,
                retryLimit: $retryLimit,
                random: $this->random,
                stateAdapter: $stateAdapter,
                auditSecret: $auditSecret,
            );
        }

        $raw = [
            'phases' => $phaseResults,
            'audit' => AuditTrail::create(
                configuration: [
                    'feature' => 'batch-campaign',
                    'phaseCount' => count($phases),
                ],
                result: $phaseResults,
                seedFingerprint: $this->random->seedFingerprint(),
                secret: $auditSecret,
            ),
        ];

        $entries = [];
        foreach ($phaseResults as $phaseName => $phaseResult) {
            foreach ($phaseResult['winners'] as $item => $users) {
                foreach ($users as $user) {
                    $entries[] = ResultBuilder::entry(
                        (string)$item,
                        (string)$user,
                        $user,
                        ['phase' => (string)$phaseName],
                    );
                }
            }
        }

        return ResultBuilder::response(
            'campaign.batch',
            $entries,
            $raw,
            $this->sumPhaseCounts($phases),
            ['phaseCount' => count($phases), 'withExplain' => $withExplain, 'retryLimit' => $retryLimit],
        );
    }

    private function runCampaign(array $users, array $items, RuleSet $rules, string $auditSecret, array $options): array
    {
        $withExplain = (bool)($options['withExplain'] ?? false);
        $retryLimit = max(1, (int)($options['retryLimit'] ?? 100));
        $raw = $this->executeCampaign(
            users: $users,
            items: $items,
            rules: $rules,
            withExplain: $withExplain,
            retryLimit: $retryLimit,
            random: $this->random,
            stateAdapter: new MemoryStateAdapter(),
            auditSecret: $auditSecret,
        );

        $entries = [];
        foreach ($raw['winners'] as $item => $users) {
            foreach ($users as $user) {
                $entries[] = ResultBuilder::entry((string)$item, (string)$user, $user);
            }
        }

        return ResultBuilder::response(
            'campaign.run',
            $entries,
            $raw,
            $this->sumItemCounts($items),
            ['withExplain' => $withExplain, 'retryLimit' => $retryLimit],
        );
    }

    private function simulate(array $users, array $items, RuleSet $rules, array $options): array
    {
        $iterations = max(1, (int)($options['iterations'] ?? 1000));
        $retryLimit = max(1, (int)($options['retryLimit'] ?? 100));

        $userWins = array_fill_keys($users, 0);
        $itemWins = array_fill_keys(array_keys($items), 0);
        $totalSlotsPerIteration = array_sum(array_column($items, 'count'));

        for ($i = 1; $i <= $iterations; $i++) {
            $result = $this->executeCampaign(
                users: $users,
                items: $items,
                rules: $rules,
                withExplain: false,
                retryLimit: $retryLimit,
                random: new SeededRandomGenerator($i),
                stateAdapter: new MemoryStateAdapter(),
                auditSecret: '',
            )['winners'];

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
                candidateId: (string)$user,
                value: (float)$distribution['rate'],
                meta: ['kind' => 'userDistribution', 'wins' => (int)$distribution['wins']],
            );
        }

        return ResultBuilder::response(
            'campaign.simulate',
            $entries,
            $raw,
            $iterations,
            ['iterations' => $iterations, 'retryLimit' => $retryLimit],
        );
    }

    private function sumItemCounts(array $items): int
    {
        $sum = 0;
        foreach ($items as $item) {
            if (is_int($item)) {
                $sum += $item;
                continue;
            }
            if (is_array($item)) {
                $sum += (int)($item['count'] ?? 1);
            }
        }
        return max(1, $sum);
    }

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
        return max(1, $sum);
    }
}
