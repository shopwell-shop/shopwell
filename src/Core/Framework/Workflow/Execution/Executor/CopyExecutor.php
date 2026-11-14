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
 * @internal
 *
 * 抄送节点：只写抄送记录，立即往下走。
 *
 * **不生成任务、不阻塞流转**。这是抄送与审批的唯一区别，也是最容易被写错的地方：
 * 一旦给抄送也建了 `workflow_task`，抄送人就会出现在"待办"里，单子要等他点一下才动。
 *
 * 抄送人来自 `properties.userIdList`（用户 id 数组），与审批人的 `assigneeValue`
 * 刻意分开命名：抄送不参与"会签/或签"语义，混用一个字段会让设计器面板
 * 在两种节点之间共享状态，容易把审批人配到抄送节点上。
 *
 * 同一节点同一人重复配置时只写一条（去重），否则"我的抄送"列表会出现重复行。
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
            // 校验器已经在保存时拦过；运行期兜一层，但**不阻断流转**——
            // 抄送配错不该让整张单卡住，记一条空抄送比停流程好
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
        // 抄送节点没有任务，不会走到这里
        return NodeOutcome::proceed();
    }
}
