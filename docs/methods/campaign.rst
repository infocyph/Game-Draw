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
- `options.rules`: rule set (optional)
- `options.seed`: optional deterministic RNG override
- `options.auditSecret`: optional HMAC secret for audit signatures
- `options.eligibility`: optional callable
- `options.stateAdapter`: optional custom `StateAdapterInterface`

Eligibility callback signature:

.. code-block:: php

   fn (string $userId, string $itemId, ?string $group, array $ctx): bool

`$ctx` currently includes:

- `slot` (int)
- `timestamp` (int)

Item Definition (`campaign.run` and `campaign.simulate`)
---------------------------------------------------------

Items normalize into:

- `count` (int, default `1`)
- `weight` (float, default `1.0`)
- `group` (?string, optional)

Weights are used for slot scheduling.

campaign.run
------------

Runs one rule-aware campaign draw.

Options:

- `withExplain` (bool, default `false`)
- `retryLimit` (int, default `100`, compatibility metadata)

Raw output includes:

- `winners`
- `slotPlan`
- `partialReason`
- `explain` (when enabled)
- `audit`

Example:

.. code-block:: php

   $result = $draw->execute([
       'method' => 'campaign.run',
       'items' => [
           'gold' => ['count' => 1, 'weight' => 2, 'group' => 'premium'],
           'silver' => ['count' => 2, 'weight' => 1, 'group' => 'basic'],
       ],
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => [
           'rules' => [
               'perUserCap' => 1,
               'perItemCap' => ['gold' => 1],
               'groupQuota' => ['premium' => 1, 'basic' => 2],
           ],
           'withExplain' => true,
           'seed' => 12345,
       ],
   ]);

campaign.batch
--------------

Runs campaign phases in sequence from `options.phases`.

Phase schema:

- `name` (optional)
- `items` (required)
- `rules` (optional; overrides default rules)
- `seed` (optional; phase-specific deterministic seed)

Example:

.. code-block:: php

   $result = $draw->execute([
       'method' => 'campaign.batch',
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => [
           'rules' => ['perUserCap' => 1],
           'phases' => [
               ['name' => 'phase_1', 'items' => ['item_a' => ['count' => 2]]],
               ['name' => 'phase_2', 'items' => ['item_b' => ['count' => 2]]],
           ],
       ],
   ]);

campaign.simulate
-----------------

Monte Carlo style repeated campaign runs using seeded iterations.

Options:

- `iterations` (int, default `1000`)
- `seed` (int, default `0`)
- `retryLimit` (compatibility metadata)

Returns aggregate distributions:

- `userDistribution`
- `itemDistribution`
- `totalSlots`

Example:

.. code-block:: php

   $result = $draw->execute([
       'method' => 'campaign.simulate',
       'items' => ['gold' => ['count' => 1], 'silver' => ['count' => 2]],
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => ['iterations' => 1000, 'seed' => 42],
   ]);

Partial Results
---------------

If constraints or eligibility block slots, campaign responses expose partial metadata through the standard response `meta` fields.
