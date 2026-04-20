Quickstart
==========

Minimal Example
---------------

.. code-block:: php

   use Infocyph\Draw\Draw;

   $draw = new Draw();

   $result = $draw->execute([
       'method' => 'lucky',
       'items' => [
           ['item' => 'gift_a', 'chances' => 10, 'amountMode' => 'list', 'amounts' => [1, 2]],
           ['item' => 'gift_b', 'chances' => 20, 'amountMode' => 'weighted', 'amounts' => ['5' => 0.25, '10' => 0.75]],
       ],
       'options' => ['count' => 2],
   ]);

   // $result contains method, entries, raw, meta

Grand Draw Example
------------------

.. code-block:: php

   $result = $draw->execute([
       'method' => 'grand',
       'items' => ['gift_a' => 2, 'gift_b' => 1],
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
   ]);

Campaign Example
----------------

.. code-block:: php

   $result = $draw->execute([
       'method' => 'campaign.run',
       'items' => [
           'gold' => ['count' => 1, 'weight' => 2, 'group' => 'premium'],
           'silver' => ['count' => 2, 'weight' => 1, 'group' => 'basic'],
       ],
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => [
           'rules' => ['perUserCap' => 1],
           'withExplain' => true,
           'seed' => 12345,
       ],
   ]);
