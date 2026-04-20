Development
===========

Quality Gates
-------------

Run individually:

- `composer test:syntax`
- `composer test:code`
- `composer test:lint`
- `composer test:sniff`
- `composer test:static`
- `composer test:security`
- `composer test:refactor`

Run complete suite:

- `composer test:all`

Formatting and Refactoring
--------------------------

- `composer process:lint`
- `composer process:sniff:fix`
- `composer process:refactor`
- `composer process:all`

Benchmarking
------------

- `composer bench:run`
- `composer bench:quick`
- `composer bench:chart`

Documentation Build
-------------------

Local HTML build:

.. code-block:: bash

   python3 -m sphinx -b html docs docs/_build/html

Read the Docs uses:

- `docs/conf.py`
- `docs/requirements.txt`
- `.readthedocs.yaml`

Contribution Checklist
----------------------

1. Add/adjust tests for behavior changes.
2. Run static analysis and sniffing.
3. Update docs pages affected by API/behavior changes.
4. Keep examples executable and aligned with current request/response contracts.
