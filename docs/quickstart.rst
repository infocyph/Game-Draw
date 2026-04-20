Quickstart
==========

Create a Draw Instance
----------------------

.. code-block:: php

   <?php
   use Infocyph\Draw\Draw;

   $draw = new Draw();

Quick Lucky Example
-------------------

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'lucky',
       'items' => [
           ['item' => 'gift_a', 'chances' => 10, 'amountMode' => 'list', 'amounts' => [1, 2]],
           ['item' => 'gift_b', 'chances' => 20, 'amountMode' => 'weighted', 'amounts' => ['5' => 0.25, '10' => 0.75]],
       ],
       'options' => ['count' => 2],
   ]);

Grand Winner Example
--------------------

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'grand',
       'items' => ['gift_a' => 2, 'gift_b' => 1],
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
   ]);

Campaign Example with PSR-6 Cache Pool
--------------------------------------

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
           'rules' => ['perUserCap' => 1],
           'withExplain' => true,
           'seed' => 12345,
       ],
   ]);

Read Result Meta
----------------

.. code-block:: php

   <?php
   if ($result['meta']['fulfilled'] === false) {
       $reason = $result['meta']['partialReason'];
       $missing = $result['meta']['unfilledCount'];
   }
