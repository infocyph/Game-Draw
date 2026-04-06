<?php

use Infocyph\Draw\Random\SeededRandomGenerator;
use Infocyph\Draw\Draw;

test('property: lucky favors higher chance over many runs', function () {
    $draw = new Draw(new SeededRandomGenerator(111));
    $counts = ['low' => 0, 'high' => 0];

    for ($i = 0; $i < 400; $i++) {
        $result = $draw->execute([
            'method' => 'lucky',
            'items' => [
                ['item' => 'low', 'chances' => 10, 'amounts' => [1]],
                ['item' => 'high', 'chances' => 90, 'amounts' => [1]],
            ],
        ]);

        $counts[$result['entries'][0]['itemId']]++;
    }

    expect($counts['high'])->toBeGreaterThan($counts['low']);
});

test('property: grand never returns duplicate candidates across items', function () {
    $users = array_map(fn ($n) => 'user_'.$n, range(1, 60));

    for ($seed = 1; $seed <= 12; $seed++) {
        $draw = new Draw(new SeededRandomGenerator($seed));
        $result = $draw->execute([
            'method' => 'grand',
            'items' => ['a' => 8, 'b' => 8, 'c' => 8],
            'candidates' => $users,
            'options' => ['retryCount' => 80],
        ]);

        $picked = array_map(fn ($entry) => $entry['candidateId'], $result['entries']);
        expect(count($picked))->toBe(count(array_unique($picked)));
    }
});

test('property: probability favors higher weight over many runs', function () {
    $draw = new Draw(new SeededRandomGenerator(412));
    $counts = ['light' => 0, 'heavy' => 0];

    for ($i = 0; $i < 500; $i++) {
        $result = $draw->execute([
            'method' => 'probability',
            'items' => [
                ['name' => 'light', 'weight' => 0.2],
                ['name' => 'heavy', 'weight' => 0.8],
            ],
        ]);

        $counts[$result['entries'][0]['itemId']]++;
    }

    expect($counts['heavy'])->toBeGreaterThan($counts['light']);
});
