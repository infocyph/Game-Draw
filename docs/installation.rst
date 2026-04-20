Installation
============

Requirements
------------

- PHP 8.4+
- `ext-bcmath`

Package dependencies include PSR-6 cache interfaces (`psr/cache`) for campaign state handling.

Install
-------

.. code-block:: bash

   composer require infocyph/game-draw

Import
------

.. code-block:: php

   <?php
   use Infocyph\Draw\Draw;

   $draw = new Draw();

Next Steps
----------

- :doc:`quickstart`
- :doc:`request-response`
- :doc:`methods/index`
- :doc:`psr6-cache`
