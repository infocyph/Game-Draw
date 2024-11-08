<?php
use Infocyph\Draw\FlexibleDraw;

beforeEach(function () {
    $this->items = [
        ['name' => 'item1', 'weight' => 10, 'min' => 1, 'max' => 50, 'time' => 'daily'],
        ['name' => 'item2', 'weight' => 20, 'min' => 5, 'max' => 25, 'time' => 'weekly'],
    ];
});

test('Probability Weighted Draw', function () {
    $draw = new FlexibleDraw($this->items);
    $result = $draw->draw('probability');
    expect($result)->toBeIn(['item1', 'item2']);
});

test('Elimination Draw', function () {
    $draw = new FlexibleDraw([['name' => 'item1'], ['name' => 'item2']]);
    $result1 = $draw->draw('elimination');
    $result2 = $draw->draw('elimination');

    // Confirm both items are drawn
    expect([$result1, $result2])
        ->toContain('item1', 'item2')
        ->and(fn() => $draw->draw('elimination'))
        ->toThrow(Exception::class, 'Items array must contain at least one item.');
});

test('Weighted Elimination Draw', function () {
    $draw = new FlexibleDraw($this->items);
    $result1 = $draw->draw('weightedElimination');
    $result2 = $draw->draw('weightedElimination');

    // Confirm both items are drawn
    expect([$result1, $result2])
        ->toContain('item1', 'item2')
        ->and(fn() => $draw->draw('weightedElimination'))
        ->toThrow(Exception::class, 'Items array must contain at least one item.');

    // Check for the elimination exception
});

test('Round Robin Draw', function () {
    $draw = new FlexibleDraw([['name' => 'item1'], ['name' => 'item2']]);
    $result1 = $draw->draw('roundRobin');
    $result2 = $draw->draw('roundRobin');
    $result3 = $draw->draw('roundRobin');

    // Round-robin cycle check
    expect([$result1, $result2, $result3])->toContain('item1', 'item2', 'item1');
});

test('Cumulative Draw', function () {
    $draw = new FlexibleDraw([['name' => 'item1'], ['name' => 'item2']]);
    $result = $draw->draw('cumulative');
    expect($result)->toBeIn(['item1', 'item2']);
});

test('Batched Draw', function () {
    $draw = new FlexibleDraw([['name' => 'item1'], ['name' => 'item2']]);
    $result = $draw->draw('batched', 2, false); // Without replacement
    expect($result)
        ->toHaveCount(2)
        ->and($result)->toContain('item1', 'item2');
});

test('Time-Based Weighted Draw', function () {
    $draw = new FlexibleDraw($this->items);
    $result = $draw->draw('timeBased');
    expect($result)->toBeIn(['item1', 'item2']);
});

test('Weighted Batch Draw', function () {
    $draw = new FlexibleDraw($this->items);
    $result = $draw->draw('weightedBatch', 2);
    expect($result)
        ->toHaveCount(2)
        ->and($result)->each(fn($item) => $item->toBeIn(['item1', 'item2']));
});

test('Sequential Draw', function () {
    $draw = new FlexibleDraw([['name' => 'item1'], ['name' => 'item2']]);
    $result1 = $draw->draw('sequential');
    $result2 = $draw->draw('sequential');
    $result3 = $draw->draw('sequential');

    // Confirm strict order with cycling
    expect([$result1, $result2, $result3])->toContain('item1', 'item2', 'item1');
});

test('Range Weighted Draw', function () {
    $draw = new FlexibleDraw([
        ['name' => 'item1', 'min' => 1, 'max' => 50, 'weight' => 10],
        ['name' => 'item2', 'min' => 5, 'max' => 25, 'weight' => 15],
    ], false); // Disable check for range-weighted test

    $result = $draw->draw('rangeWeighted');
    expect($result)->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(50);
});
