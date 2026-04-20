Request and Response Contract
=============================

Unified Request Envelope
------------------------

All draw calls use `Draw::execute(array $request)`.

.. code-block:: php

   <?php
   $result = $draw->execute([
       'method' => '...',
       'items' => [...],
       'candidates' => [...],
       'sourceFile' => '...',
       'options' => [...],
   ]);

Field Reference
---------------

- `method` (required): one of supported method names.
- `items` (required except `campaign.batch`): method-specific payload.
- `candidates` (required for `grand` and `campaign.*`): user IDs.
- `sourceFile` (optional for `grand`): CSV alternative to `candidates`.
- `options` (optional): method options.

Method-to-Required-Field Map
----------------------------

- `lucky`: `items`
- flexible item methods: `items`
- `grand`: `items` + (`candidates` or `sourceFile`)
- `campaign.run`: `items` + `candidates`
- `campaign.batch`: `candidates` + `options.phases`
- `campaign.simulate`: `items` + `candidates`

Unified Response Envelope
-------------------------

Every method returns:

.. code-block:: php

   <?php
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
           // method-specific extra metadata may be present
       ],
   ]

Meta Semantics
--------------

- `requestedCount`: requested slots/picks
- `returnedCount`: produced entries
- `fulfilled`: true when request is fully satisfied
- `partialReason`: explanation when not fulfilled
- `unfilledCount`: remaining slots not returned

Common `partialReason` values:

- `insufficient_unique_candidates`
- `no_eligible_candidates`
- `unfulfilled`

`raw` Payload by Method Family
------------------------------

`lucky` and flexible item methods
   Usually list-like method output values.

`grand`
   `array<string, list<string>>` keyed by item ID.

`campaign.run`
   `winners`, `slotPlan`, optional `explain`, `partialReason`, `audit`.

`campaign.batch`
   `phases`, `partialReason`, `audit`.

`campaign.simulate`
   `iterations`, `totalSlots`, `userDistribution`, `itemDistribution`.
