# Game Draw (v4)

[![Security & Standards](https://github.com/infocyph/Game-Draw/actions/workflows/build.yml/badge.svg)](https://github.com/infocyph/Game-Draw/actions/workflows/build.yml)

[//]: # ([![Documentation]&#40;https://img.shields.io/badge/Documentation-Game-Draw-blue?logo=readthedocs&logoColor=white&#41;]&#40;https://docs.infocyph.com/projects/Game-Draw/&#41;)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/Game-Draw?color=green&link=https%3A%2F%2Fpackagist.org%2Fpackages%2Finfocyph%2FGame-Draw)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/Game-Draw)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/infocyph/Game-Draw/php)
![GitHub Code Size](https://img.shields.io/github/languages/code-size/infocyph/Game-Draw)

Unified draw engine with one request contract and one response contract for every draw method.

## Requirements

- PHP 8.4+
- BCMath extension

## Install

```bash
composer require infocyph/game-draw
```

## Unified API

Use `Infocyph\Draw\Draw` as the only public draw entrypoint.

### Request shape

```php
[
    'method' => '...',      // required
    'items' => [...],       // required
    'candidates' => [...],  // required for grand + campaign.*
    'sourceFile' => '...',  // optional alternative for grand
    'options' => [...],     // optional
]
```

### Response shape

```php
[
    'method' => '...',
    'entries' => [
        [
            'itemId' => ?string,
            'candidateId' => ?string,
            'value' => mixed,
            'meta' => array
        ]
    ],
    'raw' => mixed,
    'meta' => [
        'mode' => 'single|multi',
        'requestedCount' => int,
        'returnedCount' => int
    ]
]
```

Single-pick and multi-pick methods both return `entries[]`.

## Supported methods

- `lucky`
- `grand`
- `probability`
- `elimination`
- `weightedElimination`
- `roundRobin`
- `cumulative`
- `batched`
- `timeBased`
- `weightedBatch`
- `sequential`
- `rangeWeighted`
- `campaign.run`
- `campaign.batch`
- `campaign.simulate`

## Examples

```php
use Infocyph\Draw\Draw;

$draw = new Draw();
```

### lucky

```php
$result = $draw->execute([
    'method' => 'lucky',
    'items' => [
        ['item' => 'gift_a', 'chances' => 10, 'amounts' => [1]],
        ['item' => 'gift_b', 'chances' => 20, 'amounts' => [2]],
    ],
    'options' => ['count' => 2],
]);
```

### grand

```php
$result = $draw->execute([
    'method' => 'grand',
    'items' => ['gift_a' => 5, 'gift_b' => 2],
    'candidates' => ['u1', 'u2', 'u3', 'u4'],
    'options' => ['retryCount' => 50],
]);
```

### probability

```php
$result = $draw->execute([
    'method' => 'probability',
    'items' => [
        ['name' => 'item1', 'weight' => 0.2],
        ['name' => 'item2', 'weight' => 0.8],
    ],
    'options' => ['count' => 3],
]);
```

### elimination

```php
$result = $draw->execute([
    'method' => 'elimination',
    'items' => [
        ['name' => 'item1'],
        ['name' => 'item2'],
    ],
    'options' => ['count' => 2],
]);
```

### weightedElimination

```php
$result = $draw->execute([
    'method' => 'weightedElimination',
    'items' => [
        ['name' => 'item1', 'weight' => 10],
        ['name' => 'item2', 'weight' => 20],
    ],
    'options' => ['count' => 2],
]);
```

### roundRobin

```php
$result = $draw->execute([
    'method' => 'roundRobin',
    'items' => [
        ['name' => 'item1'],
        ['name' => 'item2'],
    ],
    'options' => ['count' => 3],
]);
```

### cumulative

```php
$result = $draw->execute([
    'method' => 'cumulative',
    'items' => [
        ['name' => 'item1'],
        ['name' => 'item2'],
    ],
    'options' => ['count' => 3],
]);
```

### batched

```php
$result = $draw->execute([
    'method' => 'batched',
    'items' => [
        ['name' => 'item1'],
        ['name' => 'item2'],
        ['name' => 'item3'],
    ],
    'options' => ['count' => 2, 'withReplacement' => false],
]);
```

### timeBased

```php
$result = $draw->execute([
    'method' => 'timeBased',
    'items' => [
        ['name' => 'item1', 'weight' => 10, 'time' => 'daily'],
        ['name' => 'item2', 'weight' => 20, 'time' => 'weekly'],
    ],
    'options' => ['count' => 2],
]);
```

### weightedBatch

```php
$result = $draw->execute([
    'method' => 'weightedBatch',
    'items' => [
        ['name' => 'item1', 'weight' => 10],
        ['name' => 'item2', 'weight' => 20],
    ],
    'options' => ['count' => 3],
]);
```

### sequential

```php
$result = $draw->execute([
    'method' => 'sequential',
    'items' => [
        ['name' => 'item1'],
        ['name' => 'item2'],
    ],
    'options' => ['count' => 3],
]);
```

### rangeWeighted

```php
$result = $draw->execute([
    'method' => 'rangeWeighted',
    'items' => [
        ['name' => 'item1', 'min' => 1, 'max' => 50, 'weight' => 10],
        ['name' => 'item2', 'min' => 5, 'max' => 25, 'weight' => 15],
    ],
    'options' => ['count' => 2],
]);
```

### campaign.run

```php
$result = $draw->execute([
    'method' => 'campaign.run',
    'items' => [
        'gold' => ['count' => 1, 'group' => 'premium'],
        'silver' => ['count' => 2, 'group' => 'basic'],
    ],
    'candidates' => ['u1', 'u2', 'u3', 'u4'],
    'options' => [
        'rules' => [
            'perUserCap' => 1,
            'perItemCap' => ['gold' => 1],
            'groupQuota' => ['premium' => 1, 'basic' => 2],
        ],
        'retryLimit' => 100,
        'withExplain' => true,
    ],
]);
```

### campaign.batch

```php
$result = $draw->execute([
    'method' => 'campaign.batch',
    'items' => ['bootstrap' => ['count' => 1]],
    'candidates' => ['u1', 'u2', 'u3', 'u4'],
    'options' => [
        'rules' => ['perUserCap' => 1],
        'phases' => [
            ['name' => 'phase_1', 'items' => ['item_a' => ['count' => 2]]],
            ['name' => 'phase_2', 'items' => ['item_b' => ['count' => 2]]],
        ],
        'retryLimit' => 100,
    ],
]);
```

### campaign.simulate

```php
$result = $draw->execute([
    'method' => 'campaign.simulate',
    'items' => ['gold' => ['count' => 1], 'silver' => ['count' => 2]],
    'candidates' => ['u1', 'u2', 'u3', 'u4'],
    'options' => ['iterations' => 1000, 'retryLimit' => 100],
]);
```
