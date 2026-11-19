<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Executor;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class NodeExecutorRegistry
{
    /**
     * @var array<string, NodeExecutor>|null
     */
    private ?array $executors = null;

    /**
     * @param iterable<NodeExecutor> $registeredExecutors
     */
    public function __construct(private readonly iterable $registeredExecutors)
    {
    }

    public function get(string $type, string $version): NodeExecutor
    {
        if ($this->executors === null) {
            $this->executors = $this->load();
        }

        return $this->executors[$type . '@' . $version] ?? throw ApprovalFlowException::nodeExecutorNotFound($type, $version);
    }

    /**
     * @return array<string, NodeExecutor>
     */
    private function load(): array
    {
        $executors = [];
        foreach ($this->registeredExecutors as $executor) {
            $type = $executor->getType();
            $version = $executor->getVersion();
            if (!preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-zA-Z0-9_-]*$/D', $type)) {
                throw ApprovalFlowException::invalidNodeExecutor($executor::class, 'Types must be namespaced identifiers.');
            }
            if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) {
                throw ApprovalFlowException::invalidNodeExecutor($executor::class, 'Versions must use exact major.minor.patch values.');
            }
            $identity = $type . '@' . $version;
            if (isset($executors[$identity])) {
                throw ApprovalFlowException::duplicateNodeExecutor($type, $version, $executors[$identity]::class, $executor::class);
            }
            $executors[$identity] = $executor;
        }

        return $executors;
    }
}
