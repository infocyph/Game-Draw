<?php

declare(strict_types=1);

namespace Infocyph\Draw\Contracts;

interface RandomGeneratorInterface
{
    public function float(): float;

    public function int(int $min, int $max): int;

    /**
     * @param array<int|string, mixed> $items
     */
    public function pickArrayKey(array $items): int|string;

    public function seedFingerprint(): ?string;
}
