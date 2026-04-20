<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Support;

final class ResultBuilder
{
    /**
     * @param array<string, mixed> $meta
     * @return array{itemId: ?string, candidateId: ?string, value: mixed, meta: array<string, mixed>}
     */
    public static function entry(?string $itemId, ?string $candidateId, mixed $value, array $meta = []): array
    {
        return [
            'itemId' => $itemId,
            'candidateId' => $candidateId,
            'value' => $value,
            'meta' => $meta,
        ];
    }

    /**
     * @param array<int, array{itemId: ?string, candidateId: ?string, value: mixed, meta: array<string, mixed>}> $entries
     * @param array<string, mixed> $meta
     * @return array{
     *   method: string,
     *   entries: array<int, array{itemId: ?string, candidateId: ?string, value: mixed, meta: array<string, mixed>}>,
     *   raw: mixed,
     *   meta: array<string, mixed>
     * }
     */
    public static function response(
        string $method,
        array $entries,
        mixed $raw,
        int $requestedCount = 1,
        array $meta = [],
    ): array {
        $returnedCount = count($entries);
        $unfilledCount = max(0, $requestedCount - $returnedCount);
        $fulfilled = $unfilledCount === 0;
        $partialReason = $fulfilled ? null : ($meta['partialReason'] ?? 'unfulfilled');
        $responseMeta = array_merge([
            'mode' => $requestedCount === 1 ? 'single' : 'multi',
            'requestedCount' => $requestedCount,
            'returnedCount' => $returnedCount,
            'fulfilled' => $fulfilled,
            'partialReason' => $partialReason,
            'unfilledCount' => $unfilledCount,
        ], $meta);
        if ($responseMeta['fulfilled'] === false) {
            $reason = $responseMeta['partialReason'] ?? null;
            if (!is_string($reason) || $reason === '') {
                $responseMeta['partialReason'] = 'unfulfilled';
            }
        }

        return [
            'method' => $method,
            'entries' => array_values($entries),
            'raw' => $raw,
            'meta' => $responseMeta,
        ];
    }
}
