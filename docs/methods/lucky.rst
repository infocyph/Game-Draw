Lucky Method
============

Method Name
-----------

- `lucky`

Purpose
-------

Selects an item by weighted `chances`, then selects an amount by one of three amount modes.

Item Schema
-----------

Each item must include:

- `item` (string)
- `chances` (positive numeric)
- `amounts` (shape depends on mode)

Optional:

- `amountMode` (`list`, `weighted`, or `range`)

If `amountMode` is omitted, mode is inferred from `amounts`.

Amount Modes
------------

`list`
   `amounts` is a sequential numeric array.

   Example:

   .. code-block:: php

      <?php
      [1, 2, 5]

`weighted`
   `amounts` is a map: amount value as key, weight as value.

   Example:

   .. code-block:: php

      <?php
      ['5' => 0.25, '10' => 0.75]

`range`
   `amounts` is `"min,max,bias"`.

   Example:

   .. code-block:: php

      <?php
      '1,10,1.4'

   Behavior:

   - `max` must be greater than `min`
   - `bias` must be greater than `0`

Options
-------

- `count` (int, default `1`): number of picks
- `check` (bool, default `true`): enforce strict payload validation

Example
-------

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'lucky',
       'items' => [
           ['item' => 'gift_a', 'chances' => 10, 'amountMode' => 'list', 'amounts' => [1, 2]],
           ['item' => 'gift_b', 'chances' => 20, 'amountMode' => 'weighted', 'amounts' => ['5' => 0.25, '10' => 0.75]],
           ['item' => 'gift_c', 'chances' => 5, 'amountMode' => 'range', 'amounts' => '1,10,1.4'],
       ],
       'options' => ['count' => 2],
   ]);

Validation Notes
----------------

- Declared `amountMode` must match the payload shape.
- Zero or negative `chances` are rejected.
- Weighted amount keys must be numeric.
- Weighted amount weights must be positive.
