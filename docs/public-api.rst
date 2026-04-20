Public API Reference
====================

Main Entrypoint
---------------

`Infocyph\\Draw\\Draw`

.. code-block:: php

   <?php
   use Infocyph\Draw\Draw;

   $draw = new Draw();
   $result = $draw->execute($request);

Methods:

- `execute(array<string,mixed> $request): array<string,mixed>`
- `requestFingerprint(array<string,mixed> $request): string`

Random Interfaces and Implementations
-------------------------------------

`Infocyph\\Draw\\Contracts\\RandomGeneratorInterface`

- `float(): float`
- `int(int $min, int $max): int`
- `pickArrayKey(array $items): int|string`
- `seedFingerprint(): ?string`

Built-ins:

- `Infocyph\\Draw\\Random\\SecureRandomGenerator`
- `Infocyph\\Draw\\Random\\SeededRandomGenerator`

Campaign State (PSR-6)
----------------------

Campaign flows accept any `Psr\\Cache\\CacheItemPoolInterface` via `options.cachePool`.

Built-ins in this package:

- `Infocyph\\Draw\\State\\MemoryCachePool`
- `Infocyph\\Draw\\State\\MemoryCacheItem`

Rules
-----

`Infocyph\\Draw\\Rules\\RuleSet`

Constructor arguments:

- `perUserCap` (int, default `1`)
- `perItemCap` (`array<string,int>`, default `[]`)
- `groupQuota` (`array<string,int>`, default `[]`)
- `cooldownSeconds` (int, default `0`)

Factory helpers:

- `RuleSet::fromArray(array<string,mixed> $rules): RuleSet`
- `RuleSet::toArray(): array<string, int|array<string,int>>`

Audit
-----

`Infocyph\\Draw\\Audit\\AuditTrail`

- `create(array $configuration, array $result, ?string $seedFingerprint = null, string $secret = ''): array`
- `verify(array $audit, array $configuration, array $result, ?string $seedFingerprint = null, string $secret = ''): bool`
- `fingerprint(array $payload): string`

Exceptions
----------

Common exception types:

- `Infocyph\\Draw\\Exceptions\\ValidationException`
- `Infocyph\\Draw\\Exceptions\\EmptyPoolException`
- `Infocyph\\Draw\\Exceptions\\DrawExhaustedException`
- `Infocyph\\Draw\\Exceptions\\DrawException`
