<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Result;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ApprovalResultHandlerRegistry
{
    /**
     * @var array<string, ApprovalResultHandler>|null
     */
    private ?array $handlers = null;

    /**
     * @param iterable<ApprovalResultHandler> $registeredHandlers
     */
    public function __construct(private readonly iterable $registeredHandlers)
    {
    }

    public function get(string $type, string $version): ApprovalResultHandler
    {
        if ($this->handlers === null) {
            $this->handlers = $this->load();
        }

        return $this->handlers[$type . '@' . $version] ?? throw ApprovalFlowException::resultHandlerNotFound($type, $version);
    }

    /**
     * @return array<string, ApprovalResultHandler>
     */
    private function load(): array
    {
        $handlers = [];
        foreach ($this->registeredHandlers as $handler) {
            $type = $handler->getType();
            $version = $handler->getVersion();
            $identity = $type . '@' . $version;
            if (isset($handlers[$identity])) {
                throw ApprovalFlowException::duplicateResultHandler($type, $version, $handlers[$identity]::class, $handler::class);
            }
            $handlers[$identity] = $handler;
        }

        return $handlers;
    }
}
