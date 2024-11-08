<?php


use Infocyph\Draw\GrandDraw;

$items = [
    'product_001' => 5,
    'product_002' => 3,
    'product_003' => 2,
];
$csvFilePath = __DIR__ . '/sample.csv';

test('can set user list file path', function () use ($csvFilePath) {
    $draw = new GrandDraw();
    $draw->setUserListFilePath($csvFilePath);

    expect($draw)->toBeInstanceOf(GrandDraw::class);
});

test('can set items for the draw', function () use ($items) {
    $draw = new GrandDraw();
    $draw->setItems($items);

    expect($draw)->toBeInstanceOf(GrandDraw::class);
});

test('can retrieve winners for each item', function () use ($items, $csvFilePath) {
    $draw = new GrandDraw();
    $draw
        ->setItems($items)
        ->setUserListFilePath($csvFilePath);

    $winners = $draw->getWinners();
    expect($winners)
        ->toBeArray()
        ->and($winners)->toHaveKeys(['product_001', 'product_002', 'product_003'])
        ->and($winners['product_001'])->toHaveCount(5)
        ->and($winners['product_002'])->toHaveCount(3)
        ->and($winners['product_003'])->toHaveLineCountLessThan(3);
});

test('ensures no duplicate winners across multiple draws', function () use ($items, $csvFilePath) {
    $draw = new GrandDraw();
    $draw
        ->setItems($items)
        ->setUserListFilePath($csvFilePath);

    $winners = $draw->getWinners();

    $flattenedWinners = array_merge(...array_values($winners));
    $uniqueWinners = array_unique($flattenedWinners);

    expect($flattenedWinners)->toHaveCount(count($uniqueWinners));
});

test('draw method respects retry count limit', function () use ($csvFilePath) {
    $draw = new GrandDraw();
    $draw
        ->setItems(['product_004' => 10])
        ->setUserListFilePath($csvFilePath);

    // Using a low retry count to simulate reaching the retry limit
    $winners = $draw->getWinners(1);

    expect(count($winners['product_004']))->toBeLessThanOrEqual(10);
});

