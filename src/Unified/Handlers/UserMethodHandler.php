<?php

namespace Infocyph\Draw\Unified\Handlers;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Support\ResultBuilder;
use SplFileObject;

class UserMethodHandler implements MethodHandlerInterface
{
    public function __construct(private readonly RandomGeneratorInterface $random) {}

    public function execute(array $request): array
    {
        $method = (string) ($request['method'] ?? '');
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

        $source = $this->resolveSource($request);
        $retryCount = max(1, (int) ($options['retryCount'] ?? 10));
        $requestedCount = max(1, array_sum(array_map(intval(...), $items)));
        $users = $this->loadUsers($source['path']);
        $raw = $this->drawWinners($items, $users, $retryCount);
        $entries = [];
        foreach ($raw as $item => $users) {
            foreach ($users as $user) {
                $entries[] = ResultBuilder::entry(
                    itemId: (string) $item,
                    candidateId: (string) $user,
                    value: $user,
                );
            }
        }

        $source['temporary'] && @unlink($source['path']);

        return ResultBuilder::response('grand', $entries, $raw, $requestedCount, [
            'retryCount' => $retryCount,
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
    private function drawWinners(array $items, array $users, int $retryCount): array
    {
        $selectedIds = [];
        $selectedIndexes = [];
        $winners = [];
        $maxIndex = count($users) - 1;

        foreach ($items as $item => $count) {
            if (!is_int($count) || $count < 0) {
                throw new ValidationException("Item count for '{$item}' must be a non-negative integer.");
            }

            $winners[$item] = [];
            $target = min($count, count($users) - count($selectedIds));
            $picked = 0;
            $fails = 0;

            while ($picked < $target && $fails < $retryCount) {
                $index = $this->random->int(0, $maxIndex);
                if (isset($selectedIndexes[$index])) {
                    $fails++;
                    continue;
                }

                $userId = $users[$index];
                if (isset($selectedIds[$userId])) {
                    $fails++;
                    continue;
                }

                $selectedIndexes[$index] = true;
                $selectedIds[$userId] = true;
                $winners[$item][] = $userId;
                $picked++;
                $fails = 0;
            }
        }

        return $winners;
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
     * @return array{path: string, temporary: bool}
     */
    private function resolveSource(array $request): array
    {
        $sourceFile = trim((string) ($request['sourceFile'] ?? ''));
        if ($sourceFile !== '') {
            return ['path' => $sourceFile, 'temporary' => false];
        }

        $candidates = $request['candidates'] ?? null;
        if (!is_array($candidates) || empty($candidates)) {
            throw new ValidationException('Provide sourceFile or non-empty candidates for grand method.');
        }

        $users = array_values(array_filter(
            array_map(fn($value) => trim((string) $value), $candidates),
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
