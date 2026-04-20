<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers\Support;

use Infocyph\Draw\Unified\Support\ResultBuilder;

final class CampaignBatchFormatter
{
    /**
     * @param array<string, mixed> $phaseResults
     * @return list<array{itemId: string|null, candidateId: string|null, value: mixed, meta: array<string, mixed>}>
     */
    public static function buildEntries(array $phaseResults): array
    {
        $entries = [];

        foreach ($phaseResults as $phaseName => $phaseResult) {
            if (!is_array($phaseResult)) {
                continue;
            }

            self::appendPhaseEntries($entries, $phaseName, self::normalizeAssoc($phaseResult));
        }

        return $entries;
    }

    /**
     * @param list<array{itemId: string|null, candidateId: string|null, value: mixed, meta: array<string, mixed>}> $entries
     * @param array<string, mixed> $phaseResult
     */
    private static function appendPhaseEntries(array &$entries, string $phaseName, array $phaseResult): void
    {
        $winnersByItem = $phaseResult['winners'] ?? null;
        if (!is_array($winnersByItem)) {
            return;
        }

        foreach ($winnersByItem as $itemId => $winners) {
            if (!is_string($itemId) || !is_array($winners)) {
                continue;
            }

            foreach ($winners as $winner) {
                if (!is_string($winner)) {
                    continue;
                }

                $entries[] = ResultBuilder::entry($itemId, $winner, $winner, ['phase' => $phaseName]);
            }
        }
    }

    /**
     * @param array<int|string, mixed> $value
     * @return array<string, mixed>
     */
    private static function normalizeAssoc(array $value): array
    {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }
}
