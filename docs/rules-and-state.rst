Rules and State
===============

RuleSet
-------

Campaign rules are defined by `Infocyph\\Draw\\Rules\\RuleSet`:

- `perUserCap` (default `1`, minimum `1`)
- `perItemCap` (map: itemId => non-negative int)
- `groupQuota` (map: group => non-negative int)
- `cooldownSeconds` (default `0`, non-negative)

Example:

.. code-block:: php

   <?php
   'rules' => [
       'perUserCap' => 1,
       'perItemCap' => ['gold' => 1],
       'groupQuota' => ['premium' => 1, 'basic' => 2],
       'cooldownSeconds' => 60,
   ]

Rule Engine Decisions
---------------------

Campaign eligibility can be rejected by reasons such as:

- `per_user_cap_reached`
- `per_item_cap_reached`
- `group_quota_reached`
- `cooldown_active`

PSR-6 State Contract
--------------------

Campaign rule tracking uses `Psr\\Cache\\CacheItemPoolInterface`.

Provide a pool through `options.cachePool`.

Built-in Pool
-------------

`Infocyph\\Draw\\State\\MemoryCachePool` is provided for in-process usage.

.. code-block:: php

   <?php
   use Infocyph\Draw\State\MemoryCachePool;

   'options' => [
       'cachePool' => new MemoryCachePool(),
   ]

State Key Patterns
------------------

Current rule engine stores counters/values under keys like:

- `rules.user_wins.{userId}`
- `rules.item_wins.{itemId}`
- `rules.group_wins.{group}`
- `rules.user_last_win.{userId}`

Persistence Guidance
--------------------

- Use in-memory pool for single-process local/testing use.
- Use distributed/shared PSR-6 pools when campaign state must be shared across workers.
- For high-concurrency counters, choose a backend and deployment pattern that preserves expected counter behavior.
