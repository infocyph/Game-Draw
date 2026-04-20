Grand Method
============

Method Name
-----------

- `grand`

Purpose
-------

Allocates user winners to item counts using a user pool without replacement.

Input
-----

- `items`: map of item IDs to non-negative integer counts
- user source:

  - `candidates`: non-empty user ID array, or
  - `sourceFile`: path to CSV file containing user IDs

When both are present, `sourceFile` is used.

Options
-------

- `retryCount` (int, default `10`): kept in response meta for compatibility.

Selection Behavior
------------------

- Uses a shrinking candidate pool (no replacement).
- A user can win at most once per draw execution.
- If requested slots exceed unique users, response is partial.

Partial Fulfillment Metadata
----------------------------

When there are not enough unique users:

- `meta.fulfilled = false`
- `meta.partialReason = 'insufficient_unique_candidates'`
- `meta.unfilledCount > 0`

Example
-------

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'grand',
       'items' => ['gift_a' => 5, 'gift_b' => 2],
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => ['retryCount' => 50],
   ]);

CSV Source Example
------------------

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => 'grand',
       'items' => ['gift_a' => 2],
       'sourceFile' => '/path/to/users.csv',
   ]);
