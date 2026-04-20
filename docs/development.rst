Development
===========

Testing Scripts
---------------

The package ships Composer scripts for quality gates:

- `composer test:syntax`
- `composer test:code`
- `composer test:lint`
- `composer test:sniff`
- `composer test:static`
- `composer test:security`
- `composer test:refactor`
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

Docs Build (local)
------------------

From repository root:

.. code-block:: bash

   python -m sphinx -b html docs docs/_build/html

Read the Docs uses `docs/conf.py` and `docs/requirements.txt`.
