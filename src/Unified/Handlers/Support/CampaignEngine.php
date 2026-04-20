<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers\Support;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Flexible\Support\WeightTools;
use Infocyph\Draw\Rules\RuleEngine;
use Infocyph\Draw\Rules\RuleSet;
use Psr\Cache\CacheItemPoolInterface;

final class CampaignEngine
{
    private readonly RuleEngine $ruleEngine;

    /**
     * @var (callable(string, string, ?string, array{slot: int, timestamp: int}): bool)|null
     */
    private $eligibility;

    public function __construct(
        private readonly RuleSet $rules,
        private readonly CacheItemPoolInterface $cachePool,
        private readonly RandomGeneratorInterface $random,
        ?callable $eligibility,
        private readonly bool $withExplain,
    ) {
        $this->eligibility = $eligibility;
        $this->ruleEngine = new RuleEngine($this->rules, $this->cachePool);
    }

    /**
     * @param list<string> $users
     * @param array<string, array{count: int, weight: float, group: ?string}> $items
     * @return array{
     *   winners: array<string, list<string>>,
     *   explain?: array<string, list<array<string, mixed>>>,
     *   partialReason: ?string,
     *   slotPlan: list<array{itemId: string, group: ?string}>
     * }
     */
    public function run(array $users, array $items): array
    {
        $winners = [];
        $explanations = [];
        foreach ($items as $itemId => $_item) {
            $winners[$itemId] = [];
            if ($this->withExplain) {
                $explanations[$itemId] = [];
            }
        }

        $partialReasons = [];
        $slotPlan = $this->buildSlotPlan($items);
        foreach ($slotPlan as $slotIndex => $slot) {
            try {
                $picked = $this->pickWinnerFromPool(
                    users: $users,
                    itemId: $slot['itemId'],
                    group: $slot['group'],
                    slot: $slotIndex + 1,
                );
            } catch (DrawExhaustedException $exception) {
                $partialReasons[] = 'no_eligible_candidates';
                if ($this->withExplain) {
                    $explanations[$slot['itemId']][] = [
                        'status' => 'exhausted',
                        'reason' => $exception->getMessage(),
                        'slot' => $slotIndex + 1,
                    ];
                }

                continue;
            }

            $winners[$slot['itemId']][] = $picked['winner'];
            if ($this->withExplain) {
                $explanations[$slot['itemId']][] = $picked['explain'];
            }
        }

        $result = [
            'winners' => $winners,
            'partialReason' => $partialReasons === [] ? null : $this->summarizeReasons($partialReasons),
            'slotPlan' => $slotPlan,
        ];
        if ($this->withExplain) {
            $result['explain'] = $explanations;
        }

        return $result;
    }

    /**
     * @param array<string, array{count: int, weight: float, group: ?string}> $items
     * @return list<array{itemId: string, group: ?string}>
     */
    private function buildSlotPlan(array $items): array
    {
        $remaining = [];
        foreach ($items as $itemId => $item) {
            if ($item['count'] > 0) {
                $remaining[$itemId] = $item['count'];
            }
        }

        $plan = [];
        while ($remaining !== []) {
            $weightedItems = $this->buildWeightedRemaining($remaining, $items);
            $pickedItemId = $this->pickWeightedItemKey($weightedItems);
            $plan[] = ['itemId' => $pickedItemId, 'group' => $items[$pickedItemId]['group']];

            $remaining[$pickedItemId]--;
            if ($remaining[$pickedItemId] <= 0) {
                unset($remaining[$pickedItemId]);
            }
        }

        return $plan;
    }

    /**
     * @param array<string, int> $remaining
     * @param array<string, array{count: int, weight: float, group: ?string}> $items
     * @return array<string, float>
     */
    private function buildWeightedRemaining(array $remaining, array $items): array
    {
        $weightedItems = [];
        foreach ($remaining as $itemId => $count) {
            $weight = $items[$itemId]['weight'];
            if ($weight > 0) {
                $weightedItems[$itemId] = $weight * $count;
            }
        }

        if ($weightedItems !== []) {
            return $weightedItems;
        }

        foreach ($remaining as $itemId => $count) {
            $weightedItems[$itemId] = (float) $count;
        }

        return $weightedItems;
    }

    /**
     * @param list<string> $users
     * @return array{
     *   eligible: list<string>,
     *   rejected: array<string, int>,
     *   attempts: list<array{candidate: string, decision: string}>
     * }
     */
    private function collectEligibleUsers(
        array $users,
        string $itemId,
        ?string $group,
        int $slot,
        int $now,
    ): array {
        $eligibleUsers = [];
        $rejectedSummary = [];
        $attempts = [];

        foreach ($users as $user) {
            $decision = $this->rejectionReason($user, $itemId, $group, $slot, $now);
            if ($decision !== null) {
                $rejectedSummary[$decision] = ($rejectedSummary[$decision] ?? 0) + 1;
                if ($this->withExplain) {
                    $attempts[] = ['candidate' => $user, 'decision' => $decision];
                }

                continue;
            }

            $eligibleUsers[] = $user;
        }

        return [
            'eligible' => $eligibleUsers,
            'rejected' => $rejectedSummary,
            'attempts' => $attempts,
        ];
    }

    /**
     * @param array<string, float> $weights
     */
    private function pickWeightedItemKey(array $weights): string
    {
        $weightList = [];
        $indexToKey = [];
        foreach ($weights as $key => $weight) {
            $indexToKey[] = $key;
            $weightList[] = ['weight' => $weight];
        }

        [$prepared, $totalWeight] = WeightTools::prepare($weightList);
        if ($totalWeight <= 0) {
            throw new DrawExhaustedException('Unable to build weighted item slot plan because all weights are zero.');
        }

        $draw = $this->random->int(1, $totalWeight);
        foreach ($prepared as $weight) {
            $draw -= $weight['weight'];
            if ($draw <= 0) {
                return $indexToKey[$weight['index']];
            }
        }

        $lastIndex = array_key_last($indexToKey);
        if (!is_int($lastIndex)) {
            throw new DrawExhaustedException('Unable to resolve weighted item key fallback.');
        }

        return $indexToKey[$lastIndex];
    }

    /**
     * @param list<string> $users
     * @return array{winner: string, explain: array<string, mixed>}
     */
    private function pickWinnerFromPool(
        array $users,
        string $itemId,
        ?string $group,
        int $slot,
    ): array {
        $now = time();
        ['eligible' => $eligibleUsers, 'rejected' => $rejectedSummary, 'attempts' => $attempts] = $this->collectEligibleUsers(
            users: $users,
            itemId: $itemId,
            group: $group,
            slot: $slot,
            now: $now,
        );

        if ($eligibleUsers === []) {
            $reasonSummary = [];
            foreach ($rejectedSummary as $reason => $count) {
                $reasonSummary[] = "{$reason}:{$count}";
            }
            $suffix = $reasonSummary === [] ? '' : ' Reasons=' . implode(',', $reasonSummary);

            throw new DrawExhaustedException("No eligible candidate remained for '{$itemId}'.{$suffix}");
        }

        $winner = $eligibleUsers[$this->random->int(0, count($eligibleUsers) - 1)];
        $this->ruleEngine->record($winner, $itemId, $group, $now);

        return [
            'winner' => $winner,
            'explain' => [
                'winner' => $winner,
                'slot' => $slot,
                'eligiblePoolSize' => count($eligibleUsers),
                'rejectedSummary' => $rejectedSummary,
                'attempts' => $attempts,
                'status' => 'selected',
            ],
        ];
    }

    private function rejectionReason(
        string $user,
        string $itemId,
        ?string $group,
        int $slot,
        int $now,
    ): ?string {
        if ($this->eligibility !== null && !(bool) ($this->eligibility)($user, $itemId, $group, [
            'slot' => $slot,
            'timestamp' => $now,
        ])) {
            return 'eligibility_rejected';
        }

        [$allowed, $reason] = $this->ruleEngine->evaluate($user, $itemId, $group, $now);

        return $allowed ? null : $reason;
    }

    /**
     * @param list<string> $reasons
     */
    private function summarizeReasons(array $reasons): string
    {
        $counts = array_count_values($reasons);
        arsort($counts);
        $reason = array_key_first($counts);

        return is_string($reason) ? $reason : 'unfulfilled';
    }
}
