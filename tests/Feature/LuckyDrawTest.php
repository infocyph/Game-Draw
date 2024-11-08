<?php
use Infocyph\Draw\LuckyDraw;

beforeEach(function () {
    $this->items = [
        ['item' => 'item1', 'chances' => 10, 'amounts' => [5, 10, 15]],
        ['item' => 'item2', 'chances' => 20, 'amounts' => "5,15,2"],
        ['item' => 'item3', 'chances' => 15, 'amounts' => [20, 25]]
    ];
    $this->luckyDraw = new LuckyDraw($this->items);
});

test('check throws exception if items array is empty', function () {
    $draw = new LuckyDraw([]);
    $draw->pick();
})->throws(LengthException::class, 'Items array must contain at least one item.');

test('check throws exception if required keys are missing', function () {
    $invalidItems = [
        ['item' => 'item1', 'chances' => 10],
        ['chances' => 20, 'amounts' => [5, 10]]
    ];
    $draw = new LuckyDraw($invalidItems);
    $draw->pick();
})->throws(InvalidArgumentException::class, 'Item at index 0 is missing required keys: amounts');

test('pick method selects an item based on chances', function () {
    $result = $this->luckyDraw->pick();
    expect(['item1', 'item2', 'item3'])->toContain($result['item']);
});

test('pick method selects a valid amount based on the type', function () {
    $result = $this->luckyDraw->pick();
    $validAmounts = [5, 10, 15, 20, 25];

    // Check if the amount is in the list of valid fixed values
    if (in_array($result['amount'], $validAmounts, true)) {
        expect($validAmounts)->toContain($result['amount']);
    } else {
        // Otherwise, it should be within the range 5 to 15
        expect($result['amount'])->toBeGreaterThanOrEqual(5)
            ->toBeLessThanOrEqual(15);
    }
});

test('weightedAmountRange calculates a weighted random amount within range', function () {
    $amountRange = "5,10,2";
    $method = (new ReflectionClass(LuckyDraw::class))->getMethod('weightedAmountRange');
    $method->setAccessible(true);
    $result = $method->invoke($this->luckyDraw, $amountRange);

    expect($result)->toBeGreaterThanOrEqual(5)
        ->toBeLessThanOrEqual(10);
});

test('weightedAmountRange throws exception on invalid range input', function () {
    $amountRange = "5,6,0"; // Invalid bias
    $method = (new ReflectionClass(LuckyDraw::class))->getMethod('weightedAmountRange');
    $method->setAccessible(true);
    $method->invoke($this->luckyDraw, $amountRange);
})->throws(UnexpectedValueException::class, 'Bias should be greater than 0.');

test('draw method returns an item based on given weights', function () {
    $items = ['item1' => 10, 'item2' => 20, 'item3' => 15];
    $method = (new ReflectionClass(LuckyDraw::class))->getMethod('draw');
    $method->setAccessible(true);
    $result = $method->invoke($this->luckyDraw, $items);

    expect(['item1', 'item2', 'item3'])->toContain($result);
});

test('getFractionLength calculates correct fractional length', function () {
    $items = [1.234, 0.456];
    $method = (new ReflectionClass(LuckyDraw::class))->getMethod('getFractionLength');
    $method->setAccessible(true);
    $result = $method->invoke($this->luckyDraw, $items);

    expect($result)->toEqual(3);
});

test('isSequential method verifies if array keys are sequential', function () {
    $method = (new ReflectionClass(LuckyDraw::class))->getMethod('isSequential');
    $method->setAccessible(true);

    expect($method->invoke($this->luckyDraw, [1, 2, 3]))
        ->toBeTrue()
        ->and($method->invoke($this->luckyDraw, [1 => 'a', 2 => 'b']))->toBeFalse();
});
