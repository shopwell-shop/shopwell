<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Executor;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\Copy\WorkflowCopyCollection;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\NodeOutcome;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowNodeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowSignal;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * Record unique copy recipients from userIdList and immediately continue without creating tasks.
 *
 * @internal
 */
#[Package('framework')]
final class CopyExecutor implements NodeExecutorInterface
{
    /**
     * @param EntityRepository<WorkflowCopyCollection> $copyRepository
     */
    public function __construct(private readonly EntityRepository $copyRepository)
    {
    }

    public function getNodeType(): string
    {
        return WorkflowNodeTypes::COPY;
    }

    public function enter(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): NodeOutcome
    {
        $userIds = $node->property('userIdList');

        if (!\is_array($userIds) || $userIds === []) {
            // Invalid copy recipients must not block runtime progression; save-time validation reports the configuration error.
            return NodeOutcome::proceed();
        }

        $rows = [];

        foreach (array_unique(array_filter($userIds, 'is_string')) as $userId) {
            if ($userId === '') {
                continue;
            }

            $rows[] = [
                'id' => Uuid::randomHex(),
                'instanceId' => $instance->id,
                'nodeId' => $node->id,
                'userId' => $userId,
            ];
        }

        if ($rows !== []) {
            $this->copyRepository->create($rows, $context->context());
        }

        return NodeOutcome::proceed();
    }

    public function resume(
        WorkflowInstanceEntity $instance,
        GraphNode $node,
        WorkflowSignal $signal,
        WorkflowExecutionContext $context,
    ): NodeOutcome {
        // Copy nodes have no task to resume.
        return NodeOutcome::proceed();
    }
}
