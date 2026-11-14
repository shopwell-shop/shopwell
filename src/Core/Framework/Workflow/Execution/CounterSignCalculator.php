<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskCollection;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskFinder;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskStates;

/**
 * @internal
 *
 * 会签 / 或签 / 顺序会签 / 单人的判定表。**纯函数**：不查库、不写库、不发事件。
 *
 * 输入必须是"本轮 + 本节点"的任务子集（由 {@see WorkflowTaskFinder} 保证）。
 * 越界的分母是这类系统最常见、也最难查的 bug：
 * 传进来的是全实例任务，会签就会把别的节点的同意算进来，流程提前通过。
 *
 * ## 判定表
 *
 * | 类型        | 通过条件        | 拒绝条件        | 其余  |
 * |-------------|-----------------|-----------------|-------|
 * | single      | 那一条同意      | 那一条拒绝      | 待办  |
 * | orSign      | **任一**同意    | **全部**拒绝    | 待办  |
 * | countersign | **全部**同意    | **任一**拒绝    | 待办  |
 * | orderSign   | 最后一位同意    | **任一**拒绝    | 待办  |
 *
 * ## 五个坑（都有对应单测，改这里先看测试）
 *
 * 1. **分母必须按轮次**：退回后同节点进入第 2 轮，第 1 轮的拒绝任务若还在分母里，
 *    会签永远差一个人。约束由 {@see WorkflowTaskFinder::forNode()} 的 `round` 条件保证。
 * 2. **取消的任务不算分母**：加签被撤回、或签有人被移除时任务置 canceled。
 *    这里用 {@see WorkflowTaskStates::COUNTED_STATES} 过滤，别在别处再写一份状态数组。
 * 3. **或签不能在第一个拒绝时就拒绝**：钉钉语义是"其他人还有机会同意"。
 *    写成 `rejected > 0 → rejected` 会让 3 人待办的单子在第一票反对时就死掉。
 * 4. **顺序会签不能一次建完任务**：否则后面几个人同时出现在待办里，谁都能先审。
 *    判定这边只认"最后一位已同意"，建任务的节奏由 {@see Executor\TaskExecutor::resume()} 控制。
 * 5. **聚合并发必须串行**：两个会签人同时点同意，各自都读到对方还是 todo，
 *    都算出 pending，流程卡死。串行化在 {@see WorkflowTransactionRunner}（实例行 FOR UPDATE），
 *    这个类只负责"给定一组任务，结论是什么"。
 */
#[Package('framework')]
final class CounterSignCalculator
{
    /**
     * @param WorkflowTaskCollection $tasks 必须已经是"本轮 + 本节点"的子集
     * @param int|null $expectedTotal 顺序会签的**总人数**（来自任务上的审批人快照）。
     *                                顺序会签的任务是逐条创建的，只用现有任务数当分母，
     *                                第一个人同意时就会算出"全部同意"，后面的人被跳过。
     *                                其它类型传 null（分母就是现有任务数）。
     */
    public function resolve(WorkflowTaskCollection $tasks, string $countersignType, ?int $expectedTotal = null): CounterSignResult
    {
        // 分母只算仍在场上的任务：取消/跳过的不算，否则会签凑不齐"全部同意"
        $counted = $tasks->filterByStates(WorkflowTaskStates::COUNTED_STATES);

        $agreed = $counted->countByState(WorkflowTaskStates::AGREED);
        $rejected = $counted->countByState(WorkflowTaskStates::REJECTED);
        $total = $counted->count();

        // 调用方是"某条任务刚完成"之后，所以 total 至少是 1。
        // 真出现 0 说明查询条件写错了（比如 round 传错），返回 pending 让流程停住等人查，
        // 绝不在这里"顺手"判通过——那会让漏查的任务直接消失。
        if ($total === 0) {
            return CounterSignResult::pending(0, 0, 0);
        }

        return match ($countersignType) {
            // 单人：就一条任务，它同意就通过
            WorkflowCountersignTypes::SINGLE => match (true) {
                $agreed === 1 => CounterSignResult::agreed($agreed, $rejected, $total),
                $rejected === 1 => CounterSignResult::rejected($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            // 或签：任一人同意即通过；必须"全部拒绝"才拒绝（坑 3）
            WorkflowCountersignTypes::OR_SIGN => match (true) {
                $agreed > 0 => CounterSignResult::agreed($agreed, $rejected, $total),
                $rejected === $total => CounterSignResult::rejected($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            // 实例锁保证只有首个决定进入聚合；其余任务随后取消。
            WorkflowCountersignTypes::FIRST_DECISION => match (true) {
                $rejected > 0 => CounterSignResult::rejected($agreed, $rejected, $total),
                $agreed > 0 => CounterSignResult::agreed($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            // 会签：全部同意才通过；任意一人拒绝即拒绝
            WorkflowCountersignTypes::COUNTERSIGN => match (true) {
                $rejected > 0 => CounterSignResult::rejected($agreed, $rejected, $total),
                $agreed === $total => CounterSignResult::agreed($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            // 顺序会签：最后一位同意才算全部同意；任意拒绝即拒绝（坑 4）
            WorkflowCountersignTypes::ORDER_SIGN => match (true) {
                $rejected > 0 => CounterSignResult::rejected($agreed, $rejected, $total),
                // 分母优先用"快照里的总人数"：任务逐条创建，现有任务数天然等于已同意数，
                // 拿它当分母会让第一个人同意时就算通过（这是顺序会签最隐蔽的 bug）。
                ($expectedTotal !== null ? $agreed >= $expectedTotal : $counted->lastSequenceIsAgreed()) => CounterSignResult::agreed($agreed, $rejected, $total),
                default => CounterSignResult::pending($agreed, $rejected, $total),
            },

            default => throw WorkflowExecutionException::unsupportedCountersignType($countersignType),
        };
    }
}
