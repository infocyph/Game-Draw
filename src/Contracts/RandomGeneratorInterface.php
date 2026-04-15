<?php

namespace Infocyph\Draw\Contracts;

interface RandomGeneratorInterface
{
    public function float(): float;
    public function int(int $min, int $max): int;

    public function pickArrayKey(array $items): int|string;

    public function seedFingerprint(): ?string;
}
