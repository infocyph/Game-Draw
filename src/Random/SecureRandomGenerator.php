<?php

declare(strict_types=1);

namespace Infocyph\Draw\Random;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Random\Engine\Secure;
use Random\Randomizer;

class SecureRandomGenerator implements RandomGeneratorInterface
{
    private readonly Randomizer $randomizer;

    public function __construct()
    {
        $this->randomizer = new Randomizer(new Secure());
    }

    public function float(): float
    {
        return $this->randomizer->getInt(0, PHP_INT_MAX) / PHP_INT_MAX;
    }

    public function int(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    /**
     * @param array<int|string, mixed> $items
     */
    public function pickArrayKey(array $items): int|string
    {
        if ($items === []) {
            throw new ValidationException('Cannot pick from an empty array.');
        }

        $keys = $this->randomizer->pickArrayKeys($items, 1);
        $pickedKey = reset($keys);
        if (!is_int($pickedKey) && !is_string($pickedKey)) {
            throw new ValidationException('Random array key must be an integer or string.');
        }

        return $pickedKey;
    }

    public function seedFingerprint(): ?string
    {
        return null;
    }
}
