Usage Scenarios
===============

Scenario 1: Flash Giveaway (Unique Winners)
--------------------------------------------

Use `grand` when each user should win at most once.

.. code-block:: php

   <?php
   use Infocyph\Draw\Draw;

   $draw = new Draw();

   $result = $draw->execute([
       'method' => 'grand',
       'items' => ['gift_card' => 10, 'vip_pass' => 2],
       'candidates' => $userIds,
   ]);

Check fulfillment:

.. code-block:: php

   <?php
   if ($result['meta']['fulfilled'] === false) {
       // inspect partialReason + unfilledCount
   }

Scenario 1b: Grand Draw from CSV Source
---------------------------------------

When candidates come from a file instead of an in-memory list:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'grand',
       'items' => ['gift_card' => 5],
       'sourceFile' => '/path/to/users.csv',
   ]);

CSV notes:

- first column is treated as user ID
- duplicates are deduplicated
- no header auto-skip

Scenario 2: Weighted Reward Amounts
-----------------------------------

Use `lucky` with weighted amount maps for controlled payout distributions.

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'lucky',
       'items' => [
           [
               'item' => 'coins',
               'chances' => 100,
               'amountMode' => 'weighted',
               'amounts' => ['10' => 0.65, '50' => 0.25, '100' => 0.10],
           ],
       ],
       'options' => ['count' => 100],
   ]);

Scenario 3: Rotation Without Repeats
------------------------------------

Use `weightedElimination` for weighted picks that remove selected items.

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'weightedElimination',
       'items' => [
           ['name' => 'tier_a', 'weight' => 5],
           ['name' => 'tier_b', 'weight' => 3],
           ['name' => 'tier_c', 'weight' => 2],
       ],
       'options' => ['count' => 3],
   ]);

Scenario 4: Rule-Aware Campaign with Explainability
----------------------------------------------------

Use `campaign.run` with `withExplain` for why/why-not traces.

.. code-block:: php

   <?php
   use Infocyph\Draw\State\MemoryCachePool;

   $result = $draw->execute([
       'method' => 'campaign.run',
       'items' => [
           'gold' => ['count' => 1, 'weight' => 3, 'group' => 'premium'],
           'silver' => ['count' => 3, 'weight' => 1, 'group' => 'basic'],
       ],
       'candidates' => $userIds,
       'options' => [
           'cachePool' => new MemoryCachePool(),
           'rules' => [
               'perUserCap' => 1,
               'groupQuota' => ['premium' => 1, 'basic' => 3],
           ],
           'withExplain' => true,
       ],
   ]);

Inspect explain payload:

.. code-block:: php

   <?php
   $explainByItem = $result['raw']['explain'] ?? [];
   $goldExplain = $explainByItem['gold'] ?? [];
   // each element includes status, slot, rejectedSummary, and attempts

Scenario 5: Multi-Phase Campaign
---------------------------------

Use `campaign.batch` when campaigns unfold in stages.

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'campaign.batch',
       'candidates' => $userIds,
       'options' => [
           'rules' => ['perUserCap' => 1],
           'phases' => [
               [
                   'name' => 'launch',
                   'items' => ['starter' => ['count' => 100, 'weight' => 2]],
               ],
               [
                   'name' => 'retention',
                   'items' => ['booster' => ['count' => 50, 'weight' => 1]],
               ],
           ],
       ],
   ]);

Note:

- phases share the same `cachePool` for a single batch request by default,
- so rule counters can carry across phases.

Scenario 6: Reproducible Simulation Run
---------------------------------------

Use `campaign.simulate` with seed for deterministic repeated analysis.

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'campaign.simulate',
       'items' => [
           'gold' => ['count' => 1, 'weight' => 2],
           'silver' => ['count' => 2, 'weight' => 1],
       ],
       'candidates' => $userIds,
       'options' => [
           'iterations' => 5000,
           'seed' => 42,
       ],
   ]);

Scenario 7: Auditable Production Execution
------------------------------------------

Capture and verify campaign audits.

.. code-block:: php

   <?php
   use Infocyph\Draw\Audit\AuditTrail;

   $audit = $result['raw']['audit'];

   $verified = AuditTrail::verify(
       $audit,
       $auditConfiguration,
       $result['raw']['winners'],
       $seedFingerprint,
       $sharedSecret,
   );
