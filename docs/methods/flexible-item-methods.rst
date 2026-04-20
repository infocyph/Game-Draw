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

Common Options
--------------

- `count` (int, default `1`): number of draws
- `check` (bool, default `true`): validate required fields by method

`batched`-specific option:

- `withReplacement` (bool, default `false`)

Method Requirements and Behavior
--------------------------------

`probability`
   Required fields per item: `name`, `weight`

   Picks by normalized weight with replacement.

`elimination`
   Required fields per item: `name`

   Picks uniformly and removes picked item.

`weightedElimination`
   Required fields per item: `name`, `weight`

   Picks by weight and removes picked item.

`roundRobin`
   Required fields per item: `name`

   Cycles through item list in order.

`cumulative`
   Required fields per item: `name`

   Maintains cumulative scores and picks strongest candidate each round.

`batched`
   Required fields per item: `name`

   Performs `count` picks in one batch; replacement controlled by `withReplacement`.

`timeBased`
   Required fields per item: `name`, `weight`, `time`

   Applies urgency boost from elapsed time since last pick. Supported `time` values:

   - `minute`
   - `hourly`
   - `daily`
   - `weekly`
   - `monthly`

   Unknown values fall back to `daily` behavior.

`weightedBatch`
   Required fields per item: `name`, `weight`

   Batch variant of weighted probability selection.

`sequential`
   Required fields per item: `name`

   Deterministic index-based cycling.

`rangeWeighted`
   Required fields per item: `name`, `min`, `max`, `weight`

   Draws a range by weight, then samples inside range.

   - returns integer when both boundaries are integers
   - returns float otherwise

Flexible Examples
-----------------

.. code-block:: php

   // weighted elimination
   $result = $draw->execute([
       'method' => 'weightedElimination',
       'items' => [
           ['name' => 'item1', 'weight' => 10],
           ['name' => 'item2', 'weight' => 20],
       ],
       'options' => ['count' => 2],
   ]);

.. code-block:: php

   // range weighted
   $result = $draw->execute([
       'method' => 'rangeWeighted',
       'items' => [
           ['name' => 'item1', 'min' => 1, 'max' => 50, 'weight' => 10],
           ['name' => 'item2', 'min' => 5, 'max' => 25, 'weight' => 15],
       ],
       'options' => ['count' => 2],
   ]);
