<?php

declare(strict_types=1);

namespace Infocyph\Draw\Audit;

final class AuditTrail
{
    private const VERSION = 2;

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
            'version' => self::VERSION,
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
            'sha256',
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

        $version = $audit['version'] ?? null;
        if ($version === null) {
            return self::verifyLegacy(
                audit: $audit,
                generatedAt: $generatedAt,
                configuration: $configuration,
                result: $result,
                seedFingerprint: $seedFingerprint,
                secret: $secret,
            );
        }
        if ($version !== self::VERSION) {
            return false;
        }

        $expectedAlgorithm = $secret !== '' ? 'hmac-sha256' : 'sha256';
        if (($audit['signatureAlgorithm'] ?? null) !== $expectedAlgorithm) {
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
        if (!is_string($signaturePayload)) {
            return false;
        }

        return hash_equals($configHash, $expectedConfigHash)
            && hash_equals($resultHash, $expectedResultHash)
            && hash_equals($signature, $expectedSignature)
            && hash_equals($signaturePayload, $payload);
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

        $configHash = hash('sha256', self::encode($normalizedConfig));
        $resultHash = hash('sha256', self::encode($normalizedResult));
        $payload = self::encode([
            'domain' => 'infocyph.game-draw.audit',
            'version' => self::VERSION,
            'generatedAt' => $generatedAt,
            'configHash' => $configHash,
            'resultHash' => $resultHash,
            'seedFingerprint' => $seedFingerprint,
        ]);
        $signature = $secret !== ''
            ? hash_hmac('sha256', $payload, $secret)
            : hash('sha256', $payload);

        return [$configHash, $resultHash, $payload, $signature];
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function normalize(mixed $value, int $sortFlags = SORT_STRING): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            $normalized = [];
            foreach ($value as $item) {
                $normalized[] = self::normalize($item, $sortFlags);
            }

            return $normalized;
        }

        ksort($value, $sortFlags);
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item, $sortFlags);
        }

        return $value;
    }

    /**
     * Verifies artifacts created before the versioned SHA-256 format was introduced.
     *
     * @param array<string, mixed> $audit
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $result
     */
    private static function verifyLegacy(
        array $audit,
        string $generatedAt,
        array $configuration,
        array $result,
        ?string $seedFingerprint,
        string $secret,
    ): bool {
        $expectedAlgorithm = $secret !== '' ? 'hmac-sha256' : 'sha256';
        if (($audit['signatureAlgorithm'] ?? null) !== $expectedAlgorithm) {
            return false;
        }

        $configHash = hash('xxh3', self::encode(self::normalize($configuration, SORT_REGULAR)));
        $resultHash = hash('xxh3', self::encode(self::normalize($result, SORT_REGULAR)));
        $payload = implode('|', [$generatedAt, $configHash, $resultHash, $seedFingerprint ?? '']);
        $signature = $secret !== ''
            ? hash_hmac('sha256', $payload, $secret)
            : hash('sha256', $payload);
        $signaturePayload = $audit['signaturePayload'] ?? null;

        return is_string($audit['configHash'] ?? null)
            && is_string($audit['resultHash'] ?? null)
            && is_string($audit['signature'] ?? null)
            && hash_equals($configHash, $audit['configHash'])
            && hash_equals($resultHash, $audit['resultHash'])
            && hash_equals($signature, $audit['signature'])
            && ($signaturePayload === null || (is_string($signaturePayload) && hash_equals($payload, $signaturePayload)));
    }
}
