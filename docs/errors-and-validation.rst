Errors and Validation
=====================

Primary Exceptions
------------------

- `ValidationException`: invalid input shape or unsupported values.
- `EmptyPoolException`: method requires items/users but pool is empty.
- `DrawExhaustedException`: weighted/pick flow could not produce a valid draw.
- `DrawException`: generic draw exception base type.

Validation Patterns
-------------------

Common validation rules enforced by handlers:

- required `method` string
- required `items` for all methods except `campaign.batch`
- required `candidates` for `grand` and all campaign methods
- positive/valid weights where weighted draws apply
- method-specific required keys in item definitions

Partial Results vs Exceptions
-----------------------------

Not all under-fulfillment is an exception.

Methods like `grand` and campaign flows can return partial success with:

- `meta.fulfilled = false`
- non-null `meta.partialReason`
- positive `meta.unfilledCount`

Troubleshooting Checklist
-------------------------

1. Confirm `method` is one of the supported method names.
2. Confirm `items` shape matches that method.
3. Confirm `candidates` or `sourceFile` is present where required.
4. Check `meta.partialReason` when response is not fulfilled.
5. For reproducibility issues, verify RNG mode and seeds.
