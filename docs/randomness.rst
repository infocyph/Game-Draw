Randomness Modes
================

Random Generator Interface
--------------------------

All randomness is abstracted behind `RandomGeneratorInterface`:

- `float(): float`
- `int(int $min, int $max): int`
- `pickArrayKey(array $items): int|string`
- `seedFingerprint(): ?string`

Built-in Generators
-------------------

`SecureRandomGenerator`
   Cryptographically secure source (default in `Draw`).

`SeededRandomGenerator`
   Deterministic pseudo-random source (MT19937) for repeatable tests/simulations.

Usage Examples
--------------

Default secure mode:

.. code-block:: php

   $draw = new \Infocyph\Draw\Draw();

Deterministic global mode:

.. code-block:: php

   use Infocyph\Draw\Draw;
   use Infocyph\Draw\Random\SeededRandomGenerator;

   $draw = new Draw(new SeededRandomGenerator(12345));

Campaign deterministic override:

.. code-block:: php

   $result = $draw->execute([
       'method' => 'campaign.run',
       'items' => [...],
       'candidates' => [...],
       'options' => ['seed' => 12345],
   ]);

Guidance
--------

- Use secure RNG for production fairness.
- Use seeded RNG for reproducibility, regression tests, and simulation analysis.
