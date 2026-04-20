<?php

declare(strict_types=1);

namespace Infocyph\Draw;

use Infocyph\Draw\Audit\AuditTrail;
use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Random\SecureRandomGenerator;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Handlers\CampaignMethodHandler;
use Infocyph\Draw\Unified\Handlers\ItemMethodHandler;
use Infocyph\Draw\Unified\Handlers\LuckyMethodHandler;
use Infocyph\Draw\Unified\Handlers\UserMethodHandler;

class Draw
{
    /**
     * @var array<string, MethodHandlerInterface>
     */
    private array $handlerByMethod = [];

    public function __construct(?RandomGeneratorInterface $random = null)
    {
        $random ??= new SecureRandomGenerator();
        $handlers = [
            new LuckyMethodHandler($random),
            new ItemMethodHandler($random),
            new UserMethodHandler($random),
            new CampaignMethodHandler($random),
        ];

        foreach ($handlers as $handler) {
            foreach ($handler->methods() as $method) {
                $this->handlerByMethod[$method] = $handler;
            }
        }
    }

    /**
     * @param array<string, mixed> $request
     */
    public static function requestFingerprint(array $request): string
    {
        return AuditTrail::fingerprint($request);
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $rawMethod = $request['method'] ?? null;
        if (!is_string($rawMethod)) {
            throw new ValidationException('method is required.');
        }
        $method = trim($rawMethod);
        if ($method === '') {
            throw new ValidationException('method is required.');
        }
        if (!isset($this->handlerByMethod[$method])) {
            throw new ValidationException("Unsupported method: {$method}");
        }

        return $this->handlerByMethod[$method]->execute($request);
    }
}
