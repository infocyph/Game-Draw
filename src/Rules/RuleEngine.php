<?php

namespace Infocyph\Draw\Rules;

use Infocyph\Draw\Contracts\StateAdapterInterface;

class RuleEngine
{
    public function __construct(
        private readonly RuleSet $rules,
        private readonly StateAdapterInterface $stateAdapter,
    ) {
    }

    /**
     * @return array{0: bool, 1: string}
     */
    public function evaluate(string $userId, string $itemId, ?string $group, int $timestamp): array
    {
        $userWins = (int)$this->stateAdapter->get($this->userWinsKey($userId), 0);
        if ($userWins >= $this->rules->perUserCap) {
            return [false, 'per_user_cap_reached'];
        }

        $itemCap = $this->rules->perItemCap[$itemId] ?? null;
        if ($itemCap !== null) {
            $itemWins = (int)$this->stateAdapter->get($this->itemWinsKey($itemId), 0);
            if ($itemWins >= $itemCap) {
                return [false, 'per_item_cap_reached'];
            }
        }

        if ($group !== null && array_key_exists($group, $this->rules->groupQuota)) {
            $groupWins = (int)$this->stateAdapter->get($this->groupWinsKey($group), 0);
            if ($groupWins >= $this->rules->groupQuota[$group]) {
                return [false, 'group_quota_reached'];
            }
        }

        if ($this->rules->cooldownSeconds > 0) {
            $lastWin = (int)$this->stateAdapter->get($this->userLastWinKey($userId), 0);
            if ($lastWin > 0 && ($timestamp - $lastWin) < $this->rules->cooldownSeconds) {
                return [false, 'cooldown_active'];
            }
        }

        return [true, 'allowed'];
    }

    public function record(string $userId, string $itemId, ?string $group, int $timestamp): void
    {
        $this->stateAdapter->increment($this->userWinsKey($userId));
        $this->stateAdapter->increment($this->itemWinsKey($itemId));
        $group !== null && $this->stateAdapter->increment($this->groupWinsKey($group));
        $this->stateAdapter->set($this->userLastWinKey($userId), $timestamp);
    }

    private function groupWinsKey(string $group): string
    {
        return "rules.group_wins.{$group}";
    }

    private function itemWinsKey(string $itemId): string
    {
        return "rules.item_wins.{$itemId}";
    }

    private function userLastWinKey(string $userId): string
    {
        return "rules.user_last_win.{$userId}";
    }

    private function userWinsKey(string $userId): string
    {
        return "rules.user_wins.{$userId}";
    }
}
