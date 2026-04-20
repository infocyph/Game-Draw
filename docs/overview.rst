Overview
========

What Game Draw Solves
---------------------

Game Draw gives you a single entrypoint (`Infocyph\\Draw\\Draw`) for multiple draw strategies while keeping:

- a shared request envelope,
- a shared response envelope,
- injectable random generation,
- campaign rule/state controls,
- and optional audit verification.

Core Design
-----------

The package routes requests by `method` into specialized handlers:

- `LuckyMethodHandler` for `lucky`
- `ItemMethodHandler` for flexible item methods
- `UserMethodHandler` for `grand`
- `CampaignMethodHandler` for `campaign.run`, `campaign.batch`, `campaign.simulate`

Every handler returns a standardized response shape via `ResultBuilder`.

Method Families
---------------

`lucky`
   Weighted item selection plus configurable amount generation modes (`list`, `weighted`, `range`).

Item methods
   Non-campaign item draws:

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

`grand`
   Draws winners from user candidates across prize counts using a no-replacement pool.

Campaign methods
   Rule-aware user allocation per item slot:

   - `campaign.run`
   - `campaign.batch`
   - `campaign.simulate`

High-Level Guarantees
---------------------

- Consistent response metadata:

  - `requestedCount`
  - `returnedCount`
  - `fulfilled`
  - `partialReason`
  - `unfilledCount`

- Pool-based unique user selection for `grand`
- Weighted campaign item slot scheduling in campaign flows
- Audit creation and verification helpers
- Deterministic reproducibility support through seeded RNG and request fingerprints
