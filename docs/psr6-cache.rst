PSR-6 Cache Integration
=======================

Why PSR-6 Here
--------------

Campaign rule tracking now uses PSR-6 `CacheItemPoolInterface` directly.

This gives you:

- framework interoperability,
- pluggable in-memory/distributed stores,
- and cleaner integration with existing cache infrastructure.

Using the Built-in Memory Pool
------------------------------

.. code-block:: php

   <?php
   use Infocyph\Draw\Draw;
   use Infocyph\Draw\State\MemoryCachePool;

   $draw = new Draw();

   $result = $draw->execute([
       'method' => 'campaign.run',
       'items' => ['gold' => ['count' => 1]],
       'candidates' => ['u1', 'u2', 'u3'],
       'options' => [
           'rules' => ['perUserCap' => 1],
           'cachePool' => new MemoryCachePool(),
       ],
   ]);

Using an External PSR-6 Pool
----------------------------

Any PSR-6 pool is accepted:

.. code-block:: php

   <?php
   use Infocyph\Draw\Draw;
   use Psr\Cache\CacheItemPoolInterface;

   /** @var CacheItemPoolInterface $pool */
   $pool = getYourFrameworkCachePool();

   $draw = new Draw();

   $result = $draw->execute([
       'method' => 'campaign.batch',
       'candidates' => ['u1', 'u2', 'u3', 'u4'],
       'options' => [
           'cachePool' => $pool,
           'rules' => ['perUserCap' => 1],
           'phases' => [
               ['name' => 'phase_1', 'items' => ['a' => ['count' => 1]]],
               ['name' => 'phase_2', 'items' => ['b' => ['count' => 1]]],
           ],
       ],
   ]);

Concurrency Note
----------------

Rule counters use read-modify-write increments through the pool API.

For high-concurrency workloads, prefer a backend that supports strong concurrency behavior in your deployment model.
