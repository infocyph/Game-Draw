<?php

use Infocyph\Draw\Random\SeededRandomGenerator;
use Infocyph\Draw\Draw;

test('unified draw returns same response shape for lucky single and multi', function () {
    $draw = new Draw(new SeededRandomGenerator(100));

    $single = $draw->execute([
        'method' => 'lucky',
        'items' => [
            ['item' => 'a', 'chances' => 10, 'amounts' => [1]],
            ['item' => 'b', 'chances' => 20, 'amounts' => [2]],
        ],
        'options' => ['count' => 1],
    ]);

    $multi = $draw->execute([
        'method' => 'lucky',
        'items' => [
            ['item' => 'a', 'chances' => 10, 'amounts' => [1]],
            ['item' => 'b', 'chances' => 20, 'amounts' => [2]],
        ],
        'options' => ['count' => 3],
    ]);

    expect($single)->toHaveKeys(['method', 'entries', 'raw', 'meta'])
        ->and($single['entries'])->toHaveCount(1)
        ->and($single['meta']['mode'])->toBe('single')
        ->and($single['entries'][0])->toHaveKeys(['itemId', 'candidateId', 'value', 'meta'])
        ->and($multi['entries'])->toHaveCount(3)
        ->and($multi['meta']['mode'])->toBe('multi');
});

test('unified draw returns same response shape for grand draw', function () {
    $draw = new Draw(new SeededRandomGenerator(101));
    $result = $draw->execute([
        'method' => 'grand',
        'items' => ['gift' => 2],
        'candidates' => ['u1', 'u2', 'u3'],
        'options' => ['retryCount' => 20],
    ]);

    expect($result)->toHaveKeys(['method', 'entries', 'raw', 'meta'])
        ->and($result['method'])->toBe('grand')
        ->and($result['entries'])->toHaveCount(2)
        ->and($result['entries'][0]['itemId'])->toBe('gift')
        ->and($result['entries'][0]['candidateId'])->not->toBeNull();
});

test('unified draw returns same response shape for flexible methods', function () {
    $draw = new Draw(new SeededRandomGenerator(102));
    $result = $draw->execute([
        'method' => 'probability',
        'items' => [
            ['name' => 'x', 'weight' => 0.25],
            ['name' => 'y', 'weight' => 0.75],
        ],
        'options' => ['count' => 2],
    ]);

    expect($result)->toHaveKeys(['method', 'entries', 'raw', 'meta'])
        ->and($result['method'])->toBe('probability')
        ->and($result['entries'])->toHaveCount(2)
        ->and($result['entries'][0])->toHaveKeys(['itemId', 'candidateId', 'value', 'meta']);
});

test('unified draw returns same response shape for campaign run and simulate', function () {
    $draw = new Draw(new SeededRandomGenerator(103));

    $run = $draw->execute([
        'method' => 'campaign.run',
        'items' => ['gold' => ['count' => 2]],
        'candidates' => ['u1', 'u2', 'u3'],
        'options' => ['rules' => ['perUserCap' => 1], 'retryLimit' => 20],
    ]);

    $simulate = $draw->execute([
        'method' => 'campaign.simulate',
        'items' => ['gold' => ['count' => 1]],
        'candidates' => ['u1', 'u2', 'u3'],
        'options' => ['iterations' => 5],
    ]);

    expect($run)->toHaveKeys(['method', 'entries', 'raw', 'meta'])
        ->and($run['entries'])->toHaveCount(2)
        ->and($run['entries'][0]['candidateId'])->not->toBeNull()
        ->and($simulate)->toHaveKeys(['method', 'entries', 'raw', 'meta'])
        ->and($simulate['method'])->toBe('campaign.simulate')
        ->and($simulate['entries'])->toHaveCount(3)
        ->and($simulate['entries'][0]['candidateId'])->not->toBeNull();
});
