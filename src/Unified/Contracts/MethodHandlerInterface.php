<?php

namespace Infocyph\Draw\Unified\Contracts;

interface MethodHandlerInterface
{
    /**
     * Unified request:
     * - method: string
     * - items: array
     * - candidates?: array
     * - sourceFile?: string
     * - options?: array
     *
     * Unified response:
     * - method: string
     * - entries: array<int, array{itemId: ?string, candidateId: ?string, value: mixed, meta: array}>
     * - raw: mixed
     * - meta: array
     */
    public function execute(array $request): array;
    /**
     * @return array<int, string>
     */
    public function methods(): array;
}
