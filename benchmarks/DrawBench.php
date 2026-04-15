<?php

declare(strict_types=1);

namespace Infocyph\Draw\Benchmarks;

use Infocyph\Draw\Draw;
use Infocyph\Draw\Random\SeededRandomGenerator;
use PhpBench\Attributes as Bench;

#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class DrawBench
{
    #[Bench\Revs(12)]
    #[Bench\ParamProviders('provideCampaignRequests')]
    public function benchCampaignMethods(array $params): void
    {
        $draw = new Draw(new SeededRandomGenerator(103));
        $draw->execute($params['request']);
    }

    #[Bench\Revs(30)]
    #[Bench\ParamProviders('provideGrandRequests')]
    public function benchGrandMethod(array $params): void
    {
        $draw = new Draw(new SeededRandomGenerator(102));
        $draw->execute($params['request']);
    }
    #[Bench\Revs(100)]
    #[Bench\ParamProviders('provideItemRequests')]
    public function benchItemMethods(array $params): void
    {
        $draw = new Draw(new SeededRandomGenerator(101));
        $draw->execute($params['request']);
    }

    public function provideCampaignRequests(): iterable
    {
        $candidates = $this->buildCandidates(500);

        yield 'campaign.run' => [
            'request' => [
                'method' => 'campaign.run',
                'items' => [
                    'gold' => ['count' => 20, 'group' => 'g1'],
                    'silver' => ['count' => 20, 'group' => 'g2'],
                ],
                'candidates' => $candidates,
                'options' => [
                    'rules' => ['perUserCap' => 1],
                    'retryLimit' => 200,
                ],
            ],
        ];

        yield 'campaign.batch' => [
            'request' => [
                'method' => 'campaign.batch',
                'items' => ['bootstrap' => ['count' => 1]],
                'candidates' => $candidates,
                'options' => [
                    'rules' => ['perUserCap' => 1],
                    'phases' => [
                        ['name' => 'phase_1', 'items' => ['item_a' => ['count' => 8, 'group' => 'g1']]],
                        ['name' => 'phase_2', 'items' => ['item_b' => ['count' => 8, 'group' => 'g2']]],
                    ],
                    'retryLimit' => 200,
                ],
            ],
        ];

        yield 'campaign.simulate' => [
            'request' => [
                'method' => 'campaign.simulate',
                'items' => [
                    'gold' => ['count' => 2, 'group' => 'g1'],
                    'silver' => ['count' => 4, 'group' => 'g2'],
                ],
                'candidates' => $candidates,
                'options' => [
                    'iterations' => 25,
                    'retryLimit' => 100,
                    'rules' => ['perUserCap' => 1],
                ],
            ],
        ];
    }

    public function provideGrandRequests(): iterable
    {
        $candidates = $this->buildCandidates(2_000);

        yield 'grand' => [
            'request' => [
                'method' => 'grand',
                'items' => ['gift_1' => 50, 'gift_2' => 50, 'gift_3' => 50],
                'candidates' => $candidates,
                'options' => ['retryCount' => 300],
            ],
        ];
    }

    public function provideItemRequests(): iterable
    {
        yield 'lucky' => [
            'request' => [
                'method' => 'lucky',
                'items' => [
                    ['item' => 'gift_a', 'chances' => 10, 'amounts' => [1, 2]],
                    ['item' => 'gift_b', 'chances' => 20, 'amounts' => [3, 4]],
                ],
                'options' => ['count' => 3],
            ],
        ];

        yield 'probability' => [
            'request' => [
                'method' => 'probability',
                'items' => [
                    ['name' => 'item1', 'weight' => 10],
                    ['name' => 'item2', 'weight' => 20],
                ],
                'options' => ['count' => 2],
            ],
        ];

        yield 'elimination' => [
            'request' => [
                'method' => 'elimination',
                'items' => [
                    ['name' => 'item1'],
                    ['name' => 'item2'],
                ],
                'options' => ['count' => 2],
            ],
        ];

        yield 'weightedElimination' => [
            'request' => [
                'method' => 'weightedElimination',
                'items' => [
                    ['name' => 'item1', 'weight' => 5],
                    ['name' => 'item2', 'weight' => 10],
                ],
                'options' => ['count' => 2],
            ],
        ];

        yield 'roundRobin' => [
            'request' => [
                'method' => 'roundRobin',
                'items' => [
                    ['name' => 'item1'],
                    ['name' => 'item2'],
                ],
                'options' => ['count' => 3],
            ],
        ];

        yield 'cumulative' => [
            'request' => [
                'method' => 'cumulative',
                'items' => [
                    ['name' => 'item1'],
                    ['name' => 'item2'],
                ],
                'options' => ['count' => 3],
            ],
        ];

        yield 'batched' => [
            'request' => [
                'method' => 'batched',
                'items' => [
                    ['name' => 'item1'],
                    ['name' => 'item2'],
                    ['name' => 'item3'],
                ],
                'options' => ['count' => 2, 'withReplacement' => false],
            ],
        ];

        yield 'timeBased' => [
            'request' => [
                'method' => 'timeBased',
                'items' => [
                    ['name' => 'item1', 'weight' => 10, 'time' => 'daily'],
                    ['name' => 'item2', 'weight' => 20, 'time' => 'weekly'],
                ],
                'options' => ['count' => 2],
            ],
        ];

        yield 'weightedBatch' => [
            'request' => [
                'method' => 'weightedBatch',
                'items' => [
                    ['name' => 'item1', 'weight' => 10],
                    ['name' => 'item2', 'weight' => 20],
                ],
                'options' => ['count' => 3],
            ],
        ];

        yield 'sequential' => [
            'request' => [
                'method' => 'sequential',
                'items' => [
                    ['name' => 'item1'],
                    ['name' => 'item2'],
                ],
                'options' => ['count' => 3],
            ],
        ];

        yield 'rangeWeighted' => [
            'request' => [
                'method' => 'rangeWeighted',
                'items' => [
                    ['name' => 'item1', 'min' => 1, 'max' => 50, 'weight' => 10],
                    ['name' => 'item2', 'min' => 5, 'max' => 25, 'weight' => 15],
                ],
                'options' => ['count' => 2],
            ],
        ];
    }

    private function buildCandidates(int $count): array
    {
        return array_map(
            static fn(int $index): string => 'user_' . $index,
            range(1, $count),
        );
    }
}
