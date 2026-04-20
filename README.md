# Game Draw (v4)

[![Security & Standards](https://github.com/infocyph/Game-Draw/actions/workflows/build.yml/badge.svg)](https://github.com/infocyph/Game-Draw/actions/workflows/build.yml)
[![Documentation](https://img.shields.io/badge/Documentation-Game-Draw-blue?logo=readthedocs&logoColor=white)](https://docs.infocyph.com/projects/Game-Draw/)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/Game-Draw?color=green&link=https%3A%2F%2Fpackagist.org%2Fpackages%2Finfocyph%2FGame-Draw)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/Game-Draw)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/infocyph/Game-Draw/php)
![GitHub Code Size](https://img.shields.io/github/languages/code-size/infocyph/Game-Draw)

Unified PHP draw engine with a single request/response contract for item, user, and campaign draw methods.

Campaign state uses PSR-6 (`Psr\Cache\CacheItemPoolInterface`) via `options.cachePool`.

## Install

Requirements:

- PHP 8.4+
- `ext-bcmath`

```bash
composer require infocyph/game-draw
```

## Quick Example

```php
<?php
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

Published docs: https://docs.infocyph.com/projects/Game-Draw/
