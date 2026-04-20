Randomness Modes
================

Random Interface
----------------

All randomness is abstracted by `RandomGeneratorInterface`:

- `float(): float`
- `int(int $min, int $max): int`
- `pickArrayKey(array $items): int|string`
- `seedFingerprint(): ?string`

Built-in Generators
-------------------

`SecureRandomGenerator`
   Cryptographically secure random source. Used by default in `Draw`.

`SeededRandomGenerator`
   Deterministic random source for reproducible tests/simulations.

Usage
-----

Default secure mode:

.. code-block:: php

   <?php
   $draw = new \Infocyph\Draw\Draw();

Deterministic global mode:

.. code-block:: php

   <?php
   use Infocyph\Draw\Draw;
   use Infocyph\Draw\Random\SeededRandomGenerator;

   $draw = new Draw(new SeededRandomGenerator(12345));

Campaign deterministic override:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'campaign.run',
       'items' => [...],
       'candidates' => [...],
       'options' => ['seed' => 12345],
   ]);

Seed Fingerprints
-----------------

- `SecureRandomGenerator::seedFingerprint()` returns `null`.
- `SeededRandomGenerator::seedFingerprint()` returns a deterministic fingerprint.

This fingerprint is included in campaign audit payloads when available.
