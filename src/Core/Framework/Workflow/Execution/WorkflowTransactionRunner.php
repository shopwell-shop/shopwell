<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * 把引擎调用包进一个可重试事务，并提供实例行锁。
 *
 * ## 为什么需要行锁
 *
 * 会签的聚合是"读一轮任务 → 算结论 → 写状态"。两个审批人同时点同意时，
 * 各自都读到对方还是 `todo`，都算出 `pending`，于是**谁也没有推进流程**，
 * 单子就停在原地——而且没有任何报错，只能靠人工发现。这类问题在测试里
 * 几乎复现不出来，必须靠临界区保证。
 *
 * 做法：事务第一步 `SELECT ... FOR UPDATE` 锁住实例行，让同一实例的
 * 审批请求排队。锁的是**实例**而不是任务：会签要读同节点的兄弟任务，
 * 锁单条任务挡不住"两条不同任务同时聚合"。
 *
 * ## 为什么用 RetryableTransaction
 *
 * 死锁/锁等待超时是 DB 会返回可重试错误的场景（MySQL 1213/1205）。
 * 平台封装好了重试，自己写 `beginTransaction()` 会丢掉这个能力。
 * 代价是闭包可能执行多次：闭包里**不要**做发短信、写文件这类外部副作用，
 * 那些必须走 {@see WorkflowEventQueue}。
 */
#[Package('framework')]
final class WorkflowTransactionRunner
{
    private int $activeOperations = 0;

    public function __construct(
        private readonly Connection $connection,
        private readonly WorkflowEventQueue $events,
    ) {
    }

    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $closure
     *
     * @return TReturn
     */
    public function run(\Closure $closure): mixed
    {
        $checkpoint = $this->events->checkpoint();
        ++$this->activeOperations;

        try {
            return RetryableTransaction::retryable($this->connection, function () use ($closure, $checkpoint): mixed {
                // A retry also follows commit failures, after the previous callback returned.
                $this->events->discardSince($checkpoint);

                return $closure();
            });
        } catch (\Throwable $exception) {
            $this->events->discardSince($checkpoint);

            throw $exception;
        } finally {
            --$this->activeOperations;
        }
    }

    public function isRunning(): bool
    {
        return $this->activeOperations > 0;
    }

    /**
     * 锁住实例行，直到当前事务结束。
     *
     * 必须在事务内调用；单独调用会立刻释放锁，等于没锁。
     * 实例不存在时不报错——真正的"不存在"由后续查询给出更准确的错误。
     */
    public function lockInstance(string $instanceId): void
    {
        $this->connection->fetchOne(
            'SELECT `id` FROM `workflow_instance` WHERE `id` = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($instanceId)]
        );
    }
}
