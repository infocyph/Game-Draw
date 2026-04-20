Rules and State
===============

RuleSet
-------

Campaign rules are defined by `Infocyph\\Draw\\Rules\\RuleSet`:

- `perUserCap` (default `1`, minimum `1`)
- `perItemCap` (map of itemId => non-negative int)
- `groupQuota` (map of group => non-negative int)
- `cooldownSeconds` (default `0`, non-negative)

Example:

.. code-block:: php

   'rules' => [
       'perUserCap' => 1,
       'perItemCap' => ['gold' => 1],
       'groupQuota' => ['premium' => 1, 'basic' => 2],
       'cooldownSeconds' => 60,
   ]

Rule Engine Decisions
---------------------

Campaign eligibility can be rejected by rule reasons such as:

- `per_user_cap_reached`
- `per_item_cap_reached`
- `group_quota_reached`
- `cooldown_active`

State Adapter Contract
----------------------

Campaign rule tracking is abstracted by `StateAdapterInterface`.

Required methods:

- `get(string $key, mixed $default = null): mixed`
- `set(string $key, mixed $value): void`
- `increment(string $key, int $by = 1): int`
- `clear(): void`

Built-in Adapter
----------------

`MemoryStateAdapter` is provided for in-process state.

For multi-process or persistent systems, provide your own adapter implementation (for example Redis or database-backed storage).

State Key Patterns
------------------

Current rule engine writes keys in namespaces like:

- `rules.user_wins.{userId}`
- `rules.item_wins.{itemId}`
- `rules.group_wins.{group}`
- `rules.user_last_win.{userId}`

This is useful when implementing custom adapters with observability.
