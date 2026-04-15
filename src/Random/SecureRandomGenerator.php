<?php

namespace Infocyph\Draw\Random;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
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

    public function pickArrayKey(array $items): int|string
    {
        $keys = $this->randomizer->pickArrayKeys($items, 1);
        return array_values($keys)[0];
    }

    public function seedFingerprint(): ?string
    {
        return null;
    }
}
