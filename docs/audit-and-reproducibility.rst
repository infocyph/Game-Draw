Audit and Reproducibility
=========================

Audit Trail API
---------------

Use `Infocyph\\Draw\\Audit\\AuditTrail` to create and verify deterministic draw audit artifacts.

Create
------

.. code-block:: php

   use Infocyph\Draw\Audit\AuditTrail;

   $audit = AuditTrail::create(
       configuration: $configuration,
       result: $result,
       seedFingerprint: $seedFingerprint,
       secret: 'shared-secret',
   );

Verify
------

.. code-block:: php

   $isValid = AuditTrail::verify(
       audit: $audit,
       configuration: $configuration,
       result: $result,
       seedFingerprint: $seedFingerprint,
       secret: 'shared-secret',
   );

Signature Modes
---------------

- with `secret`: `hmac-sha256`
- without `secret`: `sha256`

Audit payload also includes `configHash` and `resultHash` (xxh3 for fast deterministic hashing) plus signature fields.

Request Fingerprint
-------------------

`Draw::requestFingerprint()` creates a canonical hash of request payloads:

.. code-block:: php

   use Infocyph\Draw\Draw;

   $fingerprint = Draw::requestFingerprint($request);

Use this for idempotency keys, simulation tracking, and audit indexing.

Deterministic Runs
------------------

For reproducible outcomes:

- instantiate `Draw` with `SeededRandomGenerator`, or
- pass `options.seed` on campaign methods.

Persist both the request fingerprint and seed fingerprint to replay/verify the same scenario.
