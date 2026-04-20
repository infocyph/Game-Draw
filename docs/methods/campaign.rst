Campaign Methods
================

Method Names
------------

- `campaign.run`
- `campaign.batch`
- `campaign.simulate`

Shared Inputs
-------------

- `candidates`: non-empty user ID list
- `options.rules`: optional `RuleSet` configuration payload
- `options.seed`: optional deterministic RNG override
- `options.auditSecret`: optional HMAC secret for audits
- `options.eligibility`: optional callback filter
- `options.cachePool`: optional PSR-6 cache pool (`CacheItemPoolInterface`)

Eligibility Callback
--------------------

Signature:

.. code-block:: php

   <?php
   fn (string $userId, string $itemId, ?string $group, array $ctx): bool

`$ctx` contains:

- `slot` (1-based slot index)
- `timestamp` (current unix timestamp)

Item Definition (`campaign.run` and `campaign.simulate`)
---------------------------------------------------------

Items normalize into:

- `count` (int, default `1`)
- `weight` (float, default `1.0`)
- `group` (?string)

`weight` is used in weighted slot scheduling before winner assignment.

campaign.run
------------

Runs one rule-aware campaign draw.

Options:

- `withExplain` (bool, default `false`)
- `retryLimit` (int, default `100`, compatibility metadata)

Raw payload contains:

- `winners`: `array<string, list<string>>`
- `slotPlan`: `list<array{itemId: string, group: ?string}>`
- `partialReason`: `?string`
- `audit`: audit artifact object
- `explain`: per-item trace data (when `withExplain=true`)

Example with explanation + cache pool:

.. code-block:: php

   <?php
   use Infocyph\Draw\State\MemoryCachePool;

   $result = $draw->execute([
       'method' => 'campaign.run',
       'items' => [
           'gold' => ['count' => 1, 'weight' => 2, 'group' => 'premium'],
           'silver' => ['count' => 2, 'weight' => 1, 'group' => 'basic'],
       ],
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => [
           'cachePool' => new MemoryCachePool(),
           'rules' => [
               'perUserCap' => 1,
               'perItemCap' => ['gold' => 1],
               'groupQuota' => ['premium' => 1, 'basic' => 2],
           ],
           'withExplain' => true,
           'seed' => 12345,
           'auditSecret' => 'shared-secret',
       ],
   ]);

campaign.batch
--------------

Runs campaign phases sequentially.

`campaign.batch` does not require top-level `items`; it expects `options.phases`.

Phase schema:

- `name` (optional)
- `items` (required)
- `rules` (optional phase override)
- `seed` (optional phase-specific seed)

Raw payload contains:

- `phases`: map of phase name to phase result
- `partialReason`: merged dominant partial reason across phases
- `audit`: batch-level audit artifact

Example:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'campaign.batch',
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => [
           'rules' => ['perUserCap' => 1],
           'phases' => [
               [
                   'name' => 'phase_1',
                   'items' => ['item_a' => ['count' => 2, 'weight' => 2]],
               ],
               [
                   'name' => 'phase_2',
                   'items' => ['item_b' => ['count' => 2, 'weight' => 1]],
                   'seed' => 908,
               ],
           ],
       ],
   ]);

campaign.simulate
-----------------

Performs Monte Carlo-style simulation over repeated campaign executions.

Options:

- `iterations` (int, default `1000`)
- `seed` (int, default `0`)
- `retryLimit` (compatibility metadata)

Raw payload contains:

- `iterations`
- `totalSlots`
- `userDistribution` with `wins`, `rate`, and `ci95`
- `itemDistribution` with `wins`, `avgPerIteration`

Example:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'campaign.simulate',
       'items' => [
           'gold' => ['count' => 1, 'weight' => 2],
           'silver' => ['count' => 2, 'weight' => 1],
       ],
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => ['iterations' => 2000, 'seed' => 42],
   ]);

Partial Fulfillment
-------------------

If constraints or eligibility block slots:

- `meta.fulfilled` becomes `false`
- `meta.partialReason` is set
- `meta.unfilledCount` reports missing slots
