<?php

declare(strict_types=1);

namespace Infocyph\Draw\Random;

use Random\Engine\Mt19937;

class SeededRandomGenerator extends AbstractRandomGenerator
{
    public function __construct(private readonly int $seed)
    {
        parent::__construct(new Mt19937($this->seed));
    }

    public function seedFingerprint(): ?string
    {
        return hash('xxh3', 'seed:' . $this->seed);
    }
}
