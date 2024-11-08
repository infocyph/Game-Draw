# Game Draw

[![Codacy Badge](https://app.codacy.com/project/badge/Grade/1d04992efafe4aeca3c3b14be7476a50)](https://app.codacy.com/gh/infocyph/Game-Draw/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
![Libraries.io dependency status for GitHub repo](https://img.shields.io/librariesio/github/infocyph/game-draw)
![Packagist Downloads](https://img.shields.io/packagist/dt/infocyph/game-draw)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
![Packagist Version](https://img.shields.io/packagist/v/infocyph/game-draw)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/infocyph/game-draw)
![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/infocyph/game-draw)

The **Game Draw** library provides flexible and varied methods for selecting winners based on different types of draws,
**Lucky Draw**, **Grand Draw**, and a customizable **Flexible Draw** to meet a range of requirements.

> Please don't use this to generate things/prizes with People's hard-earned money. It is intended to make things fun
> with bonus gifts only.

## Prerequisites

- **Language:** PHP 8+
- **PHP Extension:** BCMath (may need to install manually)

## Installation

```
composer require infocyph/game-draw
```

## Overview

### 1. LuckyDraw

The `LuckyDraw` class allows for winner selection based on item chances and weighted amounts.

#### Input Data

```php
$products = [
    [
        'item' => 'product_000_NoLuck', // Item code or Identifier
        'chances' => '100000',          // Item Chances
        'amounts'=> [ 1 ]              // Item Amounts
    ],
    [
        'item' => 'product_001',
        'chances' => '1000',
        'amounts' => '1.5,10.00001,1'    // Weighted CSV formatted range (min,max,bias)
    ],
    [
        'item' => 'product_002',
        'chances' => '500.001',         // Fraction Allowed
        'amounts' => [
            1 => 100,                   // Amount chances
            5 => 50,                    // Format: Amount => Chances
            10 => 10.002,               // Fraction allowed
        ]
    ],
    [
        'item' => 'product_003',
        'chances' => '100',
        'amounts' => [
            1 => 100,
            5 => 50,
            10 => 10,
            20 => 5, 
        ]
    ],
    [
        'item' => 'product_004',
        'chances' => '1',
        'amounts' => [ 10, 15, 30, 50 ] // Amounts without probability
    ],
]
```

- **item**: Provide your item's unique identifier

- **chances**: Weight of item (Float/Int).
    - It will be compared along all the items in array.
    - The higher the chances the greater the chances of getting the item.
    - In case of active inventory you can pass available item stock here

- **amounts**: String or Array of Item amount (Float/Int). It can be any like:
    - (array) Single Positive value, i.e. [ 1 ] or Multiple Positive value (randomly picked), i.e. [ 1, 2, 3, 5]
    - (array) Weighted amount, i.e.
        ```php
        [
            5 => 100,
            15 => 50,
            50 => 10,
            80 => 5.001
        ]
        ```
    - (String) Weighted CSV formatted range (min,max,bias) ```'1,10.00001,0.001'```
        - Only 3 members allowed in CSV format **min,max,bias**
        - Max should be greater than or equal to min, bias should be greater than 0
        - The higher the bias, the more the chance to pick the lowest amount

#### Usage

```php
$luckyDraw = new AbmmHasan\Draw\LuckyDraw($products);
$result = $luckyDraw->pick();
```

Example Output:

```php
[
    'item' => 'product_000_NoLuck',
    'amount' => 1
]
```

> Inventory Solutions: Available stock should be passed (after subtracting used amount from stock amount) in chances
> properly.

### 2. GrandDraw

The `GrandDraw` class is designed for large draws where items and user entries are managed in bulk.

#### Input Data

```php
$prizes = 
[
    'product_001'=>50,        // Item Code/Identifier => Amount of the item
    'product_002'=>5,
    'product_003'=>3,
    'product_004'=>2,
    'product_005'=>1
];
```

- **item**: Provide your item's unique identifier

- **amounts**: Amount of gift. It must be a positive integer value.

User entries are loaded using a CSV file:

```csv
"usr47671",
"usr57665",
"usr47671",
.....
```

#### Usage

```php
$grandDraw = new AbmmHasan\Draw\GrandDraw();
$grandDraw->setItems($prizes)
          ->setUserListFilePath('./Sample1000.csv');
$winners = $grandDraw->getWinners();
```

Example Output:

```php
[
    'product_001' => ['usr47671', 'usr57665', 'usr92400'],
    'product_002' => ['usr50344', 'usr60450', 'usr62662']
]
```

### 3. FlexibleDraw

The `FlexibleDraw` class provides a versatile approach to selection, offering various types of draw methods, including
probability-based, elimination, round-robin, time-based, and more. This flexibility allows for customized and dynamic
draws, suitable for a range of applications.

#### Supported Draw Types

- **Probability Draw**: Selects items based on assigned probability weights.
- **Elimination Draw**: Items are drawn and removed from the selection pool, ensuring no repeats.
- **Weighted Elimination Draw**: Similar to Elimination Draw, but selections are weighted.
- **Round Robin Draw**: Items are selected in a round-robin sequence, cycling through each item.
- **Cumulative Draw**: Draws items based on cumulative scores, with higher scores increasing selection probability.
- **Batched Draw**: Draws a specified number of items in one call, with or without replacement.
- **Time-Based Weighted Draw**: Selects items based on weight and a specified time interval, e.g., daily or weekly.
- **Weighted Batch Draw**: Draws a batch of items using weighted probabilities.
- **Sequential Draw**: Draws items in a fixed sequence, restarting once all items are drawn.
- **Range Weighted Draw**: Selects a random number within a defined range, weighted by probability.

#### FlexibleDraw Options and Usage Examples

Below are usage examples for each draw type. Define your items array based on the draw type requirements.

1. **Probability Draw**:
    - Selects items based on weighted probabilities. Higher-weight items have a greater likelihood of selection.
   ```php
   $items = [
       ['name' => 'item1', 'weight' => 10],
       ['name' => 'item2', 'weight' => 20],
   ];
   ```

2. **Elimination Draw**:
    - Items are drawn once, removed from the pool after selection.
   ```php
   $items = [
       ['name' => 'item1'],
       ['name' => 'item2'],
   ];
   ```

3. **Weighted Elimination Draw**:
    - Similar to elimination, but uses weights to influence item selection.
   ```php
   $items = [
       ['name' => 'item1', 'weight' => 10],
       ['name' => 'item2', 'weight' => 20],
   ];
   ```

4. **Round Robin Draw**:
    - Cycles through items in a round-robin sequence.
   ```php
   $items = [
       ['name' => 'item1'],
       ['name' => 'item2'],
   ];
   ```

5. **Cumulative Draw**:
    - Draws items based on cumulative scores, with selection probabilities adjusted over time.
   ```php
   $items = [
       ['name' => 'item1'],
       ['name' => 'item2'],
   ];
   ```

6. **Batched Draw**:
    - Draws a batch of items in one call, with optional replacement.
   ```php
   $items = [
       ['name' => 'item1'],
       ['name' => 'item2'],
   ];
   ```

7. **Time-Based Weighted Draw**:
    - Selects items based on weight and a specified time interval (e.g., daily or weekly).
   ```php
   $items = [
       ['name' => 'item1', 'weight' => 10, 'time' => 'daily'],
       ['name' => 'item2', 'weight' => 20, 'time' => 'weekly'],
   ];
   ```

8. **Weighted Batch Draw**:
    - Draws a batch of items using weighted probabilities, balancing selections by item weight.
   ```php
   $items = [
       ['name' => 'item1', 'weight' => 10],
       ['name' => 'item2', 'weight' => 20],
   ];
   ```

9. **Sequential Draw**:
    - Selects items in a predefined sequence.
   ```php
   $items = [
       ['name' => 'item1'],
       ['name' => 'item2'],
   ];
   ```

10. **Range Weighted Draw**:
    - Specifies a range for each item using `min`, `max`, and `weight`.
    ```php
    $items = [
        ['name' => 'item1', 'min' => 1, 'max' => 50, 'weight' => 10],
        ['name' => 'item2', 'min' => 5, 'max' => 25, 'weight' => 15],
    ];
    ```

#### Usage

To use the `FlexibleDraw` class, create an instance with your items array and specify the draw type in the `draw`
method:

```php
$flexibleDraw = new FlexibleDraw($items);
$result = $flexibleDraw->draw('drawType'); // Replace 'drawType' with the desired draw type, e.g., 'probability'
```

### Example Output

The output depends on the draw type and item configuration. For example, a probability draw with weights might yield:

```php
[
    'item' => 'item2',
    'weight' => 20,
]
```

### FlexibleDraw Configuration Summary

The `FlexibleDraw` class is highly adaptable, supporting various selection methods and configurations such as `weight`,
`group`, `min`, `max`, and `time`. Ideal for applications needing nuanced and dynamic draws, it is more versatile than
simpler draw mechanisms but optimized for manageable volumes.

## Support

Having trouble? Create an issue!
