<?php

declare(strict_types=1);

namespace Infocyph\Draw\Rules;

use Infocyph\Draw\Exceptions\ValidationException;
use Psr\Cache\CacheItemPoolInterface;

class RuleEngine
{
    /**
     * Request-scoped cache of PSR-6 values already read or written by this engine.
     *
     * @var array<string, int>
     */
    private array $cachedValues = [];

    /**
     * @var array<string, string>
     */
    private array $resolvedKeys = [];

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

        if ($group !== null && isset($this->rules->groupQuota[$group])) {
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

    private function cacheKey(string $scope, string $identifier): string
    {
        $lookupKey = $scope . "\0" . $identifier;
        if (isset($this->resolvedKeys[$lookupKey])) {
            return $this->resolvedKeys[$lookupKey];
        }

        $legacyKey = "rules.{$scope}.{$identifier}";
        if (strlen($legacyKey) <= 64 && preg_match('/[{}()\\/\\\\@:]/', $legacyKey) !== 1) {
            return $this->resolvedKeys[$lookupKey] = $legacyKey;
        }

        $digest = rtrim(strtr(base64_encode(hash('sha256', $lookupKey, true)), '+/', '-_'), '=');

        return $this->resolvedKeys[$lookupKey] = "r.{$scope}.{$digest}";
    }

    private function groupWinsKey(string $group): string
    {
        return $this->cacheKey('group_wins', $group);
    }

    private function incrementKey(string $key, int $by = 1): int
    {
        $item = $this->cachePool->getItem($key);
        $current = $item->isHit() ? $this->readRawInt($item->get()) : 0;
        if ($by > PHP_INT_MAX - $current) {
            throw new ValidationException('Rule state counter exceeds the platform integer range.');
        }
        $updated = $current + $by;
        $item->set($updated);
        $this->cachePool->save($item);
        $this->cachedValues[$key] = $updated;

        return $updated;
    }

    private function itemWinsKey(string $itemId): string
    {
        return $this->cacheKey('item_wins', $itemId);
    }

    private function readInt(string $key): int
    {
        if (isset($this->cachedValues[$key])) {
            return $this->cachedValues[$key];
        }

        $item = $this->cachePool->getItem($key);
        if (!$item->isHit()) {
            $this->cachedValues[$key] = 0;

            return 0;
        }

        $value = $this->readRawInt($item->get());
        $this->cachedValues[$key] = $value;

        return $value;
    }

    private function readRawInt(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new ValidationException('Rule state values must be non-negative integers.');
        }

        return $value;
    }

    private function setKeyValue(string $key, mixed $value): void
    {
        $item = $this->cachePool->getItem($key);
        $item->set($value);
        $this->cachePool->save($item);
        $this->cachedValues[$key] = $this->readRawInt($value);
    }

    private function userLastWinKey(string $userId): string
    {
        return $this->cacheKey('user_last_win', $userId);
    }

    private function userWinsKey(string $userId): string
    {
        return $this->cacheKey('user_wins', $userId);
    }
}
