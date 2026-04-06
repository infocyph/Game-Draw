<?php

use Infocyph\Draw\Draw;
use Infocyph\Draw\Random\SeededRandomGenerator;

dataset('item_methods', [
    'lucky' => [
        'method' => 'lucky',
        'items' => [
            ['item' => 'gift_a', 'chances' => 10, 'amounts' => [1, 2]],
            ['item' => 'gift_b', 'chances' => 20, 'amounts' => [3, 4]],
        ],
        'options' => ['count' => 3],
        'expectedCount' => 3,
    ],
    'probability' => [
        'method' => 'probability',
        'items' => [
            ['name' => 'item1', 'weight' => 10],
            ['name' => 'item2', 'weight' => 20],
        ],
        'options' => ['count' => 2],
        'expectedCount' => 2,
    ],
    'elimination' => [
        'method' => 'elimination',
        'items' => [
            ['name' => 'item1'],
            ['name' => 'item2'],
        ],
        'options' => ['count' => 2],
        'expectedCount' => 2,
    ],
    'weightedElimination' => [
        'method' => 'weightedElimination',
        'items' => [
            ['name' => 'item1', 'weight' => 5],
            ['name' => 'item2', 'weight' => 10],
        ],
        'options' => ['count' => 2],
        'expectedCount' => 2,
    ],
    'roundRobin' => [
        'method' => 'roundRobin',
        'items' => [
            ['name' => 'item1'],
            ['name' => 'item2'],
        ],
        'options' => ['count' => 3],
        'expectedCount' => 3,
    ],
    'cumulative' => [
        'method' => 'cumulative',
        'items' => [
            ['name' => 'item1'],
            ['name' => 'item2'],
        ],
        'options' => ['count' => 3],
        'expectedCount' => 3,
    ],
    'batched' => [
        'method' => 'batched',
        'items' => [
            ['name' => 'item1'],
            ['name' => 'item2'],
            ['name' => 'item3'],
        ],
        'options' => ['count' => 2, 'withReplacement' => false],
        'expectedCount' => 2,
    ],
    'timeBased' => [
        'method' => 'timeBased',
        'items' => [
            ['name' => 'item1', 'weight' => 10, 'time' => 'daily'],
            ['name' => 'item2', 'weight' => 20, 'time' => 'weekly'],
        ],
        'options' => ['count' => 2],
        'expectedCount' => 2,
    ],
    'weightedBatch' => [
        'method' => 'weightedBatch',
        'items' => [
            ['name' => 'item1', 'weight' => 10],
            ['name' => 'item2', 'weight' => 20],
        ],
        'options' => ['count' => 3],
        'expectedCount' => 3,
    ],
    'sequential' => [
        'method' => 'sequential',
        'items' => [
            ['name' => 'item1'],
            ['name' => 'item2'],
        ],
        'options' => ['count' => 3],
        'expectedCount' => 3,
    ],
    'rangeWeighted' => [
        'method' => 'rangeWeighted',
        'items' => [
            ['name' => 'item1', 'min' => 1, 'max' => 50, 'weight' => 10],
            ['name' => 'item2', 'min' => 5, 'max' => 25, 'weight' => 15],
        ],
        'options' => ['count' => 2],
        'expectedCount' => 2,
    ],
]);

test('covers every item draw method with unified shape', function (
    string $method,
    array $items,
    array $options,
    int $expectedCount,
) {
    $draw = new Draw(new SeededRandomGenerator(240));
    $result = $draw->execute([
        'method' => $method,
        'items' => $items,
        'options' => $options,
    ]);

    expect($result)->toHaveKeys(['method', 'entries', 'raw', 'meta'])
        ->and($result['method'])->toBe($method)
        ->and($result['entries'])->toHaveCount($expectedCount)
        ->and($result['meta']['returnedCount'])->toBe($expectedCount)
        ->and($result['entries'][0])->toHaveKeys(['itemId', 'candidateId', 'value', 'meta']);
})->with('item_methods');

test('covers grand draw with sourceFile input', function () {
    $tmpFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'draw-source-'.uniqid('', true).'.csv';
    file_put_contents($tmpFile, "u1\nu2\nu3\nu4\n", LOCK_EX);

    $draw = new Draw(new SeededRandomGenerator(241));
    $result = $draw->execute([
        'method' => 'grand',
        'items' => ['gift_1' => 2, 'gift_2' => 1],
        'sourceFile' => $tmpFile,
        'options' => ['retryCount' => 30],
    ]);

    expect($result)->toHaveKeys(['method', 'entries', 'raw', 'meta'])
        ->and($result['method'])->toBe('grand')
        ->and($result['entries'])->toHaveCount(3)
        ->and($result['entries'][0])->toHaveKeys(['itemId', 'candidateId', 'value', 'meta']);

    @unlink($tmpFile);
});

test('covers campaign.run, campaign.batch and campaign.simulate', function () {
    $draw = new Draw(new SeededRandomGenerator(242));

    $run = $draw->execute([
        'method' => 'campaign.run',
        'items' => [
            'gold' => ['count' => 1, 'group' => 'premium'],
            'silver' => ['count' => 2, 'group' => 'basic'],
        ],
        'candidates' => ['u1', 'u2', 'u3', 'u4'],
        'options' => [
            'rules' => [
                'perUserCap' => 1,
                'groupQuota' => ['premium' => 1, 'basic' => 2],
            ],
            'retryLimit' => 60,
        ],
    ]);

    $batch = $draw->execute([
        'method' => 'campaign.batch',
        'items' => ['bootstrap' => ['count' => 1]],
        'candidates' => ['u1', 'u2', 'u3', 'u4'],
        'options' => [
            'rules' => ['perUserCap' => 1],
            'phases' => [
                ['name' => 'phase_1', 'items' => ['a' => ['count' => 1, 'group' => 'g1']]],
                ['name' => 'phase_2', 'items' => ['b' => ['count' => 1, 'group' => 'g1']]],
            ],
        ],
    ]);

    $simulate = $draw->execute([
        'method' => 'campaign.simulate',
        'items' => ['gold' => ['count' => 1]],
        'candidates' => ['u1', 'u2', 'u3', 'u4'],
        'options' => ['iterations' => 6, 'retryLimit' => 30],
    ]);

    expect($run['method'])->toBe('campaign.run')
        ->and($run['entries'])->toHaveCount(3)
        ->and($batch['method'])->toBe('campaign.batch')
        ->and($batch['entries'])->toHaveCount(2)
        ->and($simulate['method'])->toBe('campaign.simulate')
        ->and($simulate['entries'])->toHaveCount(4);
});
