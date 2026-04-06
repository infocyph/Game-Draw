<?php

namespace Infocyph\Draw;

use Infocyph\Draw\Contracts\RandomGeneratorInterface;
use Infocyph\Draw\Exceptions\ValidationException;
use Infocyph\Draw\Random\SecureRandomGenerator;
use Infocyph\Draw\Unified\Contracts\MethodHandlerInterface;
use Infocyph\Draw\Unified\Handlers\CampaignMethodHandler;
use Infocyph\Draw\Unified\Handlers\ItemMethodHandler;
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

    public function execute(array $request): array
    {
        $method = trim((string)($request['method'] ?? ''));
        if ($method === '') {
            throw new ValidationException('method is required.');
        }
        if (!isset($this->handlerByMethod[$method])) {
            throw new ValidationException("Unsupported method: {$method}");
        }

        return $this->handlerByMethod[$method]->execute($request);
    }
}
