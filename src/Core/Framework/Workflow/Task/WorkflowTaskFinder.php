<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowLogCollection;

/**
 * @internal
 *
 * 任务/归档/日志的查询集中地。
 *
 * 为什么不是 Repository 类：主系统全仓只有两个自定义 `EntityRepository`（都是测试桩），
 * 业务查询一律用 `Criteria` + 按 id 注入的 `*.repository`。这个类就是那层"查询对象"，
 * 存在的价值是把**容易写错的条件**（尤其是 `round`）只写一次。
 *
 * `forNode()` 是三处调用的共同入口：会签聚合、顺序会签推进、详情页展示。
 * 三处各写一份 Criteria 时，最可能的分歧就是有人忘了带 `round`——
 * 那会让退回后的会签永远差一个人。
 */
#[Package('framework')]
final class WorkflowTaskFinder
{
    /**
     * @param EntityRepository<WorkflowTaskCollection> $taskRepository
     * @param EntityRepository<WorkflowTaskHistoryCollection> $hisTaskRepository
     * @param EntityRepository<WorkflowLogCollection> $logRepository
     */
    public function __construct(
        private readonly EntityRepository $taskRepository,
        private readonly EntityRepository $hisTaskRepository,
        private readonly EntityRepository $logRepository,
    ) {
    }

    /**
     * 某实例、某节点、某一轮的全部任务。
     *
     * **必须带 round**：这是会签分母正确性的唯一保证。退回后同节点会有第 1 轮
     * 和第 2 轮两组任务，混在一起算会签，分母永远多一个人。
     */
    public function forNode(string $instanceId, string $nodeId, int $round, Context $context): WorkflowTaskCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));
        $criteria->addFilter(new EqualsFilter('nodeId', $nodeId));
        $criteria->addFilter(new EqualsFilter('round', $round));
        $criteria->addSorting(new FieldSorting('sequence', FieldSorting::ASCENDING));

        $criteria->addAssociation('stateMachineState');

        /** @var WorkflowTaskCollection $tasks */
        $tasks = $this->taskRepository->search($criteria, $context)->getEntities();

        return $tasks;
    }

    public function forInstance(string $instanceId, Context $context): WorkflowTaskCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('instanceId', $instanceId))
            ->addSorting(new FieldSorting('round'), new FieldSorting('sequence'), new FieldSorting('id'))
            ->addAssociation('stateMachineState');

        return $this->taskRepository->search($criteria, $context)->getEntities();
    }

    /**
     * 某人的待办。
     *
     * `$criteria` 由 Presenter 组装（分页、搜索、筛选都在那里），这里只补
     * "必须是 todo + 必须是我的"这两条安全条件——**任何调用方都不能省**。
     *
     * @return EntitySearchResult<WorkflowTaskCollection>
     */
    public function todoOf(string $userId, Criteria $criteria, Context $context): EntitySearchResult
    {
        $criteria->addFilter(new EqualsFilter('assigneeId', $userId));
        $criteria->addFilter(new EqualsFilter('stateMachineState.technicalName', WorkflowTaskStates::TODO));

        if ($criteria->getSorting() === []) {
            $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));
        }

        $criteria->addAssociation('stateMachineState');

        return $this->taskRepository->search($criteria, $context);
    }

    /**
     * 某人已处理的单（从归档表读，不是从任务表读：归档才不会被退回重开改掉）。
     *
     * @return EntitySearchResult<WorkflowTaskHistoryCollection>
     */
    public function doneOf(string $userId, Criteria $criteria, Context $context): EntitySearchResult
    {
        $criteria->addFilter(new EqualsFilter('assigneeId', $userId));

        if ($criteria->getSorting() === []) {
            $criteria->addSorting(new FieldSorting('completedAt', FieldSorting::DESCENDING));
        }

        return $this->hisTaskRepository->search($criteria, $context);
    }

    public function historyOf(string $instanceId, Context $context): WorkflowTaskHistoryCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));
        $criteria->addSorting(new FieldSorting('completedAt', FieldSorting::ASCENDING));

        /** @var WorkflowTaskHistoryCollection $hisTasks */
        $hisTasks = $this->hisTaskRepository->search($criteria, $context)->getEntities();

        return $hisTasks;
    }

    public function logsOf(string $instanceId, Context $context): WorkflowLogCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('instanceId', $instanceId));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        /** @var WorkflowLogCollection $logs */
        $logs = $this->logRepository->search($criteria, $context)->getEntities();

        return $logs;
    }

    /**
     * 待办数量（导航角标用）。只取 total，不 hydration。
     */
    public function todoCount(string $userId, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('assigneeId', $userId));
        $criteria->addFilter(new EqualsFilter('stateMachineState.technicalName', WorkflowTaskStates::TODO));
        $criteria->setLimit(1);

        return $this->taskRepository->search($criteria, $context)->getTotal();
    }
}
