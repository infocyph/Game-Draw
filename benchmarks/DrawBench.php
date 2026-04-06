<?php

declare(strict_types=1);

use Infocyph\Draw\Draw;
use Infocyph\Draw\Random\SeededRandomGenerator;

$autoload = __DIR__.'/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Infocyph\\Draw\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path = __DIR__.'/../src/'.str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php';
        is_file($path) && require_once $path;
    });
}

$options = getopt('', ['json', 'iterations::', 'fail-on-regression']);
$iterations = max(100, (int)($options['iterations'] ?? 2500));

$metrics = [];
$metrics['lucky_ms'] = bench(function () use ($iterations): void {
    $draw = new Draw(new SeededRandomGenerator(101));

    for ($i = 0; $i < $iterations; $i++) {
        $draw->execute([
            'method' => 'lucky',
            'items' => [
                ['item' => 'a', 'chances' => 50, 'amounts' => [1, 2, 3]],
                ['item' => 'b', 'chances' => 25, 'amounts' => [1, 2, 3]],
                ['item' => 'c', 'chances' => 25, 'amounts' => [1, 2, 3]],
            ],
        ]);
    }
});

$metrics['flexible_ms'] = bench(function () use ($iterations): void {
    $draw = new Draw(new SeededRandomGenerator(102));

    for ($i = 0; $i < $iterations; $i++) {
        $draw->execute([
            'method' => 'probability',
            'items' => [
                ['name' => 'a', 'weight' => 0.6],
                ['name' => 'b', 'weight' => 0.3],
                ['name' => 'c', 'weight' => 0.1],
            ],
        ]);
    }
});

$metrics['grand_ms'] = bench(function () use ($iterations): void {
    $draw = new Draw(new SeededRandomGenerator(103));
    $users = array_map(static fn (int $i): string => 'u'.$i, range(1, 2000));

    for ($i = 0; $i < (int)($iterations / 25); $i++) {
        $draw->execute([
            'method' => 'grand',
            'items' => ['a' => 50, 'b' => 50, 'c' => 50],
            'candidates' => $users,
            'options' => ['retryCount' => 300],
        ]);
    }
});

$metrics['campaign_ms'] = bench(function () use ($iterations): void {
    $draw = new Draw(new SeededRandomGenerator(104));
    $users = array_map(static fn (int $i): string => 'u'.$i, range(1, 500));

    for ($i = 0; $i < (int)($iterations / 50); $i++) {
        $draw->execute([
            'method' => 'campaign.run',
            'items' => [
                'gold' => ['count' => 20, 'group' => 'g1'],
                'silver' => ['count' => 20, 'group' => 'g2'],
            ],
            'candidates' => $users,
            'options' => [
                'rules' => ['perUserCap' => 1],
                'retryLimit' => 200,
            ],
        ]);
    }
});

$baselinePath = __DIR__.'/baseline.json';
$baseline = is_file($baselinePath)
    ? json_decode((string)file_get_contents($baselinePath), true)
    : [];
$regressions = [];

foreach ($baseline as $metric => $maxMs) {
    if (isset($metrics[$metric]) && $metrics[$metric] > (float)$maxMs) {
        $regressions[$metric] = [
            'actual' => $metrics[$metric],
            'threshold' => (float)$maxMs,
        ];
    }
}

$payload = [
    'iterations' => $iterations,
    'metrics_ms' => $metrics,
    'regressions' => $regressions,
];

if (array_key_exists('json', $options)) {
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
} else {
    fwrite(STDOUT, "Performance benchmark (ms)\n");
    foreach ($metrics as $name => $value) {
        fwrite(STDOUT, str_pad($name, 16, ' ', STR_PAD_RIGHT).': '.number_format($value, 2).PHP_EOL);
    }
    if (!empty($regressions)) {
        fwrite(STDOUT, "Regressions detected:\n");
        foreach ($regressions as $name => $data) {
            fwrite(STDOUT, "{$name}: {$data['actual']} > {$data['threshold']}".PHP_EOL);
        }
    }
}

if (array_key_exists('fail-on-regression', $options) && !empty($regressions)) {
    exit(1);
}

exit(0);

function bench(callable $task): float
{
    $start = hrtime(true);
    $task();
    $end = hrtime(true);
    return round(($end - $start) / 1_000_000, 3);
}
