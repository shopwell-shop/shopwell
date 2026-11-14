<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Task;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\CounterSignCalculator;

/**
 * @internal
 *
 * 任务集合，带三个会签聚合用得上的统计方法。
 *
 * 这些方法刻意放在集合上而不是 {@see CounterSignCalculator} 里：
 * 计算器只做"按判定表给结论"，集合负责"怎么数"。数错了是查询问题，
 * 判错了是业务问题，混在一起时难定位。
 *
 * @extends EntityCollection<WorkflowTaskEntity>
 */
#[Package('framework')]
class WorkflowTaskCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'workflow_task_collection';
    }

    /**
     * 只保留仍占用会签分母的状态。
     *
     * 取消/跳过的任务必须排除：否则"3 人会签、1 人被撤回"之后，
     * 剩下 2 人全部同意也凑不齐 3 票，流程永久卡住。
     *
     * @param list<string> $states
     */
    public function filterByStates(array $states): static
    {
        return $this->filter(
            static fn (WorkflowTaskEntity $task): bool => \in_array($task->state(), $states, true)
        );
    }

    public function countByState(string $state): int
    {
        $count = 0;

        foreach ($this->getIterator() as $task) {
            if ($task->state() === $state) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * 顺序会签是否已经"轮到的人都同意了"。
     *
     * 判据是序号最大的那条任务已同意，**不是**"全部同意"——顺序会签的任务是
     * 逐条创建的，还没轮到的人根本没有任务行。用 `count(agreed) === count(all)`
     * 会永远成立（因为当下只有已同意的那条存在），第二个审批人就被跳过了。
     */
    public function lastSequenceIsAgreed(): bool
    {
        $last = null;

        foreach ($this->getIterator() as $task) {
            if ($last === null || $task->sequence > $last->sequence) {
                $last = $task;
            }
        }

        return $last !== null && $last->state() === WorkflowTaskStates::AGREED;
    }

    /**
     * 下一个待办任务（顺序会签推进用）。
     *
     * 只取 `todo` 且序号最小的那条：理论上同一节点同一轮最多一条待办，
     * 按序号兜底是为了防御脏数据。
     */
    public function nextTodo(): ?WorkflowTaskEntity
    {
        $next = null;

        foreach ($this->getIterator() as $task) {
            if (!$task->isTodo()) {
                continue;
            }

            if ($next === null || $task->sequence < $next->sequence) {
                $next = $task;
            }
        }

        return $next;
    }

    protected function getExpectedClass(): string
    {
        return WorkflowTaskEntity::class;
    }
}
