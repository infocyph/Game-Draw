Overview
========

What Game Draw Solves
---------------------

Game Draw provides a single, consistent draw API for:

- weighted item rewards,
- unique user winner allocation,
- and rule-aware campaign flows.

It standardizes request/response shapes so integrations do not need per-method transport contracts.

Architecture at a Glance
------------------------

`Draw`
   Public entrypoint and router by `method`.

`LuckyMethodHandler`
   Handles `lucky` weighted item + amount-mode draws.

`ItemMethodHandler`
   Handles non-campaign item methods (`probability`, `elimination`, etc.).

`UserMethodHandler`
   Handles `grand` user winner allocation.

`CampaignMethodHandler`
   Handles `campaign.run`, `campaign.batch`, `campaign.simulate`.

`CampaignEngine`
   Internal slot planning, eligibility filtering, and winner selection.

`RuleEngine` + `RuleSet`
   Rule decisions and state tracking.

`AuditTrail`
   Request/result fingerprinting and signature verification helpers.

`ResultBuilder`
   Produces standardized response envelope and fulfillment metadata.

Method Families
---------------

`lucky`
   Weighted item pick with amount modes: `list`, `weighted`, `range`.

Flexible item methods
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
   Unique user allocation from a candidate pool.

Campaign methods
   - `campaign.run`
   - `campaign.batch`
   - `campaign.simulate`

Core Guarantees
---------------

- unified response metadata across all methods,
- partial fulfillment visibility (`fulfilled`, `partialReason`, `unfilledCount`),
- deterministic reproducibility when seeded,
- and audit verification helpers for integrity workflows.

Where to Find Specific Guidance
-------------------------------

- Per-method behavior: :doc:`methods/index`
- Request/response contract and option matrix: :doc:`request-response`
- Campaign rule state storage (PSR-6): :doc:`psr6-cache`
- End-to-end usage patterns: :doc:`scenarios`
