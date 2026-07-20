<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Support\DrawValidator;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Handlers\Support\NormalizesHandlerInput;
use Infocyph\Draw\Unified\Support\ResultBuilder;
use SplFileObject;

class UserMethodHandler implements MethodHandlerInterface
{
    use NormalizesHandlerInput;

    private const MAX_CANDIDATES = 1_000_000;

    private const MAX_ITEMS = 10_000;

    private const MAX_TOTAL_WINNERS = 100_000;

    public function __construct(private readonly RandomGeneratorInterface $random) {}

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $method = $request['method'] ?? null;
        if (!is_string($method)) {
            throw new ValidationException('method is required and must be a string.');
        }
        if ($method !== 'grand') {
            throw new ValidationException("Unsupported user draw method: {$method}");
        }

        $items = $request['items'] ?? null;
        $options = $request['options'] ?? [];
        if (!is_array($items) || $items === []) {
            throw new ValidationException('items is required and must be a non-empty array.');
        }
        if (!is_array($options)) {
            throw new ValidationException('options must be an array when provided.');
        }
        DrawValidator::assertCountAtMost($items, self::MAX_ITEMS, 'items');

        $retryCount = $this->intValue($options['retryCount'] ?? null, 10, 'options.retryCount');
        DrawValidator::assertPositiveIntWithin($retryCount, self::MAX_TOTAL_WINNERS, 'options.retryCount');
        $normalizedItems = $this->normalizeItems($items);
        $requestedCount = $this->totalWinnerCount($normalizedItems);
        $users = $this->resolveUsers($request);

        $raw = $this->drawWinners($normalizedItems, $users);
        $entries = [];
        foreach ($raw as $item => $winners) {
            foreach ($winners as $user) {
                $entries[] = ResultBuilder::entry(
                    itemId: (string) $item,
                    candidateId: (string) $user,
                    value: $user,
                );
            }
        }

        $partialReason = null;
        if (count($entries) < $requestedCount) {
            $partialReason = 'insufficient_unique_candidates';
        }

        return ResultBuilder::response('grand', $entries, $raw, $requestedCount, [
            'retryCount' => $retryCount,
            'selectionMode' => 'pool_without_replacement',
            'partialReason' => $partialReason,
        ]);
    }

    public function methods(): array
    {
        return ['grand'];
    }

    /**
     * @param array<string, int> $items
     * @param array<int, string> $users
     * @return array<string, array<int, string>>
     */
    private function drawWinners(array $items, array $users): array
    {
        $availableUsers = array_values($users);
        $winners = [];

        foreach ($items as $item => $count) {
            $winners[$item] = [];
            $target = min($count, count($availableUsers));

            for ($picked = 0; $picked < $target; $picked++) {
                $maxIndex = count($availableUsers) - 1;
                $index = $this->random->int(0, $maxIndex);
                $winner = $availableUsers[$index];
                $winners[$item][] = $winner;

                // Remove winner in O(1) without preserving order.
                $availableUsers[$index] = $availableUsers[$maxIndex];
                array_pop($availableUsers);
            }
        }

        return $winners;
    }

    /**
     * @return list<string>
     */
    private function loadUsers(string $sourceFile): array
    {
        if (!is_readable($sourceFile)) {
            throw new ValidationException('sourceFile not found or not readable.');
        }

        $file = new SplFileObject($sourceFile);
        $file->setCsvControl(',', '"', '\\');
        $file->setFlags(SplFileObject::READ_CSV);

        $users = [];
        $seen = [];
        $rowCount = 0;
        while (!$file->eof()) {
            $row = $file->fgetcsv(',', '"', '\\');
            if (!is_array($row)) {
                continue;
            }
            $rowCount++;
            if ($rowCount > self::MAX_CANDIDATES) {
                throw new ValidationException('User source exceeds the 1000000 candidate limit.');
            }

            $id = trim((string) ($row[0] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $users[] = $id;
        }

        if ($users === []) {
            throw new ValidationException('User source is empty.');
        }

        return $users;
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<string, int>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item => $count) {
            if (!is_string($item) || trim($item) === '') {
                throw new ValidationException('Each grand item id must be a non-empty string.');
            }
            if (!is_int($count) || $count < 0) {
                throw new ValidationException("Item count for '{$item}' must be a non-negative integer.");
            }

            $normalized[$item] = $count;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $request
     * @return list<string>
     */
    private function resolveUsers(array $request): array
    {
        $sourceFileRaw = $request['sourceFile'] ?? null;
        $sourceFile = is_string($sourceFileRaw) ? trim($sourceFileRaw) : '';
        if ($sourceFile !== '') {
            return $this->loadUsers($sourceFile);
        }

        $candidates = $request['candidates'] ?? null;
        if (!is_array($candidates) || $candidates === []) {
            throw new ValidationException('Provide sourceFile or non-empty candidates for grand method.');
        }

        return $this->normalizeCandidateIds($candidates, self::MAX_CANDIDATES);
    }

    /**
     * @param array<string, int> $items
     */
    private function totalWinnerCount(array $items): int
    {
        $total = 0;
        foreach ($items as $count) {
            if ($count > self::MAX_TOTAL_WINNERS - $total) {
                throw new ValidationException('The total requested winner count exceeds 100000.');
            }
            $total += $count;
        }

        return $total;
    }
}
