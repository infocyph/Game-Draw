<?php

declare(strict_types=1);

namespace Infocyph\Draw\Unified\Handlers;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Support\ResultBuilder;
use SplFileObject;

class UserMethodHandler implements MethodHandlerInterface
{
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
        if (!is_array($items) || empty($items)) {
            throw new ValidationException('items is required and must be a non-empty array.');
        }
        if (!is_array($options)) {
            throw new ValidationException('options must be an array when provided.');
        }

        $retryCount = max(1, $this->intValue($options['retryCount'] ?? null, 10));
        $normalizedItems = $this->normalizeItems($items);
        $source = $this->resolveSource($request);
        $requestedCount = array_sum($normalizedItems);
        $users = $this->loadUsers($source['path']);

        try {
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
        } finally {
            if ($source['temporary'] && is_file($source['path'])) {
                unlink($source['path']);
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

    private function intValue(mixed $value, int $default): int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => $default,
        };
    }

    /**
     * @return array<int, string>
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
        while (!$file->eof()) {
            $row = $file->fgetcsv(',', '"', '\\');
            $id = trim((string) ($row[0] ?? ''));
            $id !== '' && $users[] = $id;
        }

        $users = array_values(array_unique($users));
        if (empty($users)) {
            throw new ValidationException('User source is empty.');
        }

        return $users;
    }

    /**
     * @return array<string, int>
     */
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
     * @return array{path: string, temporary: bool}
     */
    /**
     * @param array<string, mixed> $request
     * @return array{path: string, temporary: bool}
     */
    private function resolveSource(array $request): array
    {
        $sourceFileRaw = $request['sourceFile'] ?? null;
        $sourceFile = is_string($sourceFileRaw) ? trim($sourceFileRaw) : '';
        if ($sourceFile !== '') {
            return ['path' => $sourceFile, 'temporary' => false];
        }

        $candidates = $request['candidates'] ?? null;
        if (!is_array($candidates) || empty($candidates)) {
            throw new ValidationException('Provide sourceFile or non-empty candidates for grand method.');
        }

        $users = array_values(array_filter(
            array_map(fn($value) => is_string($value) ? trim($value) : '', $candidates),
            fn($value) => $value !== '',
        ));
        if (empty($users)) {
            throw new ValidationException('candidates must contain at least one non-empty user id.');
        }

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'draw-candidates-' . uniqid('', true) . '.csv';
        file_put_contents($tmp, implode(PHP_EOL, $users) . PHP_EOL, LOCK_EX);

        return ['path' => $tmp, 'temporary' => true];
    }
}
