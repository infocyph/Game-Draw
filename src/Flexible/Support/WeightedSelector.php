<?php

declare(strict_types=1);

namespace Infocyph\Draw\Flexible\Support;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\DrawExhaustedException;
use Infocyph\Draw\Exceptions\ValidationException;

final class WeightedSelector
{
    /**
     * @param list<array{weight: float|int|string}> $weightsInput
     */
    public static function pickIndex(
        RandomGeneratorInterface $random,
        array $weightsInput,
        string $exhaustedMessage = 'Draw failed unexpectedly.',
    ): int {
        [$weights, $totalWeight] = WeightTools::prepare($weightsInput);

        return self::pickPrepared($random, $weights, $totalWeight, $exhaustedMessage);
    }

    /**
     * @param array<int, array{index: int, weight: int}> $weights
     */
    public static function pickPrepared(
        RandomGeneratorInterface $random,
        array $weights,
        int $totalWeight,
        string $exhaustedMessage = 'Draw failed unexpectedly.',
    ): int {
        if ($totalWeight <= 0) {
            throw new ValidationException('Total weight must be greater than zero.');
        }

        $randomWeight = $random->int(1, $totalWeight);
        foreach ($weights as $weight) {
            $randomWeight -= $weight['weight'];
            if ($randomWeight <= 0) {
                return $weight['index'];
            }
        }

        throw new DrawExhaustedException($exhaustedMessage);
    }
}
