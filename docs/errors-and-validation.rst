Errors and Validation
=====================

Primary Exceptions
------------------

- `ValidationException`: invalid request payloads or unsupported method data.
- `EmptyPoolException`: method needs items/users but pool is empty.
- `DrawExhaustedException`: draw flow cannot produce a valid result.
- `DrawException`: generic draw base type.

PSR-6 Specific Exception
------------------------

- `InvalidCacheKeyException` implements `Psr\\Cache\\InvalidArgumentException` for invalid cache keys in the built-in memory pool.

Validation Patterns
-------------------

Common checks performed by handlers:

- required `method` string
- required `items` where applicable
- required `candidates` for `grand` and campaign methods
- numeric/positive weights in weighted methods
- method-specific required item keys
- strict lucky `amountMode` to payload matching

Partial Results vs Exceptions
-----------------------------

Under-fulfillment is often returned as a valid response rather than thrown exception.

Inspect response metadata:

- `meta.fulfilled`
- `meta.partialReason`
- `meta.unfilledCount`

Troubleshooting Checklist
-------------------------

1. Confirm request method name is supported.
2. Confirm required fields for that method are present.
3. Confirm item schema matches method requirements.
4. For campaign runs, verify rule constraints and eligibility callback logic.
5. For reproducibility, verify seed usage and request fingerprints.
