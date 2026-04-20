<?php

use Infocyph\Draw\Audit\AuditTrail;
use Infocyph\Draw\Draw;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Random\SeededRandomGenerator;
use Infocyph\Draw\State\MemoryCachePool;

test('grand fully fills from remaining pool even with retryCount set to one', function () {
    $draw = new Draw(new SeededRandomGenerator(701));
    $result = $draw->execute([
        'method' => 'grand',
        'items' => ['item_a' => 2, 'item_b' => 1],
        'candidates' => ['u1', 'u2', 'u3'],
        'options' => ['retryCount' => 1],
    ]);

    $picked = array_map(fn ($entry) => $entry['candidateId'], $result['entries']);
    expect($picked)
        ->toHaveCount(3)
        ->and(count($picked))->toBe(count(array_unique($picked)))
        ->and($result['meta']['fulfilled'])->toBeTrue();
});

test('grand exposes explicit partial fulfillment metadata', function () {
    $draw = new Draw(new SeededRandomGenerator(702));
    $result = $draw->execute([
        'method' => 'grand',
        'items' => ['item_a' => 3],
        'candidates' => ['u1', 'u2'],
    ]);

    expect($result['meta']['fulfilled'])->toBeFalse()
        ->and($result['meta']['partialReason'])->toBe('insufficient_unique_candidates')
        ->and($result['meta']['unfilledCount'])->toBe(1);
});

test('requested count stays zero when zero slots are requested', function () {
    $draw = new Draw(new SeededRandomGenerator(7021));
    $grand = $draw->execute([
        'method' => 'grand',
        'items' => ['item_a' => 0],
        'candidates' => ['u1', 'u2'],
    ]);

    $campaign = $draw->execute([
        'method' => 'campaign.run',
        'items' => ['item_a' => ['count' => 0]],
        'candidates' => ['u1', 'u2'],
    ]);

    expect($grand['meta']['requestedCount'])->toBe(0)
        ->and($grand['meta']['returnedCount'])->toBe(0)
        ->and($grand['meta']['fulfilled'])->toBeTrue()
        ->and($campaign['meta']['requestedCount'])->toBe(0)
        ->and($campaign['meta']['returnedCount'])->toBe(0)
        ->and($campaign['meta']['fulfilled'])->toBeTrue();
});

test('campaign uses weighted slot scheduling and eligible-pool picking', function () {
    $draw = new Draw(new SeededRandomGenerator(703));
    $result = $draw->execute([
        'method' => 'campaign.run',
        'items' => [
            'high' => ['count' => 1, 'weight' => 1000],
            'low' => ['count' => 1, 'weight' => 0],
        ],
        'candidates' => ['u1', 'u2'],
        'options' => [
            'rules' => ['perUserCap' => 1],
            'retryLimit' => 1,
            'seed' => 12,
        ],
    ]);

    expect($result['entries'])->toHaveCount(2)
        ->and($result['meta']['fulfilled'])->toBeTrue()
        ->and($result['raw']['slotPlan'][0]['itemId'])->toBe('high');
});

test('campaign batch accepts requests without top-level items', function () {
    $draw = new Draw(new SeededRandomGenerator(704));
    $result = $draw->execute([
        'method' => 'campaign.batch',
        'candidates' => ['u1', 'u2', 'u3'],
        'options' => [
            'phases' => [
                ['name' => 'phase_1', 'items' => ['a' => ['count' => 1]]],
                ['name' => 'phase_2', 'items' => ['b' => ['count' => 1]]],
            ],
            'rules' => ['perUserCap' => 1],
        ],
    ]);

    expect($result['method'])->toBe('campaign.batch')
        ->and($result['entries'])->toHaveCount(2);
});

test('campaign accepts a psr-6 cache pool', function () {
    $draw = new Draw(new SeededRandomGenerator(7041));
    $result = $draw->execute([
        'method' => 'campaign.run',
        'items' => ['gift' => ['count' => 1]],
        'candidates' => ['u1', 'u2'],
        'options' => [
            'cachePool' => new MemoryCachePool(),
            'rules' => ['perUserCap' => 1],
        ],
    ]);

    expect($result['method'])->toBe('campaign.run')
        ->and($result['entries'])->toHaveCount(1)
        ->and($result['meta']['fulfilled'])->toBeTrue();
});

test('rangeWeighted returns integer values for integer bounds', function () {
    $draw = new Draw(new SeededRandomGenerator(705));
    $result = $draw->execute([
        'method' => 'rangeWeighted',
        'items' => [
            ['name' => 'int_range', 'min' => 1, 'max' => 5, 'weight' => 1],
        ],
    ]);

    expect($result['entries'][0]['value'])->toBeInt();
});

test('roundRobin handles associative item arrays safely', function () {
    $draw = new Draw(new SeededRandomGenerator(7051));
    $result = $draw->execute([
        'method' => 'roundRobin',
        'items' => [
            'alpha' => ['name' => 'a'],
            'beta' => ['name' => 'b'],
        ],
        'options' => ['count' => 2],
    ]);

    expect(array_column($result['entries'], 'value'))->toBe(['a', 'b']);
});

test('rangeWeighted rejects non-numeric bounds with validation error', function () {
    $draw = new Draw(new SeededRandomGenerator(706));

    expect(fn () => $draw->execute([
        'method' => 'rangeWeighted',
        'items' => [
            ['name' => 'broken', 'min' => 'a', 'max' => 'b', 'weight' => 1],
        ],
    ]))->toThrow(ValidationException::class);
});

test('lucky weighted amount maps support decimal weights', function () {
    $draw = new Draw(new SeededRandomGenerator(707));
    $counts = [1 => 0, 2 => 0, 3 => 0];

    for ($i = 0; $i < 200; $i++) {
        $result = $draw->execute([
            'method' => 'lucky',
            'items' => [[
                'item' => 'gift',
                'chances' => 1,
                'amounts' => ['1' => 0.2, '2' => 0.3, '3' => 0.5],
                'amountMode' => 'weighted',
            ]],
        ]);
        $counts[$result['entries'][0]['value']]++;
    }

    expect($counts[1])->toBeGreaterThan(0)
        ->and($counts[2])->toBeGreaterThan(0)
        ->and($counts[3])->toBeGreaterThan(0);
});

test('lucky fails fast when amountMode does not match payload', function () {
    $draw = new Draw(new SeededRandomGenerator(708));

    expect(fn () => $draw->execute([
        'method' => 'lucky',
        'items' => [[
            'item' => 'gift',
            'chances' => 10,
            'amounts' => [1, 2, 3],
            'amountMode' => 'range',
        ]],
    ]))->toThrow(ValidationException::class);
});

test('audit trail supports cryptographic verify flow', function () {
    $configuration = ['feature' => 'campaign', 'items' => ['a' => ['count' => 1]]];
    $result = ['a' => ['u1']];
    $seedFingerprint = 'seed:abc';
    $secret = 'secret-key';
    $audit = AuditTrail::create($configuration, $result, $seedFingerprint, $secret);

    expect($audit['signatureAlgorithm'])->toBe('hmac-sha256')
        ->and(AuditTrail::verify($audit, $configuration, $result, $seedFingerprint, $secret))->toBeTrue()
        ->and(AuditTrail::verify($audit, $configuration, ['a' => ['u2']], $seedFingerprint, $secret))->toBeFalse();
});

test('draw request fingerprint is stable for equivalent payloads', function () {
    $requestA = [
        'method' => 'lucky',
        'items' => [['item' => 'gift', 'chances' => 1, 'amounts' => [1]]],
        'options' => ['count' => 1],
    ];
    $requestB = [
        'options' => ['count' => 1],
        'items' => [['amounts' => [1], 'chances' => 1, 'item' => 'gift']],
        'method' => 'lucky',
    ];

    expect(Draw::requestFingerprint($requestA))
        ->toBe(Draw::requestFingerprint($requestB));
});
