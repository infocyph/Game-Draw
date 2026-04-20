<?php

declare(strict_types=1);

namespace Infocyph\Draw\Rules;

use Infocyph\Draw\Contracts\StateAdapterInterface;

class RuleEngine
{
    public function __construct(
        private readonly RuleSet $rules,
        private readonly StateAdapterInterface $stateAdapter,
    ) {}

    /**
     * @return array{0: bool, 1: string}
     */
    public function evaluate(string $userId, string $itemId, ?string $group, int $timestamp): array
    {
        $userWins = $this->readInt($this->userWinsKey($userId));
        if ($userWins >= $this->rules->perUserCap) {
            return [false, 'per_user_cap_reached'];
        }

        $itemCap = $this->rules->perItemCap[$itemId] ?? null;
        if ($itemCap !== null) {
            $itemWins = $this->readInt($this->itemWinsKey($itemId));
            if ($itemWins >= $itemCap) {
                return [false, 'per_item_cap_reached'];
            }
        }

        if ($group !== null && array_key_exists($group, $this->rules->groupQuota)) {
            $groupWins = $this->readInt($this->groupWinsKey($group));
            if ($groupWins >= $this->rules->groupQuota[$group]) {
                return [false, 'group_quota_reached'];
            }
        }

        if ($this->rules->cooldownSeconds > 0) {
            $lastWin = $this->readInt($this->userLastWinKey($userId));
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

    private function readInt(string $key): int
    {
        $value = $this->stateAdapter->get($key, 0);

        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => 0,
        };
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
