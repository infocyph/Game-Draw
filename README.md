# Game Draw (v4)

[![Security & Standards](https://github.com/infocyph/Game-Draw/actions/workflows/build.yml/badge.svg)](https://github.com/infocyph/Game-Draw/actions/workflows/build.yml)
[![Documentation](https://img.shields.io/badge/Documentation-Read%20the%20Docs-blue?logo=readthedocs&logoColor=white)](https://docs.infocyph.com/projects/Game-Draw/)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/Game-Draw)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/Game-Draw)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

Unified PHP draw engine with a single request/response contract for item, user, and campaign draw methods.

## Install

Requirements:

- PHP 8.4+
- `ext-bcmath`

```bash
composer require infocyph/game-draw
```

## Quick Example

```php
use Infocyph\Draw\Draw;

$draw = new Draw();

$result = $draw->execute([
    'method' => 'lucky',
    'items' => [
        ['item' => 'gift_a', 'chances' => 10, 'amountMode' => 'list', 'amounts' => [1, 2]],
        ['item' => 'gift_b', 'chances' => 20, 'amountMode' => 'weighted', 'amounts' => ['5' => 0.25, '10' => 0.75]],
    ],
    'options' => ['count' => 2],
]);
```

## Supported Methods

- `lucky`
- `grand`
- `probability`, `elimination`, `weightedElimination`, `roundRobin`, `cumulative`
- `batched`, `timeBased`, `weightedBatch`, `sequential`, `rangeWeighted`
- `campaign.run`, `campaign.batch`, `campaign.simulate`

## Documentation

Full documentation is in the `docs/` folder (Read the Docs format):

- [Overview](docs/overview.rst)
- [Request & Response Contract](docs/request-response.rst)
- [Method Guides](docs/methods/index.rst)
- [Rules and State](docs/rules-and-state.rst)
- [Audit and Reproducibility](docs/audit-and-reproducibility.rst)
- [Randomness Modes](docs/randomness.rst)
- [Development](docs/development.rst)

Published docs: https://docs.infocyph.com/projects/Game-Draw/
