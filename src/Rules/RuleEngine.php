<?php

declare(strict_types=1);

namespace Infocyph\Draw\Rules;

use Infocyph\Draw\Support\ScalarValue;
use Psr\Cache\CacheItemPoolInterface;

class RuleEngine
{
    public function __construct(
        private readonly RuleSet $rules,
        private readonly CacheItemPoolInterface $cachePool,
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
        $this->incrementKey($this->userWinsKey($userId));
        $this->incrementKey($this->itemWinsKey($itemId));
        if ($group !== null) {
            $this->incrementKey($this->groupWinsKey($group));
        }
        $this->setKeyValue($this->userLastWinKey($userId), $timestamp);
    }

    private function groupWinsKey(string $group): string
    {
        return "rules.group_wins.{$group}";
    }

    private function incrementKey(string $key, int $by = 1): int
    {
        $item = $this->cachePool->getItem($key);
        $current = $item->isHit() ? $this->readRawInt($item->get()) : 0;
        $updated = $current + $by;
        $item->set($updated);
        $this->cachePool->save($item);

        return $updated;
    }

    private function itemWinsKey(string $itemId): string
    {
        return "rules.item_wins.{$itemId}";
    }

    private function readInt(string $key): int
    {
        $item = $this->cachePool->getItem($key);
        if (!$item->isHit()) {
            return 0;
        }

        return ScalarValue::toInt($item->get(), 0);
    }

    private function readRawInt(mixed $value): int
    {
        return ScalarValue::toInt($value, 0);
    }

    private function setKeyValue(string $key, mixed $value): void
    {
        $item = $this->cachePool->getItem($key);
        $item->set($value);
        $this->cachePool->save($item);
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
