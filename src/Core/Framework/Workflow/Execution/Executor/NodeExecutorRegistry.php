<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 *
 * 按节点 type 找执行器。
 *
 * 用 `tagged_iterator` 装配（见 services.php），所以新增执行器**不需要改这里**。
 * 结果缓存在实例属性里（不是静态属性）：容器按请求重建，静态缓存在长驻进程里会串租户。
 */
#[Package('framework')]
final class NodeExecutorRegistry
{
    /**
     * @var array<string, NodeExecutorInterface>|null
     */
    private ?array $resolved = null;

    /**
     * @param iterable<NodeExecutorInterface> $executors
     */
    public function __construct(private readonly iterable $executors)
    {
    }

    public function for(string $nodeType): NodeExecutorInterface
    {
        return $this->map()[$nodeType] ?? throw WorkflowExecutionException::unsupportedNodeType($nodeType);
    }

    public function has(string $nodeType): bool
    {
        return isset($this->map()[$nodeType]);
    }

    /**
     * @return array<string, NodeExecutorInterface>
     */
    private function map(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $map = [];

        foreach ($this->executors as $executor) {
            $type = $executor->getNodeType();

            // 同一个 type 注册两个实现时，后一个会静默覆盖——流程走错执行器极难排查，
            // 所以在装配阶段就炸掉
            if (isset($map[$type])) {
                throw WorkflowExecutionException::duplicateRegistration('runtime provider', $type);
            }

            $map[$type] = $executor;
        }

        return $this->resolved = $map;
    }
}
