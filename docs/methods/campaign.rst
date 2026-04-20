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

Per-user allowed gifts example:

.. code-block:: php

   <?php
   $allowedItemsByUser = [
       'user_a' => ['gift_b'],
       'user_b' => ['gift_b'],
       'user_c' => ['*'], // wildcard = any gift
   ];

   $result = $draw->execute([
       'method' => 'campaign.run',
       'items' => [
           'gift_b' => ['count' => 2, 'group' => 'b_group'],
           'gift_c' => ['count' => 1, 'group' => 'c_group'],
       ],
       'candidates' => ['user_a', 'user_b', 'user_c'],
       'options' => [
           'eligibility' => function (string $userId, string $itemId, ?string $group, array $ctx) use ($allowedItemsByUser): bool {
               $allowed = $allowedItemsByUser[$userId] ?? [];
               return in_array('*', $allowed, true) || in_array($itemId, $allowed, true);
           },
       ],
   ]);

Item Definition (`campaign.run` and `campaign.simulate`)
---------------------------------------------------------

Items normalize into:

- `count` (int, default `1`)
- `weight` (float, default `1.0`)
- `group` (?string)

`weight` is used in weighted slot scheduling before winner assignment.

Accepted item shapes:

`map` shape (recommended)
   .. code-block:: php

      <?php
      'items' => [
          'gold' => ['count' => 1, 'weight' => 2, 'group' => 'premium'],
          'silver' => ['count' => 2, 'weight' => 1, 'group' => 'basic'],
      ]

`count shorthand`
   .. code-block:: php

      <?php
      'items' => [
          'gold' => 1,
          'silver' => 2,
      ]

`list` shape (uses `item` or `name` for identifier)
   .. code-block:: php

      <?php
      'items' => [
          ['item' => 'gold', 'count' => 1, 'weight' => 2, 'group' => 'premium'],
          ['name' => 'silver', 'count' => 2, 'weight' => 1, 'group' => 'basic'],
      ]

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

`explain` entry shape (per attempted slot):

- `status`: `selected` or `exhausted`
- `winner` (when selected)
- `slot`
- `eligiblePoolSize` (when selected)
- `rejectedSummary`: reason counts
- `attempts`: candidate-level decision trail (when enabled)

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

Phase `rules` can be either:

- array payload (converted via `RuleSet::fromArray`), or
- a prebuilt `RuleSet` instance.

Raw payload contains:

- `phases`: map of phase name to phase result
- `partialReason`: merged dominant partial reason across phases
- `audit`: batch-level audit artifact

State behavior across phases:

- the same cache pool instance is reused for all phases in one `campaign.batch` request,
- so rule counters can carry across phases unless you provide isolated pools per request.

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

Candidate normalization notes
-----------------------------

- Candidate IDs are trimmed.
- Empty IDs are ignored.
- Duplicate IDs are deduplicated before draw execution.
