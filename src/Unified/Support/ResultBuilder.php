<?php

namespace Infocyph\Draw\Unified\Support;

final class ResultBuilder
{
    public static function entry(?string $itemId, ?string $candidateId, mixed $value, array $meta = []): array
    {
        return [
            'itemId' => $itemId,
            'candidateId' => $candidateId,
            'value' => $value,
            'meta' => $meta,
        ];
    }

    public static function response(
        string $method,
        array $entries,
        mixed $raw,
        int $requestedCount = 1,
        array $meta = [],
    ): array {
        $returnedCount = count($entries);
        return [
            'method' => $method,
            'entries' => array_values($entries),
            'raw' => $raw,
            'meta' => array_merge([
                'mode' => $requestedCount === 1 ? 'single' : 'multi',
                'requestedCount' => $requestedCount,
                'returnedCount' => $returnedCount,
            ], $meta),
        ];
    }
}
