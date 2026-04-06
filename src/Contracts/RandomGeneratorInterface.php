<?php

namespace Infocyph\Draw\Contracts;

interface RandomGeneratorInterface
{
    public function float(): float;
    public function int(int $min, int $max): int;

    /**
     * @param array $items
     * @return int|string
     */
    public function pickArrayKey(array $items): int|string;

    public function seedFingerprint(): ?string;
}
