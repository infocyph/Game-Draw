<?php

namespace Infocyph\Draw\Audit;

final class AuditTrail
{
    public static function create(
        array $configuration,
        array $result,
        ?string $seedFingerprint = null,
        string $secret = '',
    ): array {
        $generatedAt = gmdate('c');
        $normalizedConfig = self::normalize($configuration);
        $normalizedResult = self::normalize($result);

        $configHash = hash(
            'xxh3',
            json_encode($normalizedConfig, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
        $resultHash = hash(
            'xxh3',
            json_encode($normalizedResult, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
        $payload = implode('|', [$generatedAt, $configHash, $resultHash, $seedFingerprint ?? '']);

        // xxh3 is non-cryptographic and unsupported by hash_hmac(), so use salted payload hashing.
        $signaturePayload = $secret !== '' ? "{$secret}|{$payload}" : $payload;
        $signature = hash('xxh3', $signaturePayload);

        return [
            'generatedAt' => $generatedAt,
            'configHash' => $configHash,
            'resultHash' => $resultHash,
            'seedFingerprint' => $seedFingerprint,
            'signature' => $signature,
        ];
    }

    private static function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item);
        }
        return $value;
    }
}
