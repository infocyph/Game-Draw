<?php

declare(strict_types=1);

namespace Infocyph\Draw\Random;

use Random\Engine\Secure;

class SecureRandomGenerator extends AbstractRandomGenerator
{
    public function __construct()
    {
        parent::__construct(new Secure());
    }

    public function seedFingerprint(): ?string
    {
        return null;
    }
}
