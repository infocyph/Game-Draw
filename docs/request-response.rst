Request and Response Contract
=============================

Unified Request
---------------

All draw calls use:

.. code-block:: php

   $result = $draw->execute([
       'method' => '...',
       'items' => [...],
       'candidates' => [...],
       'sourceFile' => '...',
       'options' => [...],
   ]);

Field reference:

- `method` (required): draw method name.
- `items` (required except `campaign.batch`): method-specific payload.
- `candidates` (required for `grand` and all `campaign.*` methods): list of user IDs.
- `sourceFile` (optional for `grand`): CSV file path alternative to `candidates`.
- `options` (optional): method-specific options.

Unified Response
----------------

Every method returns:

.. code-block:: php

   [
       'method' => '...',
       'entries' => [
           [
               'itemId' => ?string,
               'candidateId' => ?string,
               'value' => mixed,
               'meta' => array,
           ],
       ],
       'raw' => mixed,
       'meta' => [
           'mode' => 'single|multi',
           'requestedCount' => int,
           'returnedCount' => int,
           'fulfilled' => bool,
           'partialReason' => ?string,
           'unfilledCount' => int,
       ],
   ]

Metadata Semantics
------------------

`requestedCount`
   How many results were requested by method/options.

`returnedCount`
   How many entries were actually produced.

`fulfilled`
   `true` when `returnedCount >= requestedCount`.

`partialReason`
   `null` when fulfilled. Otherwise a reason such as:

   - `insufficient_unique_candidates`
   - `no_eligible_candidates`
   - `unfulfilled`

`unfilledCount`
   `max(0, requestedCount - returnedCount)`.

Entry Semantics
---------------

- `itemId`: relevant item/prize identifier when applicable.
- `candidateId`: selected user ID for user/campaign methods.
- `value`: the selected value (item/user/amount/rate depending on method).
- `meta`: entry-level method details when provided.
