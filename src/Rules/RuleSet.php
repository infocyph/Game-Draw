<?php

declare(strict_types=1);

namespace Infocyph\Draw\Rules;

use Infocyph\Draw\Exceptions\ValidationException;

class RuleSet
{
    /**
     * @param array<string, int> $perItemCap
     * @param array<string, int> $groupQuota
     */
    public function __construct(
        public readonly int $perUserCap = 1,
        public readonly array $perItemCap = [],
        public readonly array $groupQuota = [],
        public readonly int $cooldownSeconds = 0,
    ) {
        if ($this->perUserCap < 1) {
            throw new ValidationException('perUserCap must be at least 1.');
        }

        if ($this->cooldownSeconds < 0) {
            throw new ValidationException('cooldownSeconds must be greater than or equal to zero.');
        }

        foreach ($this->perItemCap as $item => $cap) {
            if ($cap < 0) {
                throw new ValidationException("perItemCap for '{$item}' must be a non-negative integer.");
            }
        }

        foreach ($this->groupQuota as $group => $quota) {
            if ($quota < 0) {
                throw new ValidationException("groupQuota for '{$group}' must be a non-negative integer.");
            }
        }
    }

    /**
     * @param array<string, mixed> $rules
     */
    public static function fromArray(array $rules): self
    {
        $perUserCap = self::toIntOrDefault($rules['perUserCap'] ?? null, 1);
        $cooldownSeconds = self::toIntOrDefault($rules['cooldownSeconds'] ?? null, 0);
        $perItemCap = self::toIntMap($rules['perItemCap'] ?? []);
        $groupQuota = self::toIntMap($rules['groupQuota'] ?? []);

        return new self(
            perUserCap: $perUserCap,
            perItemCap: $perItemCap,
            groupQuota: $groupQuota,
            cooldownSeconds: $cooldownSeconds,
        );
    }

    /**
     * @return array<string, int|array<string, int>>
     */
    public function toArray(): array
    {
        return [
            'perUserCap' => $this->perUserCap,
            'perItemCap' => $this->perItemCap,
            'groupQuota' => $this->groupQuota,
            'cooldownSeconds' => $this->cooldownSeconds,
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function toIntMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $keyAsString = is_string($key) ? $key : (string) $key;
            $result[$keyAsString] = self::toIntOrDefault($item, 0);
        }

        return $result;
    }

    private static function toIntOrDefault(mixed $value, int $default): int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => $default,
        };
    }
}
