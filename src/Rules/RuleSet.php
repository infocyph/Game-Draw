<?php

namespace Infocyph\Draw\Rules;

use Infocyph\Draw\Exceptions\ValidationException;

class RuleSet
{
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
            if (!is_int($cap) || $cap < 0) {
                throw new ValidationException("perItemCap for '{$item}' must be a non-negative integer.");
            }
        }

        foreach ($this->groupQuota as $group => $quota) {
            if (!is_int($quota) || $quota < 0) {
                throw new ValidationException("groupQuota for '{$group}' must be a non-negative integer.");
            }
        }
    }

    public static function fromArray(array $rules): self
    {
        return new self(
            perUserCap: (int)($rules['perUserCap'] ?? 1),
            perItemCap: (array)($rules['perItemCap'] ?? []),
            groupQuota: (array)($rules['groupQuota'] ?? []),
            cooldownSeconds: (int)($rules['cooldownSeconds'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'perUserCap' => $this->perUserCap,
            'perItemCap' => $this->perItemCap,
            'groupQuota' => $this->groupQuota,
            'cooldownSeconds' => $this->cooldownSeconds,
        ];
    }
}
