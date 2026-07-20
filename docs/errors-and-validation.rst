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
- strict integer and boolean option types
- finite numeric values and unique item/phase identifiers
- bounded draw counts, campaign slots, candidates, and simulation iterations

Validation cannot be disabled with `options.check`; the key is accepted only for backward compatibility.

Operational Limits
------------------

- up to 10,000 item definitions per request or campaign phase
- up to 100,000 requested draws or campaign slots
- up to 1,000 campaign batch phases
- up to 1,000,000 candidate rows or IDs
- up to 100,000 simulation iterations
- up to 100,000,000 candidate evaluations per campaign request

These limits bound response size, memory growth, and synchronous work. Split larger workloads into application-controlled batches.

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
