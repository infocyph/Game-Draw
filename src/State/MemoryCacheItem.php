<?php

declare(strict_types=1);

namespace Infocyph\Draw\State;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

final class MemoryCacheItem implements CacheItemInterface
{
    public function __construct(private readonly string $key, private mixed $value = null, private bool $hit = false, private ?DateTimeImmutable $expiresAt = null) {}

    public function expiration(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;

            return $this;
        }

        $now = new DateTimeImmutable();
        if (is_int($time)) {
            $this->expiresAt = $now->modify("+{$time} seconds");

            return $this;
        }

        $this->expiresAt = $now->add($time);

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expiresAt = $expiration === null
            ? null
            : DateTimeImmutable::createFromInterface($expiration);

        return $this;
    }

    public function get(): mixed
    {
        return $this->hit ? $this->value : null;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }
}
