Audit and Reproducibility
=========================

AuditTrail API
--------------

Use `Infocyph\\Draw\\Audit\\AuditTrail` to create and verify deterministic artifacts.

Create Audit
------------

.. code-block:: php

   <?php
   use Infocyph\Draw\Audit\AuditTrail;

   $audit = AuditTrail::create(
       configuration: $configuration,
       result: $result,
       seedFingerprint: $seedFingerprint,
       secret: 'shared-secret',
   );

Verify Audit
------------

.. code-block:: php

   <?php
   $isValid = AuditTrail::verify(
       audit: $audit,
       configuration: $configuration,
       result: $result,
       seedFingerprint: $seedFingerprint,
       secret: 'shared-secret',
   );

Audit Fields
------------

The audit object contains:

- `generatedAt`
- `configHash`
- `resultHash`
- `seedFingerprint`
- `signatureAlgorithm`
- `signaturePayload`
- `signature`

Signature Behavior
------------------

- If `secret` is provided, signature uses `hmac-sha256`.
- If no `secret` is provided, signature uses `sha256`.

Request Fingerprinting
----------------------

Generate canonical request fingerprints:

.. code-block:: php

   <?php
   use Infocyph\Draw\Draw;

   $fingerprint = Draw::requestFingerprint($request);

This is useful for:

- idempotency keys,
- replay correlation,
- simulation grouping,
- audit indexing.

Reproducible Workflow Example
-----------------------------

.. code-block:: php

   <?php
   $seed = 1907;
   $requestFingerprint = Draw::requestFingerprint($request);

   $result = $draw->execute([
       ...$request,
       'options' => [...($request['options'] ?? []), 'seed' => $seed],
   ]);

   $audit = $result['raw']['audit'] ?? null;
