Flexible Item Methods
=====================

Method Names
------------

- `probability`
- `elimination`
- `weightedElimination`
- `roundRobin`
- `cumulative`
- `batched`
- `timeBased`
- `weightedBatch`
- `sequential`
- `rangeWeighted`

Shared Options
--------------

- `count` (int, default `1`)
- `check` (bool, default `true`)

Additional option:

- `withReplacement` applies to `batched` only (default `false`)

Method Requirements and Semantics
---------------------------------

`probability`
   Required item keys: `name`, `weight`

   Weighted draw with replacement.

`elimination`
   Required item keys: `name`

   Uniform draw without replacement.

`weightedElimination`
   Required item keys: `name`, `weight`

   Weighted draw without replacement.

`roundRobin`
   Required item keys: `name`

   Cycles through items in deterministic order.

`cumulative`
   Required item keys: `name`

   Maintains cumulative scores and picks highest score each step.

`batched`
   Required item keys: `name`

   Batch draw for `count` picks, replacement controlled by `withReplacement`.

`timeBased`
   Required item keys: `name`, `weight`, `time`

   Applies urgency boost based on elapsed time since last pick.

   Supported `time` values:

   - `minute`
   - `hourly`
   - `daily`
   - `weekly`
   - `monthly`

`weightedBatch`
   Required item keys: `name`, `weight`

   Batch variant of weighted probability draw.

`sequential`
   Required item keys: `name`

   Deterministic sequential traversal.

`rangeWeighted`
   Required item keys: `name`, `min`, `max`, `weight`

   Chooses a range by weight then samples in that range.

   Return type behavior:

   - integer result when both `min` and `max` are integers
   - float result otherwise

Examples
--------

Probability:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'probability',
       'items' => [
           ['name' => 'item1', 'weight' => 0.2],
           ['name' => 'item2', 'weight' => 0.8],
       ],
       'options' => ['count' => 3],
   ]);

Elimination:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'elimination',
       'items' => [
           ['name' => 'item1'],
           ['name' => 'item2'],
           ['name' => 'item3'],
       ],
       'options' => ['count' => 2],
   ]);

Weighted Elimination:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'weightedElimination',
       'items' => [
           ['name' => 'item1', 'weight' => 10],
           ['name' => 'item2', 'weight' => 20],
       ],
       'options' => ['count' => 2],
   ]);

Round Robin:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'roundRobin',
       'items' => [
           ['name' => 'item1'],
           ['name' => 'item2'],
       ],
       'options' => ['count' => 4],
   ]);

Cumulative:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'cumulative',
       'items' => [
           ['name' => 'item1'],
           ['name' => 'item2'],
       ],
       'options' => ['count' => 3],
   ]);

Batched (without replacement):

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'batched',
       'items' => [
           ['name' => 'item1'],
           ['name' => 'item2'],
           ['name' => 'item3'],
       ],
       'options' => ['count' => 2, 'withReplacement' => false],
   ]);

Time Based:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'timeBased',
       'items' => [
           ['name' => 'item1', 'weight' => 10, 'time' => 'daily'],
           ['name' => 'item2', 'weight' => 20, 'time' => 'weekly'],
       ],
       'options' => ['count' => 2],
   ]);

Weighted Batch:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'weightedBatch',
       'items' => [
           ['name' => 'item1', 'weight' => 10],
           ['name' => 'item2', 'weight' => 20],
       ],
       'options' => ['count' => 3],
   ]);

Sequential:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'sequential',
       'items' => [
           ['name' => 'item1'],
           ['name' => 'item2'],
       ],
       'options' => ['count' => 3],
   ]);

Range Weighted:

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'rangeWeighted',
       'items' => [
           ['name' => 'item1', 'min' => 1, 'max' => 50, 'weight' => 10],
           ['name' => 'item2', 'min' => 5, 'max' => 25, 'weight' => 15],
       ],
       'options' => ['count' => 2],
   ]);
