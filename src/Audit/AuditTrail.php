<?php

declare(strict_types=1);

namespace Infocyph\Draw\Audit;

final class AuditTrail
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function create(
        array $configuration,
        array $result,
        ?string $seedFingerprint = null,
        string $secret = '',
    ): array {
        $generatedAt = gmdate('c');
        [$configHash, $resultHash, $payload, $signature] = self::computeArtifacts(
            generatedAt: $generatedAt,
            configuration: $configuration,
            result: $result,
            seedFingerprint: $seedFingerprint,
            secret: $secret,
        );

        return [
            'generatedAt' => $generatedAt,
            'configHash' => $configHash,
            'resultHash' => $resultHash,
            'seedFingerprint' => $seedFingerprint,
            'signatureAlgorithm' => $secret !== '' ? 'hmac-sha256' : 'sha256',
            'signaturePayload' => $payload,
            'signature' => $signature,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fingerprint(array $payload): string
    {
        return hash(
            'xxh3',
            json_encode(self::normalize($payload), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, mixed> $audit
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $result
     */
    public static function verify(
        array $audit,
        array $configuration,
        array $result,
        ?string $seedFingerprint = null,
        string $secret = '',
    ): bool {
        $generatedAt = $audit['generatedAt'] ?? null;
        $signature = $audit['signature'] ?? null;
        $configHash = $audit['configHash'] ?? null;
        $resultHash = $audit['resultHash'] ?? null;
        if (!is_string($generatedAt) || !is_string($signature) || !is_string($configHash) || !is_string($resultHash)) {
            return false;
        }

        [$expectedConfigHash, $expectedResultHash, $payload, $expectedSignature] = self::computeArtifacts(
            generatedAt: $generatedAt,
            configuration: $configuration,
            result: $result,
            seedFingerprint: $seedFingerprint,
            secret: $secret,
        );

        $signaturePayload = $audit['signaturePayload'] ?? null;
        if ($signaturePayload !== null && !is_string($signaturePayload)) {
            return false;
        }

        return hash_equals($configHash, $expectedConfigHash)
            && hash_equals($resultHash, $expectedResultHash)
            && hash_equals($signature, $expectedSignature)
            && ($signaturePayload === null || hash_equals($signaturePayload, $payload));
    }

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $result
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private static function computeArtifacts(
        string $generatedAt,
        array $configuration,
        array $result,
        ?string $seedFingerprint,
        string $secret,
    ): array {
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
        $signature = $secret !== ''
            ? hash_hmac('sha256', $payload, $secret)
            : hash('sha256', $payload);

        return [$configHash, $resultHash, $payload, $signature];
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
