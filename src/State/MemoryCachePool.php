<?php

declare(strict_types=1);

namespace Infocyph\Draw\State;

use DateTimeImmutable;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class MemoryCachePool implements CacheItemPoolInterface
{
    /**
     * @var array<string, MemoryCacheItem>
     */
    private array $deferred = [];

    /**
     * @var array<string, array{value: mixed, expiresAt: ?DateTimeImmutable}>
     */
    private array $store = [];

    public function clear(): bool
    {
        $this->store = [];
        $this->deferred = [];

        return true;
    }

    public function commit(): bool
    {
        foreach ($this->deferred as $item) {
            $this->save($item);
        }

        $this->deferred = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->assertValidKey($key);
        unset($this->store[$key], $this->deferred[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function getItem(string $key): CacheItemInterface
    {
        $this->assertValidKey($key);

        $stored = $this->store[$key] ?? null;
        if ($stored === null) {
            return new MemoryCacheItem($key);
        }

        if ($this->isExpired($stored['expiresAt'])) {
            unset($this->store[$key]);

            return new MemoryCacheItem($key);
        }

        return new MemoryCacheItem($key, $stored['value'], true, $stored['expiresAt']);
    }

    /**
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        /** @var array<string, CacheItemInterface> $items */
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    public function save(CacheItemInterface $item): bool
    {
        $key = $item->getKey();
        $this->assertValidKey($key);

        $expiresAt = null;
        if ($item instanceof MemoryCacheItem) {
            $expiresAt = $item->expiration();
        }

        $this->store[$key] = [
            'value' => $item->get(),
            'expiresAt' => $expiresAt,
        ];
        unset($this->deferred[$key]);

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $key = $item->getKey();
        $this->assertValidKey($key);

        $expiresAt = null;
        if ($item instanceof MemoryCacheItem) {
            $expiresAt = $item->expiration();
        }

        $this->deferred[$key] = new MemoryCacheItem($key, $item->get(), $item->isHit(), $expiresAt);

        return true;
    }

    private function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidCacheKeyException('Cache key cannot be empty.');
        }

        if (preg_match('/[{}()\\/\\@:]/', $key) === 1) {
            throw new InvalidCacheKeyException('Cache key contains reserved characters.');
        }
    }

    private function isExpired(?DateTimeImmutable $expiresAt): bool
    {
        if ($expiresAt === null) {
            return false;
        }

        return $expiresAt <= new DateTimeImmutable();
    }
}
