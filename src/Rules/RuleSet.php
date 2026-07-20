<?php

declare(strict_types=1);

namespace Infocyph\Draw\Rules;

use Infocyph\Draw\Exceptions\ValidationException;

class RuleSet
{
    public readonly int $cooldownSeconds;

    /**
     * @var array<int|string, int>
     */
    public readonly array $groupQuota;

    /**
     * @var array<int|string, int>
     */
    public readonly array $perItemCap;

    public readonly int $perUserCap;

    /**
     * @param array<int|string, mixed> $perItemCap
     * @param array<int|string, mixed> $groupQuota
     */
    public function __construct(
        int $perUserCap = 1,
        array $perItemCap = [],
        array $groupQuota = [],
        int $cooldownSeconds = 0,
    ) {
        if ($perUserCap < 1) {
            throw new ValidationException('perUserCap must be at least 1.');
        }

        if ($cooldownSeconds < 0) {
            throw new ValidationException('cooldownSeconds must be greater than or equal to zero.');
        }

        foreach ($perItemCap as $item => $cap) {
            if ((is_string($item) && $item === '') || !is_int($cap) || $cap < 0) {
                throw new ValidationException("perItemCap for '{$item}' must be a non-negative integer.");
            }
        }

        foreach ($groupQuota as $group => $quota) {
            if ((is_string($group) && $group === '') || !is_int($quota) || $quota < 0) {
                throw new ValidationException("groupQuota for '{$group}' must be a non-negative integer.");
            }
        }

        $this->perUserCap = $perUserCap;
        $this->perItemCap = $perItemCap;
        $this->groupQuota = $groupQuota;
        $this->cooldownSeconds = $cooldownSeconds;
    }

    /**
     * @param array<string, mixed> $rules
     */
    public static function fromArray(array $rules): self
    {
        $perUserCap = self::optionalInt($rules, 'perUserCap', 1);
        $cooldownSeconds = self::optionalInt($rules, 'cooldownSeconds', 0);
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
     * @return array<string, int|array<int|string, int>>
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
     * @param array<string, mixed> $rules
     */
    private static function optionalInt(array $rules, string $key, int $default): int
    {
        $value = $rules[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        if (!is_int($value)) {
            throw new ValidationException("{$key} must be an integer.");
        }

        return $value;
    }

    /**
     * @return array<int|string, int>
     */
    private static function toIntMap(mixed $value): array
    {
        if (!is_array($value)) {
            throw new ValidationException('Rule caps and quotas must be arrays.');
        }

        $result = [];
        foreach ($value as $key => $item) {
            $keyAsString = is_string($key) ? $key : (string) $key;
            if ($keyAsString === '' || !is_int($item)) {
                throw new ValidationException('Rule caps and quotas require non-empty keys and integer values.');
            }
            $result[$keyAsString] = $item;
        }

        return $result;
    }
}
